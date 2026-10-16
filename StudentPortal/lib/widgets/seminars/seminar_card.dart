import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';

import '../../core/constants/app_colors.dart';
import '../../core/constants/app_decor.dart';
import '../../core/constants/app_text_styles.dart';
import '../../core/utils/date_utils.dart';
import '../../models/seminar_model.dart';
import '../common/surfaces.dart';
import '../premium/motion.dart';

/// An upcoming seminar: a date tile, when it is ("Tomorrow", "In 3 days"),
/// the title, time and venue, and whether it's required. The whole card
/// opens the details.
class SeminarCard extends StatelessWidget {
  final SeminarModel seminar;

  /// Past seminars render as a compact row with their outcome instead.
  final bool isCompleted;
  final VoidCallback? onViewDetails;

  /// The navy date tile — for the one seminar that's next.
  final bool emphasised;

  const SeminarCard({
    super.key,
    required this.seminar,
    this.isCompleted = false,
    this.onViewDetails,
    this.emphasised = false,
  });

  @override
  Widget build(BuildContext context) {
    if (isCompleted) {
      return Container(
        clipBehavior: Clip.antiAlias,
        decoration: AppDecor.card(),
        child: PastSeminarRow(seminar: seminar, onTap: onViewDetails),
      );
    }
    return FadeSlideIn(child: _upcoming(context));
  }

  Widget _upcoming(BuildContext context) {
    final date = DateTime.tryParse(seminar.date);
    final when = seminarWhenLabel(date);
    final soon = when == 'Today' || when == 'Tomorrow';
    final meta = [
      if (date != null) DateFormat('EEE').format(date),
      AppDateUtils.formatTime(seminar.time),
      if (seminar.venue.isNotEmpty) seminar.venue,
    ].join(' · ');

    return Semantics(
      button: onViewDetails != null,
      child: Pressable(
        onTap: onViewDetails,
        child: Container(
          clipBehavior: Clip.antiAlias,
          decoration: AppDecor.card(),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    DateTile(date: date, emphasised: emphasised),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          if (when != null) ...[
                            Text(
                              when,
                              style: GoogleFonts.publicSans(
                                fontSize: 12.5,
                                fontWeight: FontWeight.w700,
                                color: soon ? AppColors.amberText : AppColors.text2,
                              ),
                            ),
                            const SizedBox(height: 2),
                          ],
                          Text(
                            seminar.title,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: GoogleFonts.publicSans(fontSize: 15, height: 1.4, fontWeight: FontWeight.w600, color: AppColors.text1),
                          ),
                          const SizedBox(height: 4),
                          Text(meta, style: AppTextStyles.meta.copyWith(color: AppColors.text2)),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.fromLTRB(16, 10, 12, 10),
                decoration: const BoxDecoration(
                  color: Color(0xFFFAFBFC),
                  border: Border(top: BorderSide(color: AppColors.divider)),
                ),
                child: Row(
                  children: [
                    SeminarTag(
                      label: seminar.isRequired ? 'Required' : 'Optional',
                      bg: seminar.isRequired ? AppColors.accentLight : AppColors.neutralBg,
                      fg: seminar.isRequired ? AppColors.accentDark : AppColors.text2,
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        assignedByLabel(seminar),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: AppTextStyles.meta.copyWith(color: AppColors.text2),
                      ),
                    ),
                    if (onViewDetails != null)
                      const Icon(Icons.chevron_right_rounded, size: 20, color: AppColors.iconMuted),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// A past seminar in a list: short date, title, venue and how it went.
class PastSeminarRow extends StatelessWidget {
  final SeminarModel seminar;
  final VoidCallback? onTap;

  const PastSeminarRow({super.key, required this.seminar, this.onTap});

  static const _months = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];

  @override
  Widget build(BuildContext context) {
    final date = DateTime.tryParse(seminar.date);
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 12, 12, 12),
        child: Row(
          children: [
            SizedBox(
              width: 44,
              child: Column(
                children: [
                  Text(
                    date == null ? '—' : _months[date.month - 1],
                    style: GoogleFonts.publicSans(fontSize: 11, fontWeight: FontWeight.w700, letterSpacing: 0.6, color: AppColors.text3),
                  ),
                  Text(
                    date == null ? '' : '${date.day}',
                    style: GoogleFonts.publicSans(fontSize: 18, fontWeight: FontWeight.w700, color: AppColors.text1),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(seminar.title, maxLines: 2, overflow: TextOverflow.ellipsis, style: AppTextStyles.sectionTitle),
                  if (seminar.venue.isNotEmpty) ...[
                    const SizedBox(height: 1),
                    Text(seminar.venue, maxLines: 1, overflow: TextOverflow.ellipsis, style: AppTextStyles.meta),
                  ],
                ],
              ),
            ),
            const SizedBox(width: 8),
            outcomeTag(seminar.outcome),
          ],
        ),
      ),
    );
  }
}

/// How a past seminar turned out, as a tag.
Widget outcomeTag(String outcome) {
  switch (outcome) {
    case 'attended':
      return const SeminarTag(label: 'Attended', bg: AppColors.greenBg, fg: AppColors.greenText, icon: Icons.check_rounded);
    case 'missed':
      return const SeminarTag(label: 'Missed', bg: AppColors.redBg, fg: AppColors.redText);
    case 'excused':
      return const SeminarTag(label: 'Excused', bg: AppColors.amberBg, fg: AppColors.amberText);
    case 'cancelled':
      return const SeminarTag(label: 'Cancelled', bg: AppColors.neutralBg, fg: AppColors.text2);
    default:
      return const SeminarTag(label: 'Not recorded', bg: AppColors.neutralBg, fg: AppColors.text2);
  }
}

/// "Today", "Tomorrow", "In 3 days", or the weekday name further out.
String? seminarWhenLabel(DateTime? date) {
  if (date == null) return null;
  final today = DateUtils.dateOnly(DateTime.now());
  final days = DateUtils.dateOnly(date).difference(today).inDays;
  if (days < 0) return null;
  if (days == 0) return 'Today';
  if (days == 1) return 'Tomorrow';
  if (days < 7) return 'In $days days';
  return null;
}

/// Who put the student on the list, in plain words.
String assignedByLabel(SeminarModel seminar) =>
    seminar.isAiAssigned ? 'Matched to you by the system' : 'Assigned by Guidance';

/// Small rectangular tag used on seminar cards and details.
class SeminarTag extends StatelessWidget {
  final String label;
  final Color bg;
  final Color fg;
  final IconData? icon;

  const SeminarTag({super.key, required this.label, required this.bg, required this.fg, this.icon});

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 24,
      padding: const EdgeInsets.symmetric(horizontal: 8),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(6)),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[
            Icon(icon, size: 13, color: fg),
            const SizedBox(width: 4),
          ],
          Text(label, style: GoogleFonts.publicSans(fontSize: 12, fontWeight: FontWeight.w600, color: fg, height: 1.1)),
        ],
      ),
    );
  }
}
