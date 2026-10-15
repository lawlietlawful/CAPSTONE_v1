import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import '../../core/constants/app_colors.dart';
import '../../core/constants/app_decor.dart';
import '../../core/constants/app_text_styles.dart';
import '../../core/utils/date_utils.dart';
import '../../models/referral_model.dart';
import '../../models/seminar_model.dart';
import '../../providers/notification_provider.dart';
import '../../providers/seminar_provider.dart';
import '../../providers/student_provider.dart';
import '../../widgets/common/messages_button.dart';
import '../../widgets/common/student_profile_sheet.dart';
import '../../widgets/premium/motion.dart';
import '../../widgets/premium/skeleton.dart';
import '../../widgets/seminars/ai_assigned_badge.dart';
import '../seminars/seminar_detail_screen.dart';

/// Home: a greeting, the two things to keep an eye on (seminars to attend,
/// unread notifications), a supportive word when Guidance is working with
/// the student, and the next seminar. No risk label or score — students
/// don't see how staff prioritise them.
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
        _HomeHeroHeader(
          greeting: _greeting(),
          firstName: sp.student?.firstName ?? 'there',
          initials: sp.student?.initials ?? '',
          onAvatarTap: () => StudentProfileSheet.show(context),
        ),
        Expanded(
          child: ColoredBox(
            color: AppColors.background,
            child: RefreshIndicator(
              onRefresh: _fetchAll,
              color: AppColors.accent,
              child: _buildBody(sp, np, semp),
            ),
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
                    Text('Could not load your home page', style: AppTextStyles.sectionTitle),
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
    Widget section(Widget child) => FadeSlideIn(
          index: i++,
          child: Padding(padding: const EdgeInsets.symmetric(horizontal: 16), child: child),
        );

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.only(bottom: 92),
      children: [
        // The overview card rides over the end of the navy header.
        Stack(
          children: [
            Container(height: 44, color: AppColors.navy),
            section(_OverviewCard(
              seminars: semp.required.length,
              unread: np.unreadCount,
              onSeminars: () => widget.onOpenTab?.call(1),
              onAlerts: () => widget.onOpenTab?.call(2),
            )),
          ],
        ),
        const SizedBox(height: 14),
        if (sp.activeReferral != null) ...[
          section(_GuidanceCard(referral: sp.activeReferral!)),
          const SizedBox(height: 14),
        ],
        const SizedBox(height: 10),
        section(_SectionTitle(
          title: 'Next seminar',
          action: semp.required.isEmpty && semp.completed.isEmpty ? null : 'See all',
          onAction: () => widget.onOpenTab?.call(1),
        )),
        const SizedBox(height: 10),
        if (semp.nextSeminar != null)
          section(_NextSeminarCard(seminar: semp.nextSeminar!))
        else
          section(const _EmptyPanel(
            icon: Icons.event_available_outlined,
            title: 'Nothing scheduled',
            message: 'Seminars the Guidance Office signs you up for will appear here.',
          )),
      ],
    );
  }
}

// ─────────────────────────────────────────────────────────────

class _HomeHeroHeader extends StatelessWidget {
  final String greeting;
  final String firstName;
  final String initials;
  final VoidCallback onAvatarTap;

  const _HomeHeroHeader({
    required this.greeting,
    required this.firstName,
    required this.initials,
    required this.onAvatarTap,
  });

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: AppColors.navy,
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 20),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('$greeting, $firstName', style: AppTextStyles.heroGreeting),
                    const SizedBox(height: 3),
                    Text(
                      DateFormat('EEEE, MMMM d').format(DateTime.now()),
                      style: AppTextStyles.navySubtitle,
                    ),
                  ],
                ),
              ),
              const MessagesButton(),
              const SizedBox(width: 10),
              Tooltip(
                message: 'Your profile',
                child: GestureDetector(
                  onTap: onAvatarTap,
                  child: Container(
                    width: 40,
                    height: 40,
                    decoration: const BoxDecoration(
                      color: AppColors.navy2,
                      shape: BoxShape.circle,
                      border: Border.fromBorderSide(BorderSide(color: Color(0x26FFFFFF), width: 2)),
                    ),
                    alignment: Alignment.center,
                    child: initials.isEmpty
                        ? const Icon(Icons.person_rounded, size: 20, color: AppColors.navyInitial)
                        : Text(
                            initials,
                            style: GoogleFonts.inter(fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.navyInitial),
                          ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _OverviewCard extends StatelessWidget {
  final int seminars;
  final int unread;
  final VoidCallback onSeminars;
  final VoidCallback onAlerts;

  const _OverviewCard({
    required this.seminars,
    required this.unread,
    required this.onSeminars,
    required this.onAlerts,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 6),
      decoration: AppDecor.card(shadow: AppDecor.shadowRaised, borderColor: Colors.transparent),
      child: IntrinsicHeight(
        child: Row(
          children: [
            Expanded(child: _Stat(value: seminars, label: 'Seminars to attend', color: AppColors.purple, onTap: onSeminars)),
            const VerticalDivider(width: 1, indent: 14, endIndent: 14, color: AppColors.border),
            Expanded(child: _Stat(value: unread, label: 'Unread alerts', color: AppColors.accent, onTap: onAlerts)),
          ],
        ),
      ),
    );
  }
}

class _Stat extends StatelessWidget {
  final int value;
  final String label;
  final Color color;
  final VoidCallback onTap;

  const _Stat({required this.value, required this.label, required this.color, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Pressable(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 14),
        child: Column(
          children: [
            CountUp(
              value: value,
              style: GoogleFonts.inter(fontSize: 26, fontWeight: FontWeight.w700, color: AppColors.text1, height: 1.1),
            ),
            const SizedBox(height: 6),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(width: 6, height: 6, decoration: BoxDecoration(color: color, shape: BoxShape.circle)),
                const SizedBox(width: 5),
                Flexible(child: Text(label, overflow: TextOverflow.ellipsis, style: AppTextStyles.meta)),
              ],
            ),
          ],
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
            width: 42,
            height: 42,
            decoration: BoxDecoration(color: AppColors.accentLight, borderRadius: BorderRadius.circular(12)),
            child: const Icon(Icons.support_agent_rounded, size: 21, color: AppColors.accent),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Guidance is here for you',
                  style: GoogleFonts.inter(fontSize: 14, fontWeight: FontWeight.w600, color: AppColors.text1),
                ),
                const SizedBox(height: 4),
                Text(message, style: GoogleFonts.inter(fontSize: 12.5, color: AppColors.text2, height: 1.45)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  final String title;
  final String? action;
  final VoidCallback? onAction;

  const _SectionTitle({required this.title, this.action, this.onAction});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Text(
            title,
            style: GoogleFonts.inter(fontSize: 15.5, fontWeight: FontWeight.w600, color: AppColors.text1),
          ),
        ),
        if (action != null)
          TextButton(
            onPressed: onAction,
            style: TextButton.styleFrom(padding: const EdgeInsets.symmetric(horizontal: 8), minimumSize: const Size(0, 32)),
            child: Text(
              action!,
              style: GoogleFonts.inter(fontSize: 12.5, fontWeight: FontWeight.w600, color: AppColors.accent),
            ),
          ),
      ],
    );
  }
}

/// The next seminar: a date tile, when it is ("Tomorrow", "In 3 days"),
/// where, and whether it's required.
class _NextSeminarCard extends StatelessWidget {
  final SeminarModel seminar;
  const _NextSeminarCard({required this.seminar});

  String? get _when {
    final date = DateTime.tryParse(seminar.date);
    if (date == null) return null;
    final today = DateUtils.dateOnly(DateTime.now());
    final days = DateUtils.dateOnly(date).difference(today).inDays;
    if (days <= 0) return 'Today';
    if (days == 1) return 'Tomorrow';
    if (days < 7) return 'In $days days';
    return null;
  }

  @override
  Widget build(BuildContext context) {
    final date = DateTime.tryParse(seminar.date);
    final when = _when;

    return Pressable(
      onTap: () => Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => SeminarDetailScreen(seminar: seminar)),
      ),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: AppDecor.card(),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 52,
              padding: const EdgeInsets.symmetric(vertical: 8),
              decoration: BoxDecoration(color: AppColors.accentLight, borderRadius: BorderRadius.circular(12)),
              child: Column(
                children: [
                  Text(
                    date == null ? '—' : DateFormat('MMM').format(date).toUpperCase(),
                    style: GoogleFonts.inter(fontSize: 10, fontWeight: FontWeight.w700, letterSpacing: 0.6, color: AppColors.accentDark),
                  ),
                  Text(
                    date == null ? '' : '${date.day}',
                    style: GoogleFonts.inter(fontSize: 19, fontWeight: FontWeight.w700, color: AppColors.accentDark, height: 1.2),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (when != null)
                    Container(
                      margin: const EdgeInsets.only(bottom: 6),
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(color: AppColors.amberBg, borderRadius: BorderRadius.circular(6)),
                      child: Text(
                        when,
                        style: GoogleFonts.inter(fontSize: 11, fontWeight: FontWeight.w600, color: AppColors.amberText),
                      ),
                    ),
                  Text(
                    seminar.title,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: GoogleFonts.inter(fontSize: 14.5, fontWeight: FontWeight.w600, color: AppColors.text1),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    '${AppDateUtils.formatTime(seminar.time)} · ${seminar.venue}',
                    style: AppTextStyles.meta,
                  ),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 6,
                    runSpacing: 6,
                    children: [
                      if (seminar.isRequired)
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(color: AppColors.accentLight, borderRadius: BorderRadius.circular(6)),
                          child: Text(
                            'Required',
                            style: GoogleFonts.inter(fontSize: 10.5, fontWeight: FontWeight.w600, color: AppColors.accent),
                          ),
                        ),
                      if (seminar.isAiAssigned) const AiAssignedBadge(),
                    ],
                  ),
                ],
              ),
            ),
            const Icon(Icons.chevron_right_rounded, size: 20, color: AppColors.text3),
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
            width: 52,
            height: 52,
            decoration: const BoxDecoration(color: AppColors.background, shape: BoxShape.circle),
            child: Icon(icon, size: 26, color: AppColors.text3),
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
        padding: EdgeInsets.zero,
        children: [
          Stack(
            children: [
              Container(height: 44, color: AppColors.navy),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Container(
                  height: 96,
                  decoration: AppDecor.card(shadow: const []),
                  padding: const EdgeInsets.symmetric(horizontal: 48),
                  child: const Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [_StatBone(), _StatBone()],
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 26),
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: 16),
            child: Align(alignment: Alignment.centerLeft, child: SkeletonBox(width: 120, height: 14)),
          ),
          const SizedBox(height: 12),
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: 16),
            child: SkeletonCard(height: 110, leading: true),
          ),
        ],
      ),
    );
  }
}

class _StatBone extends StatelessWidget {
  const _StatBone();

  @override
  Widget build(BuildContext context) {
    return const Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        SkeletonBox(width: 36, height: 22),
        SizedBox(height: 8),
        SkeletonBox(width: 80, height: 10),
      ],
    );
  }
}
