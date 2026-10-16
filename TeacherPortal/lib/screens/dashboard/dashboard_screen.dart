import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import '../../core/constants/app_colors.dart';
import '../../core/constants/app_decor.dart';
import '../../core/constants/app_text_styles.dart';
import '../../models/referral.dart';
import '../../providers/auth_provider.dart';
import '../../providers/teacher_provider.dart';
import '../../widgets/common/drafts_banner.dart';
import '../../widgets/common/form_widgets.dart';
import '../../widgets/common/notification_bell.dart';
import '../../widgets/common/page_header.dart';
import '../../widgets/common/status_badges.dart';
import '../../widgets/common/surfaces.dart';
import '../../widgets/common/upcoming_seminar_card.dart';
import '../../widgets/premium/motion.dart';
import '../../widgets/premium/skeleton.dart';
import '../referrals/referral_detail_screen.dart';

/// Home: a greeting, the navy overview card (counts + who needs attention),
/// the two things a teacher comes here to do, this fortnight's seminars, and
/// the latest referrals.
class DashboardScreen extends StatefulWidget {
  /// Switches the bottom-nav tab (0 Home, 1 Students, 2 Referrals, 3 Reports).
  final ValueChanged<int> onNavigate;
  final VoidCallback onLogIncident;
  final VoidCallback onFileReferral;

  const DashboardScreen({
    super.key,
    required this.onNavigate,
    required this.onLogIncident,
    required this.onFileReferral,
  });

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      final provider = context.read<TeacherProvider>();
      provider.loadDashboard();
      // Populate the header bell's unread badge.
      provider.loadNotifications();
      // The overview's "needs attention" line comes from the roster.
      provider.loadRoster();
    });
  }

  Future<void> _refresh() async {
    final provider = context.read<TeacherProvider>();
    await Future.wait([provider.loadDashboard(), provider.loadRoster()]);
  }

  String get _greeting {
    final hour = DateTime.now().hour;
    if (hour < 12) return 'Good morning';
    if (hour < 18) return 'Good afternoon';
    return 'Good evening';
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final provider = context.watch<TeacherProvider>();

    return Column(
      children: [
        PageHeader(
          title: '$_greeting, ${auth.firstName}',
          subtitle: DateFormat('EEEE, MMMM d').format(DateTime.now()),
          trailing: const HeaderActions(),
        ),
        Expanded(child: _buildBody(provider)),
      ],
    );
  }

  Widget _buildBody(TeacherProvider provider) {
    final stats = provider.dashboard;

    if (provider.loadingDashboard &&
        stats.myReferrals == 0 &&
        stats.recentReferrals.isEmpty) {
      return const _HomeSkeleton();
    }

    if (provider.dashboardError != null && stats.recentReferrals.isEmpty) {
      return CenteredMessage(
        icon: Icons.wifi_off_rounded,
        title: 'Could not load your dashboard',
        message: provider.dashboardError!,
        actionLabel: 'Retry',
        onAction: () => provider.loadDashboard(),
      );
    }

    var i = 0; // stagger order of the sections
    Widget section(Widget child) => FadeSlideIn(index: i++, child: child);

    return RefreshIndicator(
      color: AppColors.accent,
      onRefresh: _refresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        // Extra bottom padding so the last card can scroll clear of the FAB.
        padding: const EdgeInsets.fromLTRB(16, 2, 16, 96),
        children: [
          section(_OverviewCard(
            advisory: stats.advisory,
            totalStudents: stats.totalStudents,
            myReferrals: stats.myReferrals,
            pending: stats.pendingReferrals,
            atRisk: provider.atRiskCount,
            onStudents: () => widget.onNavigate(1),
            onAtRisk: () {
              provider.applyRosterRiskFilter('at_risk');
              widget.onNavigate(1);
            },
            onReferrals: () {
              provider.applyReferralFilter(search: '', status: null);
              widget.onNavigate(2);
            },
            onPending: () {
              provider.applyReferralFilter(search: '', status: 'pending');
              widget.onNavigate(2);
            },
          )),
          section(const DraftsBanner(padding: EdgeInsets.only(top: 12))),
          const SizedBox(height: 12),

          // ── Quick actions ──
          section(Row(
            children: [
              Expanded(
                child: _ActionTile(
                  icon: Icons.edit_note_rounded,
                  iconBg: AppColors.amberBg,
                  iconFg: AppColors.amberText,
                  title: 'Report a concern',
                  subtitle: 'Tell Guidance what you noticed',
                  onTap: widget.onLogIncident,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _ActionTile(
                  icon: Icons.outbox_rounded,
                  iconBg: AppColors.accentLight,
                  iconFg: AppColors.accent,
                  title: 'File a referral',
                  subtitle: 'Send a student to Guidance',
                  onTap: widget.onFileReferral,
                ),
              ),
            ],
          )),

          // ── Advisees' seminars in the next two weeks ──
          if (provider.upcomingSeminars.isNotEmpty) ...[
            const SizedBox(height: 20),
            section(const SectionHeading(
              title: 'Upcoming seminars',
              subtitle: 'Advisees out of class in the next two weeks',
            )),
            ...provider.upcomingSeminars.map(
              (s) => section(Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: UpcomingSeminarCard(seminar: s),
              )),
            ),
          ],

          // ── Students I referred ──
          const SizedBox(height: 14),
          section(SectionHeading(
            title: 'Recent referrals',
            action: stats.recentReferrals.isEmpty ? null : 'View all',
            onAction: () => widget.onNavigate(2),
          )),
          if (stats.recentReferrals.isEmpty)
            section(const _EmptyPanel(
              icon: Icons.inbox_outlined,
              title: 'No referrals yet',
              message: 'Students you refer to Guidance will appear here.',
            ))
          else
            section(CardGroup(
              children: [
                for (final r in stats.recentReferrals) _RecentReferralRow(referral: r),
              ],
            )),
        ],
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────

/// The navy card: the advisory, three counts, and — when anyone is flagged —
/// a line underneath that takes the teacher straight to them.
class _OverviewCard extends StatelessWidget {
  final List<String> advisory;
  final int totalStudents;
  final int myReferrals;
  final int pending;
  final int atRisk;
  final VoidCallback onStudents;
  final VoidCallback onAtRisk;
  final VoidCallback onReferrals;
  final VoidCallback onPending;

  const _OverviewCard({
    required this.advisory,
    required this.totalStudents,
    required this.myReferrals,
    required this.pending,
    required this.atRisk,
    required this.onStudents,
    required this.onAtRisk,
    required this.onReferrals,
    required this.onPending,
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
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Your advisory',
                  style: GoogleFonts.publicSans(fontSize: 13, fontWeight: FontWeight.w600, color: Colors.white),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    advisory.isEmpty ? 'No classes yet' : advisory.join(' · '),
                    textAlign: TextAlign.right,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: AppTextStyles.navyLabel,
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(6, 10, 6, 12),
            child: IntrinsicHeight(
              child: Row(
                children: [
                  Expanded(child: _Stat(value: totalStudents, label: 'Students', onTap: onStudents)),
                  const VerticalDivider(width: 1, indent: 8, endIndent: 8, color: AppColors.navyBorder),
                  Expanded(child: _Stat(value: myReferrals, label: 'Referrals', onTap: onReferrals)),
                  const VerticalDivider(width: 1, indent: 8, endIndent: 8, color: AppColors.navyBorder),
                  Expanded(child: _Stat(value: pending, label: 'Pending', onTap: onPending)),
                ],
              ),
            ),
          ),
          AnimatedSize(
            duration: Motion.medium,
            curve: Motion.enter,
            child: atRisk == 0
                ? const SizedBox(width: double.infinity)
                : Material(
                    color: AppColors.navy2,
                    child: InkWell(
                      key: const Key('attention-line'),
                      onTap: onAtRisk,
                      child: ConstrainedBox(
                        constraints: const BoxConstraints(minHeight: 48),
                        child: Padding(
                          padding: const EdgeInsets.fromLTRB(18, 12, 14, 12),
                          child: Row(
                            children: [
                              const Icon(Icons.warning_amber_rounded, size: 18, color: AppColors.navyWarning),
                              const SizedBox(width: 10),
                              Expanded(
                                child: Text(
                                  '$atRisk student${atRisk == 1 ? '' : 's'} may need your attention',
                                  style: GoogleFonts.publicSans(fontSize: 13.5, fontWeight: FontWeight.w600, color: Colors.white),
                                ),
                              ),
                              const Icon(Icons.chevron_right_rounded, size: 20, color: AppColors.navySubText),
                            ],
                          ),
                        ),
                      ),
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
              CountUp(
                value: value,
                style: AppTextStyles.statValue.copyWith(color: Colors.white),
              ),
              const SizedBox(height: 2),
              Text(label, maxLines: 1, overflow: TextOverflow.ellipsis, style: AppTextStyles.navyLabel),
            ],
          ),
        ),
      ),
    );
  }
}

class _ActionTile extends StatelessWidget {
  final IconData icon;
  final Color iconBg;
  final Color iconFg;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  const _ActionTile({
    required this.icon,
    required this.iconBg,
    required this.iconFg,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      child: Pressable(
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.all(16),
          decoration: AppDecor.card(),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(color: iconBg, borderRadius: BorderRadius.circular(10)),
                child: Icon(icon, size: 21, color: iconFg),
              ),
              const SizedBox(height: 12),
              Text(title, style: GoogleFonts.publicSans(fontSize: 14.5, fontWeight: FontWeight.w600, color: AppColors.text1)),
              const SizedBox(height: 2),
              Text(subtitle, maxLines: 2, style: AppTextStyles.meta.copyWith(color: AppColors.text2)),
            ],
          ),
        ),
      ),
    );
  }
}

class _RecentReferralRow extends StatelessWidget {
  final Referral referral;
  const _RecentReferralRow({required this.referral});

  String get _dateLabel {
    final raw = referral.createdAt;
    if (raw == null || raw.isEmpty) return '—';
    try {
      return DateFormat('MMM d').format(DateTime.parse(raw).toLocal());
    } catch (_) {
      return raw;
    }
  }

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: () async {
        final prov = context.read<TeacherProvider>();
        await Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => ReferralDetailScreen(referralId: referral.id),
          ),
        );
        prov.loadDashboard();
      },
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        child: Row(
          children: [
            InitialsAvatar(initials: InitialsAvatar.of(referral.studentName), size: 38),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    referral.studentName ?? 'Unknown student',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: AppTextStyles.sectionTitle,
                  ),
                  const SizedBox(height: 1),
                  Text(
                    '${referral.referralTypeLabel} · $_dateLabel',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: AppTextStyles.meta,
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            AppBadge.status(referral.status),
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

  const _EmptyPanel({
    required this.icon,
    required this.title,
    required this.message,
  });

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
        physics: const NeverScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 2, 16, 0),
        children: const [
          SkeletonBox(height: 168, radius: 14),
          SizedBox(height: 12),
          Row(
            children: [
              Expanded(child: SkeletonBox(height: 120, radius: 12)),
              SizedBox(width: 12),
              Expanded(child: SkeletonBox(height: 120, radius: 12)),
            ],
          ),
          SizedBox(height: 28),
          Align(alignment: Alignment.centerLeft, child: SkeletonBox(width: 140, height: 16)),
          SizedBox(height: 14),
          SkeletonCard(leading: true),
          SizedBox(height: 10),
          SkeletonCard(leading: true),
        ],
      ),
    );
  }
}
