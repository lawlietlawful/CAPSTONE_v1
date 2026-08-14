import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../core/constants/app_text_styles.dart';
import '../../models/referral.dart';
import '../../providers/auth_provider.dart';
import '../../providers/teacher_provider.dart';
import '../../widgets/common/form_widgets.dart';
import '../../widgets/common/notification_bell.dart';
import '../../widgets/common/navy_header.dart';
import '../../widgets/common/status_badges.dart';
import '../referrals/referral_detail_screen.dart';

/// Home screen: the teacher's counters plus their five most recent referrals.
/// Mirrors resources/views/teacher/dashboard.blade.php.
class DashboardScreen extends StatefulWidget {
  /// Jump to another bottom-nav tab (used by the quick-action tiles).
  final void Function(int tabIndex) onNavigate;

  /// Open the "Log Incident" flow.
  final VoidCallback onLogIncident;

  /// Open the "File Referral" flow.
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
      context.read<TeacherProvider>().loadDashboard();
      // Populate the header bell's unread badge.
      context.read<TeacherProvider>().loadNotifications();
    });
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final provider = context.watch<TeacherProvider>();

    return Column(
      children: [
        NavyHeader(
          title: 'Hello, ${auth.firstName}',
          subtitle: 'Here is your advisory at a glance',
          showAvatar: false,
          trailing: const NotificationBell(),
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
      return const Center(child: CircularProgressIndicator());
    }

    if (provider.dashboardError != null && stats.recentReferrals.isEmpty) {
      return CenteredMessage(
        icon: Icons.wifi_off_rounded,
        title: 'Could not load dashboard',
        message: provider.dashboardError!,
        actionLabel: 'Retry',
        onAction: () => provider.loadDashboard(),
      );
    }

    return RefreshIndicator(
      onRefresh: () => provider.loadDashboard(),
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        // Extra bottom padding so the last card can scroll clear of the FAB.
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 92),
        children: [
          Row(
            children: [
              Expanded(
                child: _StatCard(
                  icon: Icons.groups_rounded,
                  iconBg: AppColors.accentLight,
                  iconFg: AppColors.accentDark,
                  value: stats.totalStudents,
                  label: 'My Students',
                  onTap: () => widget.onNavigate(1),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _StatCard(
                  icon: Icons.outbox_rounded,
                  iconBg: AppColors.purpleBg,
                  iconFg: AppColors.purpleText,
                  value: stats.myReferrals,
                  label: 'Referrals Filed',
                  onTap: () => widget.onNavigate(2),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _StatCard(
                  icon: Icons.hourglass_top_rounded,
                  iconBg: AppColors.amberBg,
                  iconFg: AppColors.amberText,
                  value: stats.pendingReferrals,
                  label: 'Pending',
                  onTap: () {
                    provider.applyReferralFilter(search: '', status: 'pending');
                    widget.onNavigate(2);
                  },
                ),
              ),
            ],
          ),
          const SizedBox(height: 22),

          // ── Quick actions ──
          Text('Quick Actions', style: AppTextStyles.pageTitle),
          const SizedBox(height: 10),
          _ActionTile(
            icon: Icons.outbox_rounded,
            iconBg: AppColors.purpleBg,
            iconFg: AppColors.purpleText,
            title: 'File a Referral',
            subtitle: 'Send a student to the guidance office',
            onTap: widget.onFileReferral,
          ),
          const SizedBox(height: 8),
          _ActionTile(
            icon: Icons.edit_note_rounded,
            iconBg: AppColors.amberBg,
            iconFg: AppColors.amberText,
            title: 'Log an Incident',
            subtitle: 'Record a behavioral incident during class',
            onTap: widget.onLogIncident,
          ),
          const SizedBox(height: 22),

          // ── Students I referred ──
          Row(
            children: [
              Expanded(
                child: Text(
                  'Students I Referred',
                  style: AppTextStyles.pageTitle,
                ),
              ),
              GestureDetector(
                onTap: () => widget.onNavigate(2), // Referrals tab
                child: Text(
                  'View all',
                  style: GoogleFonts.inter(
                    fontSize: 12,
                    fontWeight: FontWeight.w500,
                    color: AppColors.accent,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          if (stats.recentReferrals.isEmpty)
            const _EmptyPanel(
              icon: Icons.inbox_outlined,
              title: 'No referrals yet',
              message: 'Referrals you file will appear here.',
            )
          else
            ...stats.recentReferrals.map(
              (r) => Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: _RecentReferralRow(referral: r),
              ),
            ),
        ],
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────

class _StatCard extends StatelessWidget {
  final IconData icon;
  final Color iconBg;
  final Color iconFg;
  final int value;
  final String label;
  final VoidCallback? onTap;

  const _StatCard({
    required this.icon,
    required this.iconBg,
    required this.iconFg,
    required this.value,
    required this.label,
    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 14),
        decoration: BoxDecoration(
          color: AppColors.surface,
          border: Border.all(color: AppColors.border),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          children: [
            Container(
              width: 36,
              height: 36,
              decoration: BoxDecoration(
                color: iconBg,
                borderRadius: BorderRadius.circular(9),
              ),
              child: Icon(icon, size: 18, color: iconFg),
            ),
            const SizedBox(height: 9),
            Text('$value', style: AppTextStyles.statValue),
            const SizedBox(height: 2),
            Text(label, textAlign: TextAlign.center, style: AppTextStyles.meta),
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
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.all(13),
        decoration: BoxDecoration(
          color: AppColors.surface,
          border: Border.all(color: AppColors.border),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Row(
          children: [
            Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(
                color: iconBg,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(icon, size: 19, color: iconFg),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(title, style: AppTextStyles.sectionTitle),
                  const SizedBox(height: 2),
                  Text(subtitle, style: AppTextStyles.meta),
                ],
              ),
            ),
            const Icon(
              Icons.chevron_right_rounded,
              size: 20,
              color: AppColors.text3,
            ),
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

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(12),
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
        padding: const EdgeInsets.all(13),
        decoration: BoxDecoration(
          color: AppColors.surface,
          border: Border.all(color: AppColors.border),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        referral.studentName ?? 'Unknown student',
                        style: AppTextStyles.sectionTitle,
                      ),
                      const SizedBox(height: 3),
                      Text(
                        referral.referralTypeLabel,
                        style: AppTextStyles.meta,
                      ),
                    ],
                  ),
                ),
                AppBadge.priority(referral.priority),
              ],
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                const Icon(
                  Icons.event_rounded,
                  size: 13,
                  color: AppColors.text3,
                ),
                const SizedBox(width: 4),
                Text(_dateLabel, style: AppTextStyles.meta),
                const Spacer(),
                AppBadge.status(referral.status),
                const SizedBox(width: 6),
                const Icon(
                  Icons.chevron_right_rounded,
                  size: 18,
                  color: AppColors.text3,
                ),
              ],
            ),
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
      decoration: BoxDecoration(
        color: AppColors.surface,
        border: Border.all(color: AppColors.border),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        children: [
          Icon(icon, size: 34, color: AppColors.text3),
          const SizedBox(height: 10),
          Text(title, style: AppTextStyles.sectionTitle),
          const SizedBox(height: 4),
          Text(message, textAlign: TextAlign.center, style: AppTextStyles.meta),
        ],
      ),
    );
  }
}
