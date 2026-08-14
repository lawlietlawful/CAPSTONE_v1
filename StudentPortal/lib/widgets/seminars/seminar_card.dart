import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../core/constants/app_colors.dart';
import '../../core/utils/date_utils.dart';
import '../../models/seminar_model.dart';
import 'ai_assigned_badge.dart';

class SeminarCard extends StatelessWidget {
  final SeminarModel seminar;
  final bool isCompleted;
  final VoidCallback? onViewDetails;

  const SeminarCard({
    super.key,
    required this.seminar,
    this.isCompleted = false,
    this.onViewDetails,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.border),
      ),
      child: Opacity(
        opacity: isCompleted ? 0.6 : 1.0,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Title + AI badge
            Padding(
              padding: const EdgeInsets.fromLTRB(14, 12, 14, 0),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Text(
                      seminar.title,
                      style: GoogleFonts.inter(
                        fontSize: 13,
                        fontWeight: FontWeight.w500,
                        color: isCompleted ? AppColors.text3 : AppColors.text1,
                        decoration:
                            isCompleted ? TextDecoration.lineThrough : null,
                      ),
                    ),
                  ),
                  if (seminar.isAiAssigned && !isCompleted) ...[
                    const SizedBox(width: 8),
                    const AiAssignedBadge(),
                  ],
                ],
              ),
            ),

            // Meta row: date, time, venue
            Padding(
              padding: const EdgeInsets.fromLTRB(14, 6, 14, 12),
              child: Wrap(
                spacing: 12,
                runSpacing: 4,
                children: [
                  _MetaItem(
                    icon: Icons.calendar_today_outlined,
                    text: AppDateUtils.formatSeminarDate(seminar.date),
                  ),
                  _MetaItem(
                    icon: Icons.access_time_outlined,
                    text: AppDateUtils.formatTime(seminar.time),
                  ),
                  _MetaItem(
                    icon: Icons.location_on_outlined,
                    text: seminar.venue,
                  ),
                ],
              ),
            ),

            if (!isCompleted) ...[
              const Divider(color: AppColors.border, height: 1),
              Padding(
                padding:
                    const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    _Chip(
                      label: seminar.isRequired ? 'Required' : 'Optional',
                      bg: seminar.isRequired
                          ? AppColors.redBg
                          : AppColors.greenBg,
                      textColor: seminar.isRequired
                          ? AppColors.redText
                          : AppColors.greenText,
                    ),
                    if (onViewDetails != null)
                      GestureDetector(
                        onTap: onViewDetails,
                        behavior: HitTestBehavior.opaque,
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              'View details',
                              style: GoogleFonts.inter(
                                fontSize: 12,
                                fontWeight: FontWeight.w500,
                                color: AppColors.accent,
                              ),
                            ),
                            const SizedBox(width: 3),
                            const Icon(Icons.arrow_forward,
                                size: 13, color: AppColors.accent),
                          ],
                        ),
                      ),
                  ],
                ),
              ),
            ] else
              const Padding(
                padding: EdgeInsets.fromLTRB(14, 0, 14, 12),
                child: _Chip(
                  label: '✓ Attended',
                  bg: AppColors.greenBg,
                  textColor: AppColors.greenText,
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _MetaItem extends StatelessWidget {
  final IconData icon;
  final String text;
  const _MetaItem({required this.icon, required this.text});

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 12, color: AppColors.text3),
        const SizedBox(width: 4),
        Text(
          text,
          style: GoogleFonts.inter(fontSize: 11, color: AppColors.text3),
        ),
      ],
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
