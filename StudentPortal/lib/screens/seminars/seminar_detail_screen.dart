import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../core/constants/app_colors.dart';
import '../../core/constants/app_decor.dart';
import '../../core/constants/app_text_styles.dart';
import '../../core/utils/date_utils.dart';
import '../../models/seminar_model.dart';
import '../../widgets/common/surfaces.dart';
import '../../widgets/seminars/seminar_card.dart';

/// Everything about one seminar: when, where, who's speaking, the
/// student's status, and what it's about.
class SeminarDetailScreen extends StatelessWidget {
  final SeminarModel seminar;

  const SeminarDetailScreen({super.key, required this.seminar});

  @override
  Widget build(BuildContext context) {
    final date = DateTime.tryParse(seminar.date);
    final when = _isUpcoming ? seminarWhenLabel(date) : null;
    final soon = when == 'Today' || when == 'Tomorrow';

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Seminar')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 32),
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 4),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Wrap(
                  spacing: 8,
                  runSpacing: 6,
                  children: [
                    SeminarTag(
                      label: seminar.isRequired ? 'Required' : 'Optional',
                      bg: seminar.isRequired ? AppColors.accentLight : AppColors.neutralBg,
                      fg: seminar.isRequired ? AppColors.accentDark : AppColors.text2,
                    ),
                    if (when != null)
                      SeminarTag(
                        label: when,
                        bg: soon ? AppColors.amberBg : AppColors.neutralBg,
                        fg: soon ? AppColors.amberText : AppColors.text2,
                      ),
                    if (!_isUpcoming) outcomeTag(seminar.outcome),
                  ],
                ),
                const SizedBox(height: 12),
                Text(
                  seminar.title,
                  style: AppTextStyles.screenTitle,
                ),
                const SizedBox(height: 4),
                Text(assignedByLabel(seminar), style: AppTextStyles.meta.copyWith(fontSize: 13, color: AppColors.text2)),
              ],
            ),
          ),
          const SizedBox(height: 18),

          CardGroup(
            children: [
              _DetailRow(
                icon: Icons.calendar_today_outlined,
                label: 'Date',
                value: AppDateUtils.formatFullDate(seminar.date),
              ),
              _DetailRow(
                icon: Icons.access_time_rounded,
                label: 'Time',
                value: AppDateUtils.formatTime(seminar.time),
              ),
              _DetailRow(
                icon: Icons.location_on_outlined,
                label: 'Venue',
                value: seminar.venue.isEmpty ? '—' : seminar.venue,
              ),
              if (seminar.speaker != null && seminar.speaker!.isNotEmpty)
                _DetailRow(
                  icon: Icons.person_outline_rounded,
                  label: 'Speaker',
                  value: seminar.speaker!,
                ),
              _DetailRow(
                icon: Icons.check_circle_outline_rounded,
                label: 'Status',
                value: _statusLabel(_displayStatus),
              ),
            ],
          ),

          if (seminar.description != null && seminar.description!.isNotEmpty) ...[
            const SizedBox(height: 12),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(16),
              decoration: AppDecor.card(),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('About this seminar', style: AppTextStyles.sectionTitle),
                  const SizedBox(height: 6),
                  Text(
                    seminar.description!,
                    style: GoogleFonts.publicSans(fontSize: 14, height: 1.55, color: const Color(0xFF2B3648)),
                  ),
                ],
              ),
            ),
          ],

          if (_isUpcoming) ...[
            const SizedBox(height: 12),
            const InfoNote(
              text: "Can't make it? Let the Guidance Office know before the "
                  'session so it can be marked excused.',
              background: AppColors.segmentTrack,
              foreground: Color(0xFF2B3648),
            ),
          ],
        ],
      ),
    );
  }

  /// Still to come for this student (not attended, missed, excused or
  /// cancelled).
  bool get _isUpcoming => seminar.status == 'enrolled' && seminar.sessionStatus != 'cancelled' && seminar.sessionStatus != 'completed';

  /// A cancelled session reads "Cancelled" whatever the student's own status.
  String get _displayStatus =>
      seminar.sessionStatus == 'cancelled' ? 'cancelled' : seminar.status;

  String _statusLabel(String status) {
    switch (status) {
      case 'attended':
        return 'Attended';
      case 'missed':
        return 'Missed';
      case 'excused':
        return 'Excused';
      case 'cancelled':
        return 'Cancelled';
      default:
        return 'Enrolled';
    }
  }
}

class _DetailRow extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;

  const _DetailRow({
    required this.icon,
    required this.label,
    required this.value,
  });

  @override
  Widget build(BuildContext context) {
    return MergeSemantics(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 13),
        child: Row(
          children: [
            Icon(icon, size: 18, color: AppColors.text2),
            const SizedBox(width: 12),
            SizedBox(
              width: 72,
              child: Text(label, style: AppTextStyles.meta.copyWith(fontSize: 13.5)),
            ),
            Expanded(
              child: Text(
                value,
                style: GoogleFonts.publicSans(fontSize: 14, fontWeight: FontWeight.w600, color: AppColors.text1),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
