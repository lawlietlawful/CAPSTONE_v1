import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../core/constants/app_decor.dart';
import '../../core/constants/app_text_styles.dart';
import '../../models/roster_student.dart';
import '../../models/student_detail.dart';
import '../../providers/teacher_provider.dart';
import '../../widgets/common/form_widgets.dart';
import '../../widgets/common/page_header.dart';
import '../../widgets/common/status_badges.dart';
import '../../widgets/common/surfaces.dart';
import '../../widgets/premium/motion.dart';
import '../../widgets/premium/skeleton.dart';
import '../log_incident/log_incident_screen.dart';
import '../referrals/file_referral_screen.dart';
import '../reports/report_detail_screen.dart';
import '../referrals/referral_detail_screen.dart';
import '../seminars/matching_seminars_screen.dart';
import '../seminars/seminar_detail_screen.dart';

/// A single student's full picture: current risk, the merged timeline of every
/// report and referral about them, and their assigned interventions — plus a
/// shortcut to report a concern or file a referral for them.
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

  Future<void> _logIncident() async {
    final provider = context.read<TeacherProvider>();
    await Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => LogIncidentScreen(initialStudentId: widget.studentId),
    ));
    if (!mounted) return;
    provider.loadReports();
    provider.loadRoster();
    _retry();
  }

  Future<void> _fileReferral() async {
    final provider = context.read<TeacherProvider>();
    await Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => FileReferralScreen(initialStudentId: widget.studentId),
    ));
    if (!mounted) return;
    provider.loadReferrals();
    provider.loadRoster();
    _retry();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Student')),
      body: FutureBuilder<StudentDetail>(
        future: _future,
        builder: (context, snap) {
          if (snap.connectionState == ConnectionState.waiting) {
            return const SkeletonList(count: 4, leading: true);
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
    var i = 0; // stagger order
    Widget enter(Widget child) => FadeSlideIn(index: i++, child: child);

    return RefreshIndicator(
      onRefresh: () async {
        _retry();
        await _future;
      },
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 32),
        children: [
          _Header(student: s),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  key: const Key('student-log-incident'),
                  onPressed: _logIncident,
                  icon: const Icon(Icons.edit_note_rounded, size: 20, color: AppColors.amberText),
                  label: const Text('Report concern'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: OutlinedButton.icon(
                  key: const Key('student-file-referral'),
                  onPressed: _fileReferral,
                  icon: const Icon(Icons.outbox_rounded, size: 19, color: AppColors.accent),
                  label: const Text('File referral'),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          enter(_RiskExplainerCard(student: s, seminars: detail.seminars)),
          const SizedBox(height: 14),

          enter(SectionHeading(title: 'Case history', count: detail.timeline.length)),
          if (detail.timeline.isEmpty)
            enter(const _EmptyRow(
              icon: Icons.inbox_outlined,
              text: 'No reports or referrals on file.',
            ))
          else
            enter(CardGroup(
              children: [
                for (final t in detail.timeline) _TimelineRow(item: t),
              ],
            )),

          const SizedBox(height: 14),
          enter(SectionHeading(title: 'Interventions', count: detail.seminars.length)),
          if (detail.seminars.isEmpty)
            enter(const _EmptyRow(
              icon: Icons.school_outlined,
              text: 'No seminars assigned yet.',
            ))
          else
            enter(CardGroup(
              children: [for (final sm in detail.seminars) _SeminarRow(seminar: sm)],
            )),
        ],
      ),
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
    return Row(
      children: [
        // Glides in from the same avatar on the My Students row.
        Hero(
          tag: 'student-avatar-${student.id}',
          child: InitialsAvatar(initials: InitialsAvatar.of(student.name), size: 56),
        ),
        const SizedBox(width: 14),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(student.name, style: AppTextStyles.pageTitle),
              if (meta.isNotEmpty) ...[
                const SizedBox(height: 2),
                Text(meta, style: AppTextStyles.meta.copyWith(fontSize: 13, color: AppColors.text2)),
              ],
            ],
          ),
        ),
      ],
    );
  }
}

/// Explains *why* the risk tag looks the way it does: a three-part scale
/// with the student's position, the plain-language reason from the last
/// assessment, and a recommended seminar if one hasn't been assigned yet.
/// The ML engine only returns a score + label (no per-feature breakdown), so
/// this surfaces the richest context actually available.
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

  /// Where the marker sits on the 0–1 scale. The score is kept inside its
  /// level's third, so the marker never contradicts the label.
  double get _position {
    final score = (student.riskScore ?? 50).clamp(0, 100) / 100;
    return switch (student.riskLevel) {
      'high' => score.clamp(0.70, 0.97),
      'moderate' => score.clamp(0.37, 0.63),
      _ => score.clamp(0.03, 0.30),
    };
  }

  @override
  Widget build(BuildContext context) {
    if (student.riskLevel == 'not_assessed') {
      return Container(
        padding: const EdgeInsets.all(16),
        decoration: AppDecor.card(),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(child: Text('Current risk level', style: AppTextStyles.sectionTitle)),
                AppBadge.risk(student.riskLevel),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              'No risk assessment yet. This appears once the student has a '
              'behavioral report or referral on file.',
              style: AppTextStyles.bodySecondary,
            ),
          ],
        ),
      );
    }

    final tag = student.recommendedSeminarTag;
    final tagLabel = tag == null ? null : (_tagLabels[tag] ?? tag);
    final matchingSeminar = tag == null
        ? null
        : seminars.cast<SeminarItem?>().firstWhere(
            (s) => s?.triggerReason == tag,
            orElse: () => null,
          );
    final levelWord = switch (student.riskLevel) {
      'high' => 'High',
      'moderate' => 'Moderate',
      _ => 'Low',
    };

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: AppDecor.card(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(child: Text('Current risk level', style: AppTextStyles.sectionTitle)),
              AppBadge.risk(student.riskLevel),
            ],
          ),
          const SizedBox(height: 18),
          Semantics(
            label: 'Risk scale: $levelWord',
            child: ExcludeSemantics(child: _RiskScale(position: _position)),
          ),
          if (student.riskReason != null && student.riskReason!.isNotEmpty) ...[
            const SizedBox(height: 16),
            Text('Why this level', style: GoogleFonts.publicSans(fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.text1)),
            const SizedBox(height: 4),
            Text(student.riskReason!, style: AppTextStyles.bodySecondary),
          ],
          if (tagLabel != null) ...[
            const SizedBox(height: 14),
            Material(
              color: matchingSeminar != null ? AppColors.greenBg : AppColors.background,
              borderRadius: BorderRadius.circular(10),
              child: InkWell(
                borderRadius: BorderRadius.circular(10),
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => matchingSeminar != null
                        ? SeminarDetailScreen(seminar: matchingSeminar)
                        : MatchingSeminarsScreen(tag: tag!, tagLabel: tagLabel),
                  ),
                ),
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(12, 12, 8, 12),
                  child: Row(
                    children: [
                      Icon(
                        matchingSeminar != null ? Icons.event_available_rounded : Icons.school_outlined,
                        size: 18,
                        color: matchingSeminar != null ? AppColors.greenText : AppColors.accent,
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text.rich(
                          TextSpan(children: [
                            TextSpan(
                              text: matchingSeminar != null ? 'Already assigned' : 'Recommended',
                              style: const TextStyle(fontWeight: FontWeight.w600),
                            ),
                            TextSpan(
                              text: matchingSeminar != null ? ' · ${matchingSeminar.title}' : ' · $tagLabel',
                            ),
                          ]),
                          style: GoogleFonts.publicSans(
                            fontSize: 13,
                            height: 1.4,
                            color: matchingSeminar != null ? AppColors.greenText : AppColors.text1,
                          ),
                        ),
                      ),
                      Icon(
                        Icons.chevron_right_rounded,
                        size: 20,
                        color: matchingSeminar != null ? AppColors.greenText : AppColors.iconMuted,
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

/// Low / Moderate / High in three segments, with a ring marking the student.
class _RiskScale extends StatelessWidget {
  final double position; // 0–1
  const _RiskScale({required this.position});

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final w = constraints.maxWidth;
        return Column(
          children: [
            SizedBox(
              height: 16,
              child: Stack(
                clipBehavior: Clip.none,
                children: [
                  const Positioned(
                    left: 0,
                    right: 0,
                    top: 5,
                    child: Row(
                      children: [
                        Expanded(child: _Segment(color: Color(0xFFBFE3D2))),
                        SizedBox(width: 3),
                        Expanded(child: _Segment(color: Color(0xFFF8DDB0))),
                        SizedBox(width: 3),
                        Expanded(child: _Segment(color: Color(0xFFF2B8AE))),
                      ],
                    ),
                  ),
                  TweenAnimationBuilder<double>(
                    tween: Tween(begin: Motion.reduced(context) ? position : 0, end: position),
                    duration: const Duration(milliseconds: 700),
                    curve: Motion.enter,
                    builder: (_, t, _) => Positioned(
                      left: (w * t - 8).clamp(0, w - 16),
                      top: 0,
                      child: Container(
                        width: 16,
                        height: 16,
                        decoration: BoxDecoration(
                          color: Colors.white,
                          shape: BoxShape.circle,
                          border: Border.all(color: AppColors.text1, width: 3),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(child: Text('Low', style: AppTextStyles.meta.copyWith(fontSize: 12))),
                Expanded(child: Text('Moderate', textAlign: TextAlign.center, style: AppTextStyles.meta.copyWith(fontSize: 12))),
                Expanded(child: Text('High', textAlign: TextAlign.right, style: AppTextStyles.meta.copyWith(fontSize: 12))),
              ],
            ),
          ],
        );
      },
    );
  }
}

class _Segment extends StatelessWidget {
  final Color color;
  const _Segment({required this.color});

  @override
  Widget build(BuildContext context) => Container(
        height: 6,
        decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(3)),
      );
}

class _TimelineRow extends StatelessWidget {
  final TimelineItem item;

  const _TimelineRow({required this.item});

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
    final kindLabel = isReport ? 'Report' : 'Referral';
    final dotColor = !item.mine
        ? AppColors.neutralDot
        : isReport
            ? AppColors.amber
            : AppColors.accent;
    final meta = [
      if (_dateLabel.isNotEmpty) _dateLabel,
      if (item.mine) 'Filed by you',
    ].join(' · ');

    // Only the teacher's own entries open: someone else's stays a summary.
    final content = Padding(
      padding: const EdgeInsets.fromLTRB(14, 12, 10, 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.only(top: 6),
            child: Container(
              width: 10,
              height: 10,
              decoration: BoxDecoration(color: dotColor, shape: BoxShape.circle),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.mine ? '$kindLabel · ${item.title}' : kindLabel,
                  style: AppTextStyles.sectionTitle,
                ),
                const SizedBox(height: 1),
                Text(meta, style: AppTextStyles.meta),
                if (!item.mine) ...[
                  const SizedBox(height: 6),
                  Row(
                    children: [
                      const Icon(Icons.lock_outline_rounded, size: 14, color: AppColors.text2),
                      const SizedBox(width: 6),
                      Expanded(
                        child: Text(
                          'Filed by another staff member. Details stay with Guidance.',
                          style: GoogleFonts.publicSans(fontSize: 12.5, color: AppColors.text2),
                        ),
                      ),
                    ],
                  ),
                ],
                if (item.mine && item.detail.isNotEmpty) ...[
                  const SizedBox(height: 6),
                  Text(
                    item.detail,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: AppTextStyles.bodySecondary.copyWith(fontSize: 13),
                  ),
                ],
                const SizedBox(height: 8),
                Wrap(
                  spacing: 6,
                  runSpacing: 6,
                  children: [
                    AppBadge.status(item.status),
                    if (isReport && item.severity != null) AppBadge.severity(item.severity!),
                    if (isReport && item.escalated) AppBadge.escalated(),
                    if (!isReport && item.priority != null) AppBadge.priority(item.priority!),
                    if (!isReport && item.auto) AppBadge.auto(),
                  ],
                ),
              ],
            ),
          ),
          if (item.mine)
            const Padding(
              padding: EdgeInsets.only(top: 2),
              child: Icon(Icons.chevron_right_rounded, size: 20, color: AppColors.iconMuted),
            ),
        ],
      ),
    );

    if (!item.mine) return content;
    return InkWell(
      onTap: () => Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => isReport
              ? ReportDetailScreen(reportId: item.id)
              : ReferralDetailScreen(referralId: item.id),
        ),
      ),
      child: content,
    );
  }
}

class _SeminarRow extends StatelessWidget {
  final SeminarItem seminar;
  const _SeminarRow({required this.seminar});

  AppBadge get _status {
    switch (seminar.status) {
      case 'attended':
        return const AppBadge(label: 'Attended', bg: AppColors.greenBg, fg: AppColors.greenText, dot: AppColors.green);
      case 'missed':
        return const AppBadge(label: 'Missed', bg: AppColors.redBg, fg: AppColors.redText, dot: AppColors.red);
      default:
        return const AppBadge(label: 'Enrolled', bg: AppColors.accentLight, fg: AppColors.accentDark, dot: AppColors.accent);
    }
  }

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: () => Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => SeminarDetailScreen(seminar: seminar)),
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 12, 8, 12),
        child: Row(
          children: [
            Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(color: AppColors.accentLight, borderRadius: BorderRadius.circular(10)),
              child: const Icon(Icons.school_outlined, size: 19, color: AppColors.accent),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(seminar.title, style: AppTextStyles.sectionTitle),
                  if (seminar.date != null && seminar.date!.isNotEmpty) ...[
                    const SizedBox(height: 1),
                    Text(_formatDate(seminar.date!), style: AppTextStyles.meta),
                  ],
                  if (seminar.attendanceNotRecorded) ...[
                    const SizedBox(height: 4),
                    Text(
                      'Session passed — attendance not recorded',
                      style: GoogleFonts.publicSans(fontSize: 12, color: AppColors.amberText),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(width: 8),
            _status,
            const Icon(Icons.chevron_right_rounded, size: 20, color: AppColors.iconMuted),
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
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 18),
      decoration: AppDecor.card(),
      child: Row(
        children: [
          Icon(icon, size: 18, color: AppColors.text3),
          const SizedBox(width: 10),
          Expanded(child: Text(text, style: AppTextStyles.meta.copyWith(fontSize: 13.5))),
        ],
      ),
    );
  }
}

/// "2026-10-17" → "Oct 17, 2026"; anything unparseable is shown as-is.
String _formatDate(String raw) {
  try {
    return DateFormat('MMM d, yyyy').format(DateTime.parse(raw));
  } catch (_) {
    return raw;
  }
}
