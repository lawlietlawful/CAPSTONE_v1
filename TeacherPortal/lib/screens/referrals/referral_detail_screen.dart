import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../core/constants/app_decor.dart';
import '../../core/constants/app_text_styles.dart';
import '../../models/referral_detail.dart';
import '../../providers/teacher_provider.dart';
import '../messaging/compose_message_screen.dart';
import '../../widgets/common/follow_up_card.dart';
import '../../widgets/common/form_widgets.dart';
import '../../widgets/common/page_header.dart';
import '../../widgets/common/status_badges.dart';
import '../../widgets/common/surfaces.dart';
import '../../widgets/premium/motion.dart';
import '../../widgets/premium/skeleton.dart';

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
        title: const Text('Referral'),
      ),
      body: FutureBuilder<ReferralDetail>(
        future: _future,
        builder: (context, snap) {
          if (snap.connectionState == ConnectionState.waiting) {
            return const SkeletonList(count: 4);
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
    final filed = _formatDate(r.createdAt);
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 4, 16, 32),
      children: [
        // ── Header ──
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 4),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Text(
                      r.studentName ?? 'Unknown student',
                      style: AppTextStyles.pageTitle,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Padding(
                    padding: const EdgeInsets.only(top: 2),
                    child: AppBadge.status(r.status),
                  ),
                ],
              ),
              const SizedBox(height: 2),
              Text(
                [r.referralTypeLabel, if (filed.isNotEmpty) 'Filed $filed'].join(' · '),
                style: AppTextStyles.meta.copyWith(fontSize: 13, color: AppColors.text2),
              ),
              if (r.isAutoEscalated) ...[
                const SizedBox(height: 10),
                AppBadge.auto(),
              ],
            ],
          ),
        ),
        const SizedBox(height: 16),

        // ── Status journey ──
        _Panel(title: 'Status', child: _Journey(steps: detail.journey)),
        const SizedBox(height: 12),

        // ── Who has it at Guidance ──
        _Panel(
          title: 'Handled by',
          child: _HandledByCard(
            counselorId: r.counselorId,
            counselorName: r.counselorName,
            referralLabel: r.referralTypeLabel,
            studentName: r.studentName,
          ),
        ),
        const SizedBox(height: 12),

        // ── Teacher's follow-up, once Guidance has resolved it ──
        if (r.isResolved) ...[
          const SectionHeading(title: 'Your follow-up'),
          FollowUpCard(
            referralId: r.id,
            studentName: r.studentName,
            followUp: r.followUp,
            onSaved: () => setState(() => _future = _load()),
          ),
          const SizedBox(height: 12),
        ],

        // ── Reason (what the teacher wrote) ──
        _Panel(
          title: r.isAutoEscalated ? 'Reason' : 'Your reason',
          trailing: Text.rich(
            TextSpan(children: [
              const TextSpan(text: 'Priority '),
              TextSpan(
                text: _cap(r.priority.isEmpty ? 'low' : r.priority),
                style: TextStyle(fontWeight: FontWeight.w600, color: _priorityColor(r.priority)),
              ),
            ]),
            style: AppTextStyles.meta,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (r.isAutoEscalated && r.escalatedFromReportId != null) ...[
                Text(
                  'Auto-escalated from Behavioral Report #${r.escalatedFromReportId}',
                  style: GoogleFonts.publicSans(fontSize: 12.5, color: AppColors.purpleText),
                ),
                const SizedBox(height: 6),
              ],
              Text(r.reason, style: AppTextStyles.body.copyWith(color: const Color(0xFF2B3648))),
              const SizedBox(height: 8),
              Text('Priority is set by the system from the student\'s risk level.', style: AppTextStyles.meta),
            ],
          ),
        ),
      ],
    );
  }

  static String _cap(String s) => s.isEmpty ? s : '${s[0].toUpperCase()}${s.substring(1)}';

  static Color _priorityColor(String p) => switch (p.toLowerCase()) {
        'high' => AppColors.redText,
        'moderate' || 'medium' => AppColors.amberText,
        _ => AppColors.greenText,
      };

  static String _formatDate(String? raw) {
    if (raw == null || raw.isEmpty) return '';
    try {
      return DateFormat('MMM d, yyyy').format(DateTime.parse(raw).toLocal());
    } catch (_) {
      return raw;
    }
  }
}

/// A white card with a small title on top.
class _Panel extends StatelessWidget {
  final String title;
  final Widget? trailing;
  final Widget child;

  const _Panel({required this.title, required this.child, this.trailing});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
      decoration: AppDecor.card(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(child: Text(title, style: AppTextStyles.sectionTitle)),
              ?trailing,
            ],
          ),
          const SizedBox(height: 8),
          child,
        ],
      ),
    );
  }
}

class _Journey extends StatelessWidget {
  final List<JourneyStep> steps;
  const _Journey({required this.steps});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.zero,
      child: Column(
        children: [
          for (var i = 0; i < steps.length; i++)
            FadeSlideIn(
              index: i + 1,
              offset: 8,
              child: _JourneyRow(step: steps[i], isLast: i == steps.length - 1),
            ),
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
              Padding(
                padding: const EdgeInsets.only(top: 10),
                child: Container(
                    width: 22,
                    height: 22,
                    decoration: BoxDecoration(
                      color: step.isDone || cancelled
                          ? markColor
                          : AppColors.surface,
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
                              width: 8,
                              height: 8,
                              decoration: const BoxDecoration(
                                color: AppColors.accent,
                                shape: BoxShape.circle,
                              ),
                            ),
                          )
                        : null,
                ),
              ),
              if (!isLast)
                Expanded(
                  child: Container(
                    width: 2,
                    color: AppColors.border,
                    alignment: Alignment.topCenter,
                    // A finished step's line fills downward toward the next one.
                    child: step.isDone
                        ? TweenAnimationBuilder<double>(
                            tween: Tween(
                              begin: Motion.reduced(context) ? 1 : 0,
                              end: 1,
                            ),
                            duration: const Duration(milliseconds: 600),
                            curve: Motion.enter,
                            builder: (_, t, _) => FractionallySizedBox(
                              heightFactor: t,
                              child: Container(color: AppColors.accent),
                            ),
                          )
                        : null,
                  ),
                ),
            ],
          ),
          const SizedBox(width: 14),
          // Label + date
          Expanded(
            child: Padding(
              padding: EdgeInsets.only(top: 10, bottom: isLast ? 2 : 16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    step.label,
                    style: GoogleFonts.publicSans(
                      fontSize: 14,
                      fontWeight: step.isCurrent
                          ? FontWeight.w600
                          : FontWeight.w500,
                      color: step.isCurrent && !cancelled ? AppColors.accentDark : textColor,
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
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            assigned
                ? InitialsAvatar(initials: InitialsAvatar.of(counselorName), size: 40, dark: true)
                : Container(
                    width: 40,
                    height: 40,
                    decoration: const BoxDecoration(color: AppColors.background, shape: BoxShape.circle),
                    child: const Icon(Icons.hourglass_empty_rounded, size: 18, color: AppColors.text3),
                  ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    assigned ? (counselorName ?? 'Guidance counselor') : 'Waiting for a counselor',
                    style: AppTextStyles.sectionTitle,
                  ),
                  const SizedBox(height: 1),
                  Text(
                    assigned ? 'Guidance counselor' : 'The Guidance Office will assign one soon.',
                    style: AppTextStyles.meta,
                  ),
                ],
              ),
            ),
            if (assigned) ...[
              const SizedBox(width: 8),
              OutlinedButton.icon(
                key: const Key('message-counselor'),
                onPressed: () => Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => ComposeMessageScreen(
                      initialCounselorId: counselorId,
                      initialSubject:
                          'About my referral: ${studentName ?? 'student'} ($referralLabel)',
                    ),
                  ),
                ),
                style: OutlinedButton.styleFrom(padding: const EdgeInsets.symmetric(horizontal: 12)),
                icon: const Icon(Icons.chat_bubble_outline_rounded, size: 16),
                label: Text(
                  'Message',
                  semanticsLabel: 'Message ${counselorName ?? 'counselor'}',
                ),
              ),
            ],
          ],
        ),
        const SizedBox(height: 12),
        const Divider(height: 1),
        const SizedBox(height: 12),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Icon(Icons.lock_outline_rounded, size: 15, color: AppColors.text2),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                'Counseling notes stay confidential with the Guidance Office.',
                style: GoogleFonts.publicSans(fontSize: 12.5, color: AppColors.text2, height: 1.4),
              ),
            ),
          ],
        ),
      ],
    );
  }
}
