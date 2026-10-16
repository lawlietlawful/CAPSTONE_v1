import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import '../../core/constants/app_colors.dart';
import '../../core/constants/app_decor.dart';
import '../../core/constants/app_text_styles.dart';
import '../../core/utils/date_utils.dart';
import '../../models/notification_model.dart';
import '../../models/referral_model.dart';
import '../../models/seminar_model.dart';
import '../../providers/notification_provider.dart';
import '../../providers/seminar_provider.dart';
import '../../providers/student_provider.dart';
import '../../widgets/common/messages_button.dart';
import '../../widgets/common/page_header.dart';
import '../../widgets/common/surfaces.dart';
import '../../widgets/premium/motion.dart';
import '../../widgets/premium/skeleton.dart';
import '../../widgets/seminars/seminar_card.dart';
import '../seminars/seminar_detail_screen.dart';

/// Home: a greeting, the two things to keep an eye on (seminars to attend,
/// unread alerts), a supportive word when Guidance is working with the
/// student, the next seminar and the latest alerts. No risk label or score —
/// students don't see how staff prioritise them.
class HomeScreen extends StatefulWidget {
  /// Switches the bottom-nav tab (0 Home, 1 Seminars, 2 Alerts).
  final ValueChanged<int>? onOpenTab;

  const HomeScreen({super.key, this.onOpenTab});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _fetchAll());
  }

  Future<void> _fetchAll() async {
    if (!mounted) return;
    await Future.wait([
      context.read<StudentProvider>().fetchAll(),
      context.read<SeminarProvider>().fetch(),
      context.read<NotificationProvider>().fetch(),
    ]);
  }

  String _greeting() {
    final h = DateTime.now().hour;
    if (h >= 5 && h < 12) return 'Good morning';
    if (h >= 12 && h < 18) return 'Good afternoon';
    return 'Good evening';
  }

  @override
  Widget build(BuildContext context) {
    final sp = context.watch<StudentProvider>();
    final np = context.watch<NotificationProvider>();
    final semp = context.watch<SeminarProvider>();

    return Column(
      children: [
        PageHeader(
          title: '${_greeting()}, ${sp.student?.firstName ?? 'there'}',
          subtitle: DateFormat('EEEE, MMMM d').format(DateTime.now()),
          trailing: const HeaderActions(),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: _fetchAll,
            color: AppColors.accent,
            child: _buildBody(sp, np, semp),
          ),
        ),
      ],
    );
  }

  Widget _buildBody(StudentProvider sp, NotificationProvider np, SeminarProvider semp) {
    if ((sp.isLoading || semp.isLoading) && sp.student == null) {
      return const _HomeSkeleton();
    }

    if (sp.error != null && sp.student == null) {
      return CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          SliverFillRemaining(
            hasScrollBody: false,
            child: Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.wifi_off_rounded, size: 40, color: AppColors.text3),
                    const SizedBox(height: 12),
                    Text('Could not load your home page', style: AppTextStyles.sectionHeading),
                    const SizedBox(height: 4),
                    Text(sp.error!, textAlign: TextAlign.center, style: AppTextStyles.meta),
                  ],
                ),
              ),
            ),
          ),
        ],
      );
    }

    var i = 0; // stagger order
    Widget section(Widget child) => FadeSlideIn(index: i++, child: child);
    final student = sp.student;
    final latest = np.notifications.take(2).toList();

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(16, 2, 16, 32),
      children: [
        section(_OverviewCard(
          classLabel: student == null
              ? ''
              : [student.gradeLevelDisplay, student.section].where((s) => s.isNotEmpty).join(' · '),
          seminars: semp.required.length,
          unread: np.unreadCount,
          onSeminars: () => widget.onOpenTab?.call(1),
          onAlerts: () => widget.onOpenTab?.call(2),
        )),
        if (sp.activeReferral != null) ...[
          const SizedBox(height: 12),
          section(_GuidanceCard(referral: sp.activeReferral!)),
        ],
        const SizedBox(height: 14),
        section(SectionHeading(
          title: 'Next seminar',
          action: semp.required.isEmpty && semp.completed.isEmpty ? null : 'See all',
          onAction: () => widget.onOpenTab?.call(1),
        )),
        if (semp.nextSeminar != null)
          section(_NextSeminarCard(seminar: semp.nextSeminar!))
        else
          section(const _EmptyPanel(
            icon: Icons.event_available_outlined,
            title: 'Nothing scheduled',
            message: 'Seminars the Guidance Office signs you up for will appear here.',
          )),
        if (latest.isNotEmpty) ...[
          const SizedBox(height: 14),
          section(SectionHeading(
            title: 'Latest alerts',
            action: 'See all',
            onAction: () => widget.onOpenTab?.call(2),
          )),
          section(CardGroup(
            children: [
              for (final n in latest)
                _AlertRow(
                  notification: n,
                  onTap: () {
                    if (!n.isRead) context.read<NotificationProvider>().markRead(n.id);
                    widget.onOpenTab?.call(2);
                  },
                ),
            ],
          )),
        ],
      ],
    );
  }
}

// ─────────────────────────────────────────────────────────────

class _OverviewCard extends StatelessWidget {
  final String classLabel;
  final int seminars;
  final int unread;
  final VoidCallback onSeminars;
  final VoidCallback onAlerts;

  const _OverviewCard({
    required this.classLabel,
    required this.seminars,
    required this.unread,
    required this.onSeminars,
    required this.onAlerts,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      clipBehavior: Clip.antiAlias,
      decoration: AppDecor.navyCard(),
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(18, 16, 18, 0),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    classLabel.isEmpty ? 'This school year' : classLabel,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: GoogleFonts.publicSans(fontSize: 13, fontWeight: FontWeight.w600, color: Colors.white),
                  ),
                ),
                const SizedBox(width: 12),
                Text('S.Y. ${AppDateUtils.schoolYear()}', style: AppTextStyles.navyLabel),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(6, 10, 6, 14),
            child: IntrinsicHeight(
              child: Row(
                children: [
                  Expanded(child: _Stat(value: seminars, label: 'Seminars to attend', onTap: onSeminars)),
                  const VerticalDivider(width: 1, indent: 8, endIndent: 8, color: AppColors.navyBorder),
                  Expanded(child: _Stat(value: unread, label: 'Unread alerts', onTap: onAlerts)),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Stat extends StatelessWidget {
  final int value;
  final String label;
  final VoidCallback onTap;

  const _Stat({required this.value, required this.label, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: '$value $label',
      excludeSemantics: true,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(10),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CountUp(value: value, style: AppTextStyles.statValue.copyWith(color: Colors.white)),
              const SizedBox(height: 2),
              Text(label, maxLines: 1, overflow: TextOverflow.ellipsis, style: AppTextStyles.navyLabel),
            ],
          ),
        ),
      ),
    );
  }
}

/// Shown while Guidance is working with the student: who is helping and
/// what happens next, in a supportive tone. Never why they were referred
/// or how they're rated.
class _GuidanceCard extends StatelessWidget {
  final ReferralModel referral;
  const _GuidanceCard({required this.referral});

  @override
  Widget build(BuildContext context) {
    final counselor = referral.counselorName;
    final message = counselor != null
        ? '$counselor from the Guidance Office will reach out to you. You can also drop by the office anytime.'
        : 'The Guidance Office will reach out to you soon. You can also drop by the office anytime.';

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: AppDecor.card(),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(color: AppColors.accentLight, borderRadius: BorderRadius.circular(10)),
            child: const Icon(Icons.chat_bubble_outline_rounded, size: 20, color: AppColors.accent),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Guidance is here for you',
                  style: GoogleFonts.publicSans(fontSize: 14.5, fontWeight: FontWeight.w600, color: AppColors.text1),
                ),
                const SizedBox(height: 4),
                Text(message, style: AppTextStyles.bodySecondary),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// The next seminar: a date tile, when it is ("Tomorrow", "In 3 days"),
/// where, and whether it's required.
class _NextSeminarCard extends StatelessWidget {
  final SeminarModel seminar;
  const _NextSeminarCard({required this.seminar});

  @override
  Widget build(BuildContext context) {
    return SeminarCard(
      seminar: seminar,
      emphasised: true,
      onViewDetails: () => Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => SeminarDetailScreen(seminar: seminar)),
      ),
    );
  }
}

class _AlertRow extends StatelessWidget {
  final NotificationModel notification;
  final VoidCallback onTap;
  const _AlertRow({required this.notification, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final unread = !notification.isRead;
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.only(top: 6),
              child: Container(
                width: 8,
                height: 8,
                decoration: BoxDecoration(
                  color: unread ? AppColors.accent : Colors.transparent,
                  shape: BoxShape.circle,
                ),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    notification.title.isEmpty ? 'Notification' : notification.title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: AppTextStyles.sectionTitle.copyWith(
                      fontWeight: unread ? FontWeight.w600 : FontWeight.w500,
                    ),
                  ),
                  const SizedBox(height: 1),
                  Text(
                    notification.message,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: AppTextStyles.meta.copyWith(color: AppColors.text2, height: 1.4),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            Text(AppDateUtils.formatNotificationTime(notification.createdAt), style: AppTextStyles.meta.copyWith(fontSize: 12)),
          ],
        ),
      ),
    );
  }
}

class _EmptyPanel extends StatelessWidget {
  final IconData icon;
  final String title;
  final String message;

  const _EmptyPanel({required this.icon, required this.title, required this.message});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 28, horizontal: 16),
      decoration: AppDecor.card(),
      child: Column(
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: const BoxDecoration(color: AppColors.background, shape: BoxShape.circle),
            child: Icon(icon, size: 24, color: AppColors.text3),
          ),
          const SizedBox(height: 12),
          Text(title, style: AppTextStyles.sectionTitle),
          const SizedBox(height: 4),
          Text(message, textAlign: TextAlign.center, style: AppTextStyles.meta),
        ],
      ),
    );
  }
}

/// Home's shape while the first load is in flight.
class _HomeSkeleton extends StatelessWidget {
  const _HomeSkeleton();

  @override
  Widget build(BuildContext context) {
    return Shimmer(
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 2, 16, 0),
        children: const [
          SkeletonBox(height: 132, radius: 14),
          SizedBox(height: 26),
          Align(alignment: Alignment.centerLeft, child: SkeletonBox(width: 120, height: 16)),
          SizedBox(height: 14),
          SkeletonCard(height: 120, leading: true),
        ],
      ),
    );
  }
}
