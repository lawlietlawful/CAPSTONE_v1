import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../core/constants/app_text_styles.dart';
import '../../models/referral_detail.dart';
import '../../providers/teacher_provider.dart';
import '../messaging/compose_message_screen.dart';
import '../../widgets/common/follow_up_card.dart';
import '../../widgets/common/form_widgets.dart';
import '../../widgets/common/status_badges.dart';

/// A referral's outcome: its status journey and who has it at Guidance, with
/// a way to message them. Closes the loop for the teacher who filed it.
class ReferralDetailScreen extends StatefulWidget {
  final int referralId;
  const ReferralDetailScreen({super.key, required this.referralId});

  @override
  State<ReferralDetailScreen> createState() => _ReferralDetailScreenState();
}

class _ReferralDetailScreenState extends State<ReferralDetailScreen> {
  late Future<ReferralDetail> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<ReferralDetail> _load() =>
      context.read<TeacherProvider>().fetchReferralDetail(widget.referralId);

  void _retry() => setState(() => _future = _load());

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text(
          'Referral',
          style: GoogleFonts.inter(fontSize: 16, fontWeight: FontWeight.w500),
        ),
      ),
      body: FutureBuilder<ReferralDetail>(
        future: _future,
        builder: (context, snap) {
          if (snap.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snap.hasError || !snap.hasData) {
            return CenteredMessage(
              icon: Icons.wifi_off_rounded,
              title: 'Could not load referral',
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

  Widget _buildDetail(ReferralDetail detail) {
    final r = detail.referral;
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
      children: [
        // ── Header ──
        Container(
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            color: AppColors.surface,
            border: Border.all(color: AppColors.border),
            borderRadius: BorderRadius.circular(14),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                r.studentName ?? 'Unknown student',
                style: AppTextStyles.pageTitle,
              ),
              const SizedBox(height: 4),
              Text(r.referralTypeLabel, style: AppTextStyles.meta),
              const SizedBox(height: 12),
              Wrap(
                spacing: 6,
                runSpacing: 6,
                children: [
                  AppBadge.status(r.status),
                  AppBadge.priority(r.priority),
                  if (r.isAutoEscalated) AppBadge.auto(),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 22),

        // ── Status journey ──
        Text('Status', style: AppTextStyles.sectionTitle),
        const SizedBox(height: 12),
        _Journey(steps: detail.journey),
        const SizedBox(height: 22),

        // ── Who has it at Guidance ──
        Text('Handled by', style: AppTextStyles.sectionTitle),
        const SizedBox(height: 10),
        _HandledByCard(
          counselorId: r.counselorId,
          counselorName: r.counselorName,
          referralLabel: r.referralTypeLabel,
          studentName: r.studentName,
        ),
        const SizedBox(height: 22),

        // ── Teacher's follow-up, once Guidance has resolved it ──
        if (r.isResolved) ...[
          Text('Your follow-up', style: AppTextStyles.sectionTitle),
          const SizedBox(height: 10),
          FollowUpCard(
            referralId: r.id,
            studentName: r.studentName,
            followUp: r.followUp,
            onSaved: () => setState(() => _future = _load()),
          ),
          const SizedBox(height: 22),
        ],

        // ── Reason (what the teacher wrote) ──
        Text('Reason', style: AppTextStyles.sectionTitle),
        const SizedBox(height: 10),
        if (r.isAutoEscalated && r.escalatedFromReportId != null) ...[
          Text(
            'Auto-escalated from Behavioral Report #${r.escalatedFromReportId}',
            style: GoogleFonts.inter(
              fontSize: 11.5,
              color: AppColors.purpleText,
            ),
          ),
          const SizedBox(height: 8),
        ],
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: AppColors.surface,
            border: Border.all(color: AppColors.border),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Text(
            r.reason,
            style: GoogleFonts.inter(
              fontSize: 13,
              color: AppColors.text2,
              height: 1.5,
            ),
          ),
        ),
      ],
    );
  }
}

class _Journey extends StatelessWidget {
  final List<JourneyStep> steps;
  const _Journey({required this.steps});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 4, 16, 4),
      decoration: BoxDecoration(
        color: AppColors.surface,
        border: Border.all(color: AppColors.border),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Column(
        children: [
          for (var i = 0; i < steps.length; i++)
            _JourneyRow(step: steps[i], isLast: i == steps.length - 1),
        ],
      ),
    );
  }
}

class _JourneyRow extends StatelessWidget {
  final JourneyStep step;
  final bool isLast;
  const _JourneyRow({required this.step, required this.isLast});

  String get _dateLabel {
    final raw = step.date;
    if (raw == null || raw.isEmpty) return '';
    try {
      return DateFormat('MMM d, yyyy').format(DateTime.parse(raw).toLocal());
    } catch (_) {
      return raw;
    }
  }

  @override
  Widget build(BuildContext context) {
    final cancelled = step.isCancelled;
    final reached = step.isDone || step.isCurrent;

    // Cancelled uses red; reached steps use accent; upcoming are muted.
    final Color markColor = cancelled
        ? AppColors.red
        : (reached ? AppColors.accent : AppColors.border);
    final Color textColor = reached || cancelled
        ? AppColors.text1
        : AppColors.text3;

    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Marker + connector column
          Column(
            children: [
              Container(
                width: 22,
                height: 22,
                margin: const EdgeInsets.only(top: 12),
                decoration: BoxDecoration(
                  color: reached || cancelled ? markColor : AppColors.surface,
                  shape: BoxShape.circle,
                  border: Border.all(color: markColor, width: 2),
                ),
                child: step.isDone
                    ? const Icon(
                        Icons.check_rounded,
                        size: 13,
                        color: Colors.white,
                      )
                    : cancelled
                    ? const Icon(
                        Icons.close_rounded,
                        size: 13,
                        color: Colors.white,
                      )
                    : step.isCurrent
                    ? Center(
                        child: Container(
                          width: 7,
                          height: 7,
                          decoration: const BoxDecoration(
                            color: Colors.white,
                            shape: BoxShape.circle,
                          ),
                        ),
                      )
                    : null,
              ),
              if (!isLast)
                Expanded(
                  child: Container(
                    width: 2,
                    color: step.isDone ? AppColors.accent : AppColors.border,
                  ),
                ),
            ],
          ),
          const SizedBox(width: 14),
          // Label + date
          Expanded(
            child: Padding(
              padding: EdgeInsets.only(top: 12, bottom: isLast ? 12 : 18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    step.label,
                    style: GoogleFonts.inter(
                      fontSize: 13.5,
                      fontWeight: step.isCurrent
                          ? FontWeight.w600
                          : FontWeight.w500,
                      color: textColor,
                    ),
                  ),
                  if (_dateLabel.isNotEmpty) ...[
                    const SizedBox(height: 2),
                    Text(_dateLabel, style: AppTextStyles.meta),
                  ] else if (step.isCurrent && !cancelled) ...[
                    const SizedBox(height: 2),
                    Text('In progress', style: AppTextStyles.meta),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Who at Guidance has the referral. Their case notes stay with Guidance, so
/// instead of notes the teacher gets a direct line to the counselor.
class _HandledByCard extends StatelessWidget {
  final int? counselorId;
  final String? counselorName;
  final String referralLabel;
  final String? studentName;

  const _HandledByCard({
    required this.counselorId,
    required this.counselorName,
    required this.referralLabel,
    required this.studentName,
  });

  @override
  Widget build(BuildContext context) {
    final assigned = counselorId != null;
    return Container(
      width: double.infinity,
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
              Container(
                width: 34,
                height: 34,
                decoration: BoxDecoration(
                  color: assigned ? AppColors.accentLight : AppColors.background,
                  borderRadius: BorderRadius.circular(9),
                ),
                child: Icon(
                  assigned ? Icons.support_agent_rounded : Icons.hourglass_empty_rounded,
                  size: 17,
                  color: assigned ? AppColors.accent : AppColors.text3,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      assigned ? (counselorName ?? 'Guidance counselor') : 'Waiting for a counselor',
                      style: GoogleFonts.inter(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                        color: AppColors.text1,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      assigned ? 'Guidance counselor' : 'The Guidance Office will assign one soon.',
                      style: AppTextStyles.meta,
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Icon(Icons.lock_outline_rounded, size: 13, color: AppColors.text3),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  'Counseling notes stay confidential with the Guidance Office.',
                  style: GoogleFonts.inter(fontSize: 11.5, color: AppColors.text3, height: 1.4),
                ),
              ),
            ],
          ),
          if (assigned) ...[
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                key: const Key('message-counselor'),
                onPressed: () => Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => ComposeMessageScreen(
                      initialCounselorId: counselorId,
                      initialSubject: 'About my referral: ${studentName ?? 'student'} ($referralLabel)',
                    ),
                  ),
                ),
                icon: const Icon(Icons.chat_bubble_outline_rounded, size: 16),
                label: Text(
                  'Message ${counselorName ?? 'counselor'}',
                  style: GoogleFonts.inter(fontSize: 13, fontWeight: FontWeight.w500),
                ),
                style: OutlinedButton.styleFrom(
                  foregroundColor: AppColors.accent,
                  side: const BorderSide(color: AppColors.border),
                  padding: const EdgeInsets.symmetric(vertical: 11),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
