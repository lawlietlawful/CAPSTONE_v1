import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../core/constants/app_colors.dart';
import '../../core/constants/app_text_styles.dart';
import '../../core/utils/date_utils.dart';
import '../../core/utils/string_utils.dart';
import '../../models/seminar_model.dart';
import '../../widgets/seminars/ai_assigned_badge.dart';

class SeminarDetailScreen extends StatelessWidget {
  final SeminarModel seminar;

  const SeminarDetailScreen({super.key, required this.seminar});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        backgroundColor: AppColors.navy,
        elevation: 0,
        title: Text('Seminar Details', style: AppTextStyles.navyPageTitle),
        iconTheme: const IconThemeData(color: AppColors.navyText),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // ── Header card ─────────────────────────────────
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: AppColors.surface,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: AppColors.border),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Wrap(
                    spacing: 8,
                    runSpacing: 6,
                    children: [
                      if (seminar.isAiAssigned) const AiAssignedBadge(),
                      _Chip(
                        label: seminar.isRequired ? 'Required' : 'Optional',
                        bg: seminar.isRequired
                            ? AppColors.redBg
                            : AppColors.greenBg,
                        textColor: seminar.isRequired
                            ? AppColors.redText
                            : AppColors.greenText,
                      ),
                      _Chip(
                        label: _statusLabel(seminar.status),
                        bg: _statusBg(seminar.status),
                        textColor: _statusText(seminar.status),
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  Text(
                    seminar.title,
                    style: GoogleFonts.inter(
                      fontSize: 18,
                      fontWeight: FontWeight.w500,
                      color: AppColors.text1,
                      height: 1.4,
                    ),
                  ),
                  const SizedBox(height: 10),
                  const Divider(color: AppColors.border, height: 1),
                  const SizedBox(height: 4),
                  _DetailRow(
                    icon: Icons.calendar_today_outlined,
                    label: 'Date',
                    value: AppDateUtils.formatFullDate(seminar.date),
                  ),
                  _DetailRow(
                    icon: Icons.access_time_outlined,
                    label: 'Time',
                    value: AppDateUtils.formatTime(seminar.time),
                  ),
                  _DetailRow(
                    icon: Icons.location_on_outlined,
                    label: 'Venue',
                    value: seminar.venue,
                  ),
                  if (seminar.speaker != null && seminar.speaker!.isNotEmpty)
                    _DetailRow(
                      icon: Icons.person_outlined,
                      label: 'Speaker',
                      value: seminar.speaker!,
                    ),
                  _DetailRow(
                    icon: Icons.info_outlined,
                    label: 'Status',
                    value: AppStringUtils.capitalize(seminar.status),
                  ),
                ],
              ),
            ),

            // ── Description card ────────────────────────────
            if (seminar.description != null &&
                seminar.description!.isNotEmpty) ...[
              const SizedBox(height: 16),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: AppColors.surface,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: AppColors.border),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('About this seminar',
                        style: AppTextStyles.sectionTitle),
                    const SizedBox(height: 8),
                    Text(
                      seminar.description!,
                      style: GoogleFonts.inter(
                        fontSize: 13,
                        fontWeight: FontWeight.w400,
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
      ),
    );
  }

  String _statusLabel(String status) {
    switch (status) {
      case 'attended':
        return '✓ Attended';
      case 'missed':
        return 'Missed';
      default:
        return 'Enrolled';
    }
  }

  Color _statusBg(String status) {
    switch (status) {
      case 'attended':
        return AppColors.greenBg;
      case 'missed':
        return AppColors.redBg;
      default:
        return AppColors.accentLight;
    }
  }

  Color _statusText(String status) {
    switch (status) {
      case 'attended':
        return AppColors.greenText;
      case 'missed':
        return AppColors.redText;
      default:
        return AppColors.accentDark;
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
  final Color textColor;
  const _Chip({required this.label, required this.bg, required this.textColor});

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
          color: textColor,
        ),
      ),
    );
  }
}
