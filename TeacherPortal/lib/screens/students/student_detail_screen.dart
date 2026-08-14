import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../core/constants/app_text_styles.dart';
import '../../models/roster_student.dart';
import '../../models/student_detail.dart';
import '../../providers/teacher_provider.dart';
import '../../widgets/common/form_widgets.dart';
import '../../widgets/common/status_badges.dart';
import '../reports/report_detail_screen.dart';
import '../referrals/referral_detail_screen.dart';
import '../seminars/matching_seminars_screen.dart';
import '../seminars/seminar_detail_screen.dart';

/// A single student's full picture: current risk, the merged timeline of every
/// report and referral about them, and their assigned interventions.
class StudentDetailScreen extends StatefulWidget {
  final int studentId;
  const StudentDetailScreen({super.key, required this.studentId});

  @override
  State<StudentDetailScreen> createState() => _StudentDetailScreenState();
}

class _StudentDetailScreenState extends State<StudentDetailScreen> {
  late Future<StudentDetail> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<StudentDetail> _load() =>
      context.read<TeacherProvider>().fetchStudentDetail(widget.studentId);

  void _retry() => setState(() => _future = _load());

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text(
          'Student',
          style: GoogleFonts.inter(fontSize: 16, fontWeight: FontWeight.w500),
        ),
      ),
      body: FutureBuilder<StudentDetail>(
        future: _future,
        builder: (context, snap) {
          if (snap.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snap.hasError || !snap.hasData) {
            return CenteredMessage(
              icon: Icons.wifi_off_rounded,
              title: 'Could not load student',
              message: 'Please check your connection and try again.',
              actionLabel: 'Retry',
              onAction: _retry,
            );
          }
          return _buildDetail(snap.data!);
        },
      ),
    );
  }

  Widget _buildDetail(StudentDetail detail) {
    final s = detail.student;
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
      children: [
        _Header(student: s),
        const SizedBox(height: 14),
        _RiskExplainerCard(student: s, seminars: detail.seminars),
        const SizedBox(height: 22),

        _SectionLabel('Case history', count: detail.timeline.length),
        const SizedBox(height: 10),
        if (detail.timeline.isEmpty)
          const _EmptyRow(
            icon: Icons.inbox_outlined,
            text: 'No reports or referrals on file.',
          )
        else
          ...detail.timeline.map(
            (t) => Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: _TimelineCard(item: t),
            ),
          ),

        const SizedBox(height: 22),
        _SectionLabel('Interventions', count: detail.seminars.length),
        const SizedBox(height: 10),
        if (detail.seminars.isEmpty)
          const _EmptyRow(
            icon: Icons.school_outlined,
            text: 'No seminars assigned yet.',
          )
        else
          ...detail.seminars.map(
            (sm) => Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: _SeminarCard(seminar: sm),
            ),
          ),
      ],
    );
  }
}

class _Header extends StatelessWidget {
  final RosterStudent student;
  const _Header({required this.student});

  @override
  Widget build(BuildContext context) {
    final meta = [
      student.schoolId,
      student.yearSection,
      student.course,
    ].where((e) => e != null && e.isNotEmpty).join(' · ');
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: AppColors.surface,
        border: Border.all(color: AppColors.border),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(student.name, style: AppTextStyles.pageTitle),
          const SizedBox(height: 4),
          Text(meta, style: AppTextStyles.meta),
          const SizedBox(height: 14),
          AppBadge.risk(student.riskLevel),
        ],
      ),
    );
  }
}

/// Explains *why* the risk badge/score looks the way it does: a visual scale,
/// the plain-language reason from the last assessment, and a recommended
/// seminar if one hasn't been assigned yet. The ML engine only returns a
/// score + label (no per-feature breakdown), so this surfaces the richest
/// context actually available rather than a bare number.
class _RiskExplainerCard extends StatelessWidget {
  final RosterStudent student;
  final List<SeminarItem> seminars;
  const _RiskExplainerCard({required this.student, required this.seminars});

  static const _tagLabels = {
    'general': 'General guidance',
    'attendance_intervention': 'Attendance intervention',
    'academic_recovery': 'Academic recovery',
    'anti_bullying': 'Anti-bullying',
    'values_formation': 'Values formation',
    'orientation': 'Orientation',
  };

  Color get _scaleColor {
    switch (student.riskLevel) {
      case 'high':
        return AppColors.redText;
      case 'moderate':
        return AppColors.amberText;
      case 'low':
        return AppColors.greenText;
      default:
        return AppColors.text3;
    }
  }

  @override
  Widget build(BuildContext context) {
    if (student.riskLevel == 'not_assessed') {
      return Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: AppColors.surface,
          border: Border.all(color: AppColors.border),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Row(
          children: [
            const Icon(
              Icons.hourglass_top_rounded,
              size: 16,
              color: AppColors.text3,
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                'No risk assessment yet. This appears once the student has a '
                'behavioral report or referral on file.',
                style: GoogleFonts.inter(
                  fontSize: 12,
                  color: AppColors.text3,
                  height: 1.4,
                ),
              ),
            ),
          ],
        ),
      );
    }

    final score = (student.riskScore ?? 0).clamp(0, 100).toDouble();
    final tag = student.recommendedSeminarTag;
    final tagLabel = tag == null ? null : (_tagLabels[tag] ?? tag);
    final matchingSeminar = tag == null
        ? null
        : seminars.cast<SeminarItem?>().firstWhere(
            (s) => s?.triggerReason == tag,
            orElse: () => null,
          );

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surface,
        border: Border.all(color: AppColors.border),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Why this risk level',
            style: GoogleFonts.inter(
              fontSize: 12.5,
              fontWeight: FontWeight.w600,
              color: AppColors.text1,
            ),
          ),
          const SizedBox(height: 10),
          ClipRRect(
            borderRadius: BorderRadius.circular(4),
            child: LinearProgressIndicator(
              value: score / 100,
              minHeight: 7,
              backgroundColor: AppColors.background,
              valueColor: AlwaysStoppedAnimation(_scaleColor),
            ),
          ),
          const SizedBox(height: 5),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text('Low', style: AppTextStyles.meta),
              Text(
                '${score.toStringAsFixed(0)} / 100',
                style: GoogleFonts.inter(
                  fontSize: 11,
                  fontWeight: FontWeight.w600,
                  color: _scaleColor,
                ),
              ),
              Text('High', style: AppTextStyles.meta),
            ],
          ),
          if (student.riskReason != null && student.riskReason!.isNotEmpty) ...[
            const SizedBox(height: 12),
            Text(
              student.riskReason!,
              style: GoogleFonts.inter(
                fontSize: 12,
                color: AppColors.text2,
                height: 1.45,
              ),
            ),
          ],
          if (tagLabel != null) ...[
            const SizedBox(height: 10),
            InkWell(
              borderRadius: BorderRadius.circular(8),
              onTap: () => Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => matchingSeminar != null
                      ? SeminarDetailScreen(seminar: matchingSeminar)
                      : MatchingSeminarsScreen(tag: tag!, tagLabel: tagLabel),
                ),
              ),
              child: Row(
                children: [
                  Icon(
                    matchingSeminar != null
                        ? Icons.check_circle_outline_rounded
                        : Icons.school_outlined,
                    size: 14,
                    color: matchingSeminar != null
                        ? AppColors.greenText
                        : AppColors.accent,
                  ),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      matchingSeminar != null
                          ? 'Already assigned: ${matchingSeminar.title}'
                          : 'Recommended intervention: $tagLabel',
                      style: GoogleFonts.inter(
                        fontSize: 12,
                        fontWeight: FontWeight.w500,
                        color: matchingSeminar != null
                            ? AppColors.greenText
                            : AppColors.accentDark,
                      ),
                    ),
                  ),
                  Icon(
                    Icons.chevron_right_rounded,
                    size: 16,
                    color: matchingSeminar != null
                        ? AppColors.greenText
                        : AppColors.accent,
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _SectionLabel extends StatelessWidget {
  final String text;
  final int count;
  const _SectionLabel(this.text, {required this.count});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Text(text, style: AppTextStyles.sectionTitle),
        const SizedBox(width: 8),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 1),
          decoration: BoxDecoration(
            color: AppColors.background,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: AppColors.border),
          ),
          child: Text(
            '$count',
            style: GoogleFonts.inter(
              fontSize: 11,
              fontWeight: FontWeight.w600,
              color: AppColors.text3,
            ),
          ),
        ),
      ],
    );
  }
}

class _TimelineCard extends StatelessWidget {
  final TimelineItem item;
  const _TimelineCard({required this.item});

  String get _dateLabel {
    final raw = item.date;
    if (raw == null || raw.isEmpty) return '';
    try {
      return DateFormat('MMM d, yyyy').format(DateTime.parse(raw));
    } catch (_) {
      return raw;
    }
  }

  @override
  Widget build(BuildContext context) {
    final isReport = item.isReport;
    final icon = isReport ? Icons.receipt_long_rounded : Icons.outbox_rounded;
    final kindLabel = isReport ? 'Behavioral report' : 'Referral';

    return InkWell(
      borderRadius: BorderRadius.circular(12),
      onTap: () => Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => isReport
              ? ReportDetailScreen(reportId: item.id)
              : ReferralDetailScreen(referralId: item.id),
        ),
      ),
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
                Container(
                  width: 30,
                  height: 30,
                  decoration: BoxDecoration(
                    color: AppColors.background,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Icon(icon, size: 16, color: AppColors.text3),
                ),
                const SizedBox(width: 11),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        kindLabel.toUpperCase(),
                        style: GoogleFonts.inter(
                          fontSize: 9.5,
                          letterSpacing: 0.6,
                          fontWeight: FontWeight.w600,
                          color: AppColors.text3,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(item.title, style: AppTextStyles.sectionTitle),
                    ],
                  ),
                ),
                if (_dateLabel.isNotEmpty) ...[
                  Text(_dateLabel, style: AppTextStyles.meta),
                  const SizedBox(width: 4),
                ],
                const Icon(
                  Icons.chevron_right_rounded,
                  size: 18,
                  color: AppColors.text3,
                ),
              ],
            ),
            if (item.detail.isNotEmpty) ...[
              const SizedBox(height: 9),
              Text(
                item.detail,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: GoogleFonts.inter(
                  fontSize: 12,
                  color: AppColors.text2,
                  height: 1.45,
                ),
              ),
            ],
            const SizedBox(height: 11),
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                AppBadge.status(item.status),
                if (isReport && item.severity != null)
                  AppBadge.severity(item.severity!),
                if (isReport && item.escalated) AppBadge.escalated(),
                if (!isReport && item.priority != null)
                  AppBadge.priority(item.priority!),
                if (!isReport && item.auto) AppBadge.auto(),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _SeminarCard extends StatelessWidget {
  final SeminarItem seminar;
  const _SeminarCard({required this.seminar});

  ({String label, Color bg, Color fg}) get _statusVisual {
    switch (seminar.status) {
      case 'attended':
        return (
          label: 'Attended',
          bg: AppColors.greenBg,
          fg: AppColors.greenText,
        );
      case 'missed':
        return (label: 'Missed', bg: AppColors.redBg, fg: AppColors.redText);
      default:
        return (
          label: 'Enrolled',
          bg: AppColors.accentLight,
          fg: AppColors.accentDark,
        );
    }
  }

  @override
  Widget build(BuildContext context) {
    final status = _statusVisual;
    return InkWell(
      borderRadius: BorderRadius.circular(12),
      onTap: () => Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => SeminarDetailScreen(seminar: seminar),
        ),
      ),
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
              children: [
                const Icon(
                  Icons.school_rounded,
                  size: 18,
                  color: AppColors.accent,
                ),
                const SizedBox(width: 11),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(seminar.title, style: AppTextStyles.sectionTitle),
                      if (seminar.date != null && seminar.date!.isNotEmpty) ...[
                        const SizedBox(height: 2),
                        Text(seminar.date!, style: AppTextStyles.meta),
                      ],
                    ],
                  ),
                ),
                AppBadge(label: status.label, bg: status.bg, fg: status.fg),
                const SizedBox(width: 6),
                const Icon(
                  Icons.chevron_right_rounded,
                  size: 18,
                  color: AppColors.text3,
                ),
              ],
            ),
            if (seminar.attendanceNotRecorded) ...[
              const SizedBox(height: 8),
              Row(
                children: [
                  const Icon(
                    Icons.info_outline_rounded,
                    size: 13,
                    color: AppColors.amberText,
                  ),
                  const SizedBox(width: 5),
                  Expanded(
                    child: Text(
                      'Session passed — attendance not recorded',
                      style: GoogleFonts.inter(
                        fontSize: 11,
                        color: AppColors.amberText,
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _EmptyRow extends StatelessWidget {
  final IconData icon;
  final String text;
  const _EmptyRow({required this.icon, required this.text});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 18),
      decoration: BoxDecoration(
        color: AppColors.surface,
        border: Border.all(color: AppColors.border),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Icon(icon, size: 17, color: AppColors.text3),
          const SizedBox(width: 10),
          Text(text, style: AppTextStyles.meta),
        ],
      ),
    );
  }
}
