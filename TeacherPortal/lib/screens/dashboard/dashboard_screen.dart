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
import '../../widgets/common/navy_header.dart';
import '../../widgets/common/status_badges.dart';
import '../../widgets/common/upcoming_seminar_card.dart';
import '../../widgets/premium/motion.dart';
import '../../widgets/premium/skeleton.dart';
import '../referrals/referral_detail_screen.dart';

/// Home: a greeting, the overview card (counts + who needs attention), the
/// two things a teacher comes here to do, this fortnight's seminars, and the
/// latest referrals.
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
        NavyHeader(
          title: '$_greeting, ${auth.firstName}',
          subtitle: DateFormat('EEEE, MMMM d').format(DateTime.now()),
          showAvatar: false,
          trailing: const HeaderActions(),
        ),
        Expanded(
          child: Container(
            color: AppColors.background,
            child: _buildBody(provider),
          ),
        ),
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
    Widget section(Widget child) => FadeSlideIn(
          index: i++,
          child: Padding(padding: const EdgeInsets.symmetric(horizontal: 16), child: child),
        );

    return RefreshIndicator(
      color: AppColors.accent,
      onRefresh: _refresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        // Extra bottom padding so the last card can scroll clear of the FAB.
        padding: const EdgeInsets.only(bottom: 92),
        children: [
          // The overview card rides over the end of the navy header.
          Stack(
            children: [
              Container(height: 44, color: AppColors.navy),
              section(_OverviewCard(
                totalStudents: stats.totalStudents,
                myReferrals: stats.myReferrals,
                pending: stats.pendingReferrals,
                atRisk: provider.atRiskCount,
                onStudents: () => widget.onNavigate(1),
                onReferrals: () => widget.onNavigate(2),
                onPending: () {
                  provider.applyReferralFilter(search: '', status: 'pending');
                  widget.onNavigate(2);
                },
              )),
            ],
          ),
          const SizedBox(height: 14),
          section(const DraftsBanner(padding: EdgeInsets.only(bottom: 4))),

          // ── Quick actions ──
          section(Row(
            children: [
              Expanded(
                child: _ActionTile(
                  icon: Icons.outbox_rounded,
                  iconBg: AppColors.purpleBg,
                  iconFg: AppColors.purpleText,
                  title: 'File a referral',
                  subtitle: 'Send a student to Guidance',
                  onTap: widget.onFileReferral,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _ActionTile(
                  icon: Icons.edit_note_rounded,
                  iconBg: AppColors.amberBg,
                  iconFg: AppColors.amberText,
                  title: 'Log an incident',
                  subtitle: 'Record what happened in class',
                  onTap: widget.onLogIncident,
                ),
              ),
            ],
          )),
          const SizedBox(height: 24),

          // ── Advisees' seminars in the next two weeks ──
          if (provider.upcomingSeminars.isNotEmpty) ...[
            section(const _SectionTitle(
              title: 'Upcoming seminars',
              subtitle: 'Advisees who will be out of class in the next two weeks',
            )),
            const SizedBox(height: 10),
            ...provider.upcomingSeminars.map(
              (s) => section(Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: UpcomingSeminarCard(seminar: s),
              )),
            ),
            const SizedBox(height: 14),
          ],

          // ── Students I referred ──
          section(_SectionTitle(
            title: 'Recent referrals',
            action: 'View all',
            onAction: () => widget.onNavigate(2),
          )),
          const SizedBox(height: 10),
          if (stats.recentReferrals.isEmpty)
            section(const _EmptyPanel(
              icon: Icons.inbox_outlined,
              title: 'No referrals yet',
              message: 'Students you refer to Guidance will appear here.',
            ))
          else
            ...stats.recentReferrals.map(
              (r) => section(Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: _RecentReferralRow(referral: r),
              )),
            ),
        ],
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────

class _SectionTitle extends StatelessWidget {
  final String title;
  final String? subtitle;
  final String? action;
  final VoidCallback? onAction;

  const _SectionTitle({required this.title, this.subtitle, this.action, this.onAction});

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: GoogleFonts.inter(fontSize: 15.5, fontWeight: FontWeight.w600, color: AppColors.text1),
              ),
              if (subtitle != null) ...[
                const SizedBox(height: 2),
                Text(subtitle!, style: AppTextStyles.meta),
              ],
            ],
          ),
        ),
        if (action != null)
          TextButton(
            onPressed: onAction,
            style: TextButton.styleFrom(
              padding: const EdgeInsets.symmetric(horizontal: 8),
              minimumSize: const Size(0, 32),
            ),
            child: Text(
              action!,
              style: GoogleFonts.inter(fontSize: 12.5, fontWeight: FontWeight.w600, color: AppColors.accent),
            ),
          ),
      ],
    );
  }
}

/// The three counts in one raised card, and — when anyone is flagged — a
/// line underneath that takes the teacher straight to them.
class _OverviewCard extends StatelessWidget {
  final int totalStudents;
  final int myReferrals;
  final int pending;
  final int atRisk;
  final VoidCallback onStudents;
  final VoidCallback onReferrals;
  final VoidCallback onPending;

  const _OverviewCard({
    required this.totalStudents,
    required this.myReferrals,
    required this.pending,
    required this.atRisk,
    required this.onStudents,
    required this.onReferrals,
    required this.onPending,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: AppDecor.card(shadow: AppDecor.shadowRaised, borderColor: Colors.transparent),
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 6),
            child: IntrinsicHeight(
              child: Row(
                children: [
                  Expanded(child: _Stat(value: totalStudents, label: 'My students', color: AppColors.accent, onTap: onStudents)),
                  const VerticalDivider(width: 1, indent: 14, endIndent: 14, color: AppColors.border),
                  Expanded(child: _Stat(value: myReferrals, label: 'Referrals filed', color: AppColors.purple, onTap: onReferrals)),
                  const VerticalDivider(width: 1, indent: 14, endIndent: 14, color: AppColors.border),
                  Expanded(child: _Stat(value: pending, label: 'Pending', color: AppColors.amber, onTap: onPending)),
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
                    color: AppColors.amberBg,
                    borderRadius: const BorderRadius.vertical(bottom: Radius.circular(AppDecor.radius)),
                    child: InkWell(
                      key: const Key('attention-line'),
                      onTap: onStudents,
                      borderRadius: const BorderRadius.vertical(bottom: Radius.circular(AppDecor.radius)),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 11),
                        child: Row(
                          children: [
                            const Icon(Icons.warning_amber_rounded, size: 17, color: AppColors.amberText),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                '$atRisk student${atRisk == 1 ? '' : 's'} may need your attention',
                                style: GoogleFonts.inter(fontSize: 12.5, fontWeight: FontWeight.w600, color: AppColors.amberText),
                              ),
                            ),
                            const Icon(Icons.chevron_right_rounded, size: 18, color: AppColors.amberText),
                          ],
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
                Flexible(
                  child: Text(label, overflow: TextOverflow.ellipsis, style: AppTextStyles.meta),
                ),
              ],
            ),
          ],
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
    return Pressable(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: AppDecor.card(),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(color: iconBg, borderRadius: BorderRadius.circular(12)),
                  child: Icon(icon, size: 20, color: iconFg),
                ),
                const Spacer(),
                const Icon(Icons.arrow_forward_rounded, size: 17, color: AppColors.text3),
              ],
            ),
            const SizedBox(height: 14),
            Text(title, style: GoogleFonts.inter(fontSize: 13.5, fontWeight: FontWeight.w600, color: AppColors.text1)),
            const SizedBox(height: 3),
            Text(subtitle, maxLines: 2, style: AppTextStyles.meta),
          ],
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
      return DateFormat('MMM d, yyyy').format(DateTime.parse(raw).toLocal());
    } catch (_) {
      return raw;
    }
  }

  /// "Cruz, Ana" → "AC".
  String get _initials {
    final name = referral.studentName ?? '';
    final parts = name.split(', ');
    final letters = parts.length == 2
        ? '${parts[1].isNotEmpty ? parts[1][0] : ''}${parts[0].isNotEmpty ? parts[0][0] : ''}'
        : (name.isNotEmpty ? name[0] : '?');
    return letters.toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    return Pressable(
      onTap: () async {
        final prov = context.read<TeacherProvider>();
        await Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => ReferralDetailScreen(referralId: referral.id),
          ),
        );
        prov.loadDashboard();
      },
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: AppDecor.card(),
        child: Row(
          children: [
            Container(
              width: 40,
              height: 40,
              alignment: Alignment.center,
              decoration: BoxDecoration(color: AppColors.accentLight, borderRadius: BorderRadius.circular(12)),
              child: Text(
                _initials,
                style: GoogleFonts.inter(fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.accentDark),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          referral.studentName ?? 'Unknown student',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: AppTextStyles.sectionTitle,
                        ),
                      ),
                      AppBadge.status(referral.status),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(
                    '${referral.referralTypeLabel} · $_dateLabel',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: AppTextStyles.meta,
                  ),
                ],
              ),
            ),
            const SizedBox(width: 4),
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

  const _EmptyPanel({
    required this.icon,
    required this.title,
    required this.message,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 30, horizontal: 16),
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
        physics: const NeverScrollableScrollPhysics(),
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
                  padding: const EdgeInsets.symmetric(horizontal: 26),
                  child: const Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      _StatBone(), _StatBone(), _StatBone(),
                    ],
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: 16),
            child: Row(
              children: [
                Expanded(child: SkeletonBox(height: 112, radius: 16)),
                SizedBox(width: 10),
                Expanded(child: SkeletonBox(height: 112, radius: 16)),
              ],
            ),
          ),
          const SizedBox(height: 26),
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: 16),
            child: Align(alignment: Alignment.centerLeft, child: SkeletonBox(width: 140, height: 14)),
          ),
          const SizedBox(height: 12),
          for (var i = 0; i < 3; i++)
            const Padding(
              padding: EdgeInsets.fromLTRB(16, 0, 16, 10),
              child: SkeletonCard(height: 72, leading: true),
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
        SkeletonBox(width: 64, height: 10),
      ],
    );
  }
}
