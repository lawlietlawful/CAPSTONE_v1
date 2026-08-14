import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';

import '../../core/constants/app_colors.dart';
import '../../core/constants/app_text_styles.dart';
import '../../models/student_detail.dart';

/// Full detail of a single seminar — either one the student is already
/// enrolled in, or a candidate upcoming session for a recommended tag.
/// The seminar is passed in directly (from the student detail payload or the
/// matching-seminars lookup); there is no separate fetch-by-id call.
class SeminarDetailScreen extends StatelessWidget {
  final SeminarItem seminar;
  const SeminarDetailScreen({super.key, required this.seminar});

  String get _dateLabel {
    final raw = seminar.date;
    if (raw == null || raw.isEmpty) return 'Not scheduled';
    try {
      return DateFormat('MMM d, yyyy').format(DateTime.parse(raw));
    } catch (_) {
      return raw;
    }
  }

  /// The backend sends a raw "HH:mm:ss" string; render it as "1:00 PM".
  String? get _timeLabel {
    final raw = seminar.time;
    if (raw == null || raw.isEmpty) return null;
    try {
      return DateFormat('h:mm a').format(DateFormat('HH:mm:ss').parse(raw));
    } catch (_) {
      return raw;
    }
  }

  /// The seminar's own lifecycle chip — only shown when it's assigned and the
  /// session's own status is worth calling out (completed/cancelled), since an
  /// enrolled student's "Enrolled" chip alone doesn't say whether the session
  /// has actually happened yet.
  ({String label, Color bg, Color fg})? get _sessionVisual {
    if (!seminar.isAssigned) return null;
    switch (seminar.sessionStatus) {
      case 'completed':
        return (
          label: 'Session completed',
          bg: AppColors.greenBg,
          fg: AppColors.greenText,
        );
      case 'cancelled':
        return (
          label: 'Session cancelled',
          bg: AppColors.redBg,
          fg: AppColors.redText,
        );
      default:
        return null;
    }
  }

  ({String label, Color bg, Color fg}) get _statusVisual {
    final s = seminar.status.toLowerCase();
    if (seminar.isAssigned) {
      switch (s) {
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
    switch (s) {
      case 'ongoing':
        return (
          label: 'Ongoing',
          bg: AppColors.amberBg,
          fg: AppColors.amberText,
        );
      case 'completed':
        return (
          label: 'Completed',
          bg: AppColors.greenBg,
          fg: AppColors.greenText,
        );
      case 'cancelled':
        return (label: 'Cancelled', bg: AppColors.redBg, fg: AppColors.redText);
      default:
        return (
          label: 'Upcoming',
          bg: AppColors.accentLight,
          fg: AppColors.accentDark,
        );
    }
  }

  @override
  Widget build(BuildContext context) {
    final status = _statusVisual;
    final session = _sessionVisual;
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text(
          'Seminar',
          style: GoogleFonts.inter(fontSize: 16, fontWeight: FontWeight.w500),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: AppColors.surface,
              border: Border.all(color: AppColors.border),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Wrap(
                  spacing: 6,
                  runSpacing: 6,
                  children: [
                    if (seminar.isRequired)
                      _Chip(
                        label: 'Required',
                        bg: AppColors.redBg,
                        fg: AppColors.redText,
                      ),
                    _Chip(label: status.label, bg: status.bg, fg: status.fg),
                    if (session != null)
                      _Chip(
                        label: session.label,
                        bg: session.bg,
                        fg: session.fg,
                      ),
                    if (!seminar.isAssigned)
                      _Chip(
                        label: 'Not yet enrolled',
                        bg: const Color(0xFFF1F5F9),
                        fg: AppColors.text2,
                      ),
                  ],
                ),
                const SizedBox(height: 10),
                Text(
                  seminar.title,
                  style: GoogleFonts.inter(
                    fontSize: 17,
                    fontWeight: FontWeight.w600,
                    color: AppColors.text1,
                    height: 1.4,
                  ),
                ),
                if (seminar.attendanceNotRecorded) ...[
                  const SizedBox(height: 10),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 8,
                    ),
                    decoration: BoxDecoration(
                      color: AppColors.amberBg,
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Icon(
                          Icons.info_outline_rounded,
                          size: 14,
                          color: AppColors.amberText,
                        ),
                        const SizedBox(width: 6),
                        Expanded(
                          child: Text(
                            'This session has already passed but attendance '
                            "hasn't been recorded yet.",
                            style: GoogleFonts.inter(
                              fontSize: 11.5,
                              color: AppColors.amberText,
                              height: 1.4,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
                const SizedBox(height: 12),
                const Divider(color: AppColors.border, height: 1),
                const SizedBox(height: 4),
                _DetailRow(
                  icon: Icons.calendar_today_outlined,
                  label: 'Date',
                  value: _dateLabel,
                ),
                if (_timeLabel != null)
                  _DetailRow(
                    icon: Icons.access_time_outlined,
                    label: 'Time',
                    value: _timeLabel!,
                  ),
                if (seminar.venue != null && seminar.venue!.isNotEmpty)
                  _DetailRow(
                    icon: Icons.location_on_outlined,
                    label: 'Venue',
                    value: seminar.venue!,
                  ),
                if (seminar.speaker != null && seminar.speaker!.isNotEmpty)
                  _DetailRow(
                    icon: Icons.person_outline_rounded,
                    label: 'Speaker',
                    value: seminar.speaker!,
                  ),
              ],
            ),
          ),
          if (seminar.description != null &&
              seminar.description!.isNotEmpty) ...[
            const SizedBox(height: 14),
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: AppColors.surface,
                border: Border.all(color: AppColors.border),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('About this seminar', style: AppTextStyles.sectionTitle),
                  const SizedBox(height: 8),
                  Text(
                    seminar.description!,
                    style: GoogleFonts.inter(
                      fontSize: 13,
                      color: AppColors.text2,
                      height: 1.6,
                    ),
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
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          Icon(icon, size: 16, color: AppColors.text3),
          const SizedBox(width: 10),
          Text(
            label,
            style: GoogleFonts.inter(fontSize: 12, color: AppColors.text3),
          ),
          const Spacer(),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: GoogleFonts.inter(
                fontSize: 12.5,
                fontWeight: FontWeight.w500,
                color: AppColors.text1,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Chip extends StatelessWidget {
  final String label;
  final Color bg;
  final Color fg;
  const _Chip({required this.label, required this.bg, required this.fg});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        label,
        style: GoogleFonts.inter(
          fontSize: 10.5,
          fontWeight: FontWeight.w500,
          color: fg,
        ),
      ),
    );
  }
}
