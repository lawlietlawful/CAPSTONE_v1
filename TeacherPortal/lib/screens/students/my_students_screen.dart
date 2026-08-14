import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../core/constants/app_text_styles.dart';
import '../../models/roster_student.dart';
import '../../providers/teacher_provider.dart';
import '../../widgets/common/form_widgets.dart';
import '../../widgets/common/navy_header.dart';
import '../../widgets/common/notification_bell.dart';
import '../../widgets/common/status_badges.dart';
import 'student_detail_screen.dart';

/// The teacher's advised students, each with their current ML risk level — the
/// monitoring view that surfaces the predictive analytics to the front-line
/// teacher. At-risk students float to the top so they can't be missed.
class MyStudentsScreen extends StatefulWidget {
  const MyStudentsScreen({super.key});

  @override
  State<MyStudentsScreen> createState() => _MyStudentsScreenState();
}

class _MyStudentsScreenState extends State<MyStudentsScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) context.read<TeacherProvider>().loadRoster();
    });
  }

  void _openDetail(RosterStudent s) {
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => StudentDetailScreen(studentId: s.id)),
    );
  }

  /// High → moderate → the rest, each block kept alphabetical (the API already
  /// sorts by last name). Puts who-needs-attention first.
  List<RosterStudent> _sorted(List<RosterStudent> roster) {
    int rank(String level) => switch (level) {
      'high' => 0,
      'moderate' => 1,
      'low' => 2,
      _ => 3,
    };
    final list = [...roster];
    list.sort((a, b) => rank(a.riskLevel).compareTo(rank(b.riskLevel)));
    return list;
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<TeacherProvider>();
    final total = provider.roster.length;

    return Column(
      children: [
        NavyHeader(
          title: 'My Students',
          subtitle: total == 0
              ? 'Students in your advisory'
              : '$total student${total == 1 ? '' : 's'} in your advisory',
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
    if (provider.loadingRoster && provider.roster.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (provider.rosterError != null && provider.roster.isEmpty) {
      return CenteredMessage(
        icon: Icons.wifi_off_rounded,
        title: 'Could not load students',
        message: provider.rosterError!,
        actionLabel: 'Retry',
        onAction: () => provider.loadRoster(),
      );
    }

    if (provider.roster.isEmpty) {
      return RefreshIndicator(
        onRefresh: () => provider.loadRoster(),
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: const [
            SizedBox(height: 140),
            CenteredMessage(
              icon: Icons.groups_outlined,
              title: 'No students yet',
              message:
                  'Students in the courses/sections you advise will appear here.',
            ),
          ],
        ),
      );
    }

    final students = _sorted(provider.roster);

    return RefreshIndicator(
      onRefresh: () => provider.loadRoster(),
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
        children: [
          _AtRiskBanner(count: provider.atRiskCount),
          if (provider.atRiskCount > 0) const SizedBox(height: 14),
          ...students.map(
            (s) => Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: _StudentCard(student: s, onTap: () => _openDetail(s)),
            ),
          ),
        ],
      ),
    );
  }
}

/// A calm reassurance when nobody's flagged, an amber nudge when someone is.
class _AtRiskBanner extends StatelessWidget {
  final int count;
  const _AtRiskBanner({required this.count});

  @override
  Widget build(BuildContext context) {
    final calm = count == 0;
    final bg = calm ? AppColors.greenBg : AppColors.amberBg;
    final fg = calm ? AppColors.greenText : AppColors.amberText;
    return Container(
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Icon(
            calm ? Icons.check_circle_rounded : Icons.warning_amber_rounded,
            size: 18,
            color: fg,
          ),
          const SizedBox(width: 9),
          Expanded(
            child: Text(
              calm
                  ? 'No students need attention right now.'
                  : '$count student${count == 1 ? '' : 's'} may need attention — shown first.',
              style: GoogleFonts.inter(
                fontSize: 12.5,
                fontWeight: FontWeight.w500,
                color: fg,
                height: 1.3,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _StudentCard extends StatelessWidget {
  final RosterStudent student;
  final VoidCallback onTap;

  const _StudentCard({required this.student, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.all(14),
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
                      Text(student.name, style: AppTextStyles.sectionTitle),
                      const SizedBox(height: 3),
                      Text(
                        [
                          student.schoolId,
                          student.yearSection,
                        ].where((s) => s.isNotEmpty).join(' · '),
                        style: AppTextStyles.meta,
                      ),
                    ],
                  ),
                ),
                AppBadge.risk(student.riskLevel),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                _Stat(
                  icon: Icons.receipt_long_rounded,
                  label:
                      '${student.reportsCount} report${student.reportsCount == 1 ? '' : 's'}',
                ),
                const SizedBox(width: 16),
                _Stat(
                  icon: Icons.outbox_rounded,
                  label: '${student.openReferrals} open',
                ),
                const Spacer(),
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

class _Stat extends StatelessWidget {
  final IconData icon;
  final String label;
  const _Stat({required this.icon, required this.label});

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 13, color: AppColors.text3),
        const SizedBox(width: 4),
        Text(label, style: AppTextStyles.meta),
      ],
    );
  }
}
