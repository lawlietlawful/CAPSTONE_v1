import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';

import '../../core/constants/app_colors.dart';
import '../../core/constants/app_decor.dart';
import '../../core/constants/app_text_styles.dart';
import '../../models/upcoming_seminar.dart';
import 'surfaces.dart';

/// One seminar some of the teacher's advisees are enrolled in: when and
/// where, and which advisees will be out of class for it.
class UpcomingSeminarCard extends StatelessWidget {
  final UpcomingSeminar seminar;
  const UpcomingSeminarCard({super.key, required this.seminar});

  @override
  Widget build(BuildContext context) {
    final start = seminar.startsAt;
    final today = DateUtils.dateOnly(DateTime.now());
    final day = start == null ? null : DateUtils.dateOnly(start);
    final when = day == null
        ? null
        : day == today
            ? 'Today'
            : day == today.add(const Duration(days: 1))
                ? 'Tomorrow'
                : DateFormat('EEE').format(day);
    final time = (start != null && seminar.time != null && seminar.time!.isNotEmpty)
        ? DateFormat('h:mm a').format(start)
        : null;
    final names = seminar.students;

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: AppDecor.card(),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          DateTile(date: day),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Text(
                        seminar.title,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: GoogleFonts.publicSans(fontSize: 14.5, height: 1.35, fontWeight: FontWeight.w600, color: AppColors.text1),
                      ),
                    ),
                    if (seminar.isRequired)
                      Container(
                        margin: const EdgeInsets.only(left: 8),
                        height: 22,
                        alignment: Alignment.center,
                        padding: const EdgeInsets.symmetric(horizontal: 7),
                        decoration: BoxDecoration(
                          color: AppColors.accentLight,
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text(
                          'Required',
                          style: GoogleFonts.publicSans(fontSize: 11.5, fontWeight: FontWeight.w600, color: AppColors.accentDark),
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 3),
                Text(
                  [?when, ?time, if (seminar.venue != null && seminar.venue!.isNotEmpty) seminar.venue!].join(' · '),
                  style: AppTextStyles.meta.copyWith(color: AppColors.text2),
                ),
                if (names.isNotEmpty) ...[
                  const SizedBox(height: 10),
                  Row(
                    children: [
                      SizedBox(
                        width: 24 + 18.0 * (names.take(3).length - 1),
                        height: 24,
                        child: Stack(
                          children: [
                            for (var i = 0; i < names.take(3).length; i++)
                              Positioned(
                                left: 18.0 * i,
                                child: Container(
                                  decoration: BoxDecoration(
                                    shape: BoxShape.circle,
                                    border: Border.all(color: AppColors.surface, width: 2),
                                  ),
                                  child: InitialsAvatar(initials: InitialsAvatar.of(names[i]), size: 22),
                                ),
                              ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          _who(names),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: AppTextStyles.meta.copyWith(color: AppColors.text2),
                        ),
                      ),
                    ],
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  /// "Ana Cruz", "Ana Cruz and Ben Reyes", "Ana Cruz, Ben Reyes +2 more".
  static String _who(List<String> names) {
    final shown = names.take(2).map(_firstLast).toList();
    if (names.length == 1) return shown.first;
    if (names.length == 2) return '${shown[0]} and ${shown[1]}';
    return '${shown.join(', ')} +${names.length - 2} more';
  }

  /// "Cruz, Ana" → "Ana Cruz".
  static String _firstLast(String name) {
    final parts = name.split(', ');
    return parts.length == 2 ? '${parts[1]} ${parts[0]}' : name;
  }
}
