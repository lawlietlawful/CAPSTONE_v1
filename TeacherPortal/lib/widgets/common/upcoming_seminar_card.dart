import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';

import '../../core/constants/app_colors.dart';
import '../../core/constants/app_decor.dart';
import '../../models/upcoming_seminar.dart';

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
                : DateFormat('EEE, MMM d').format(day);
    final time = (start != null && seminar.time != null && seminar.time!.isNotEmpty)
        ? DateFormat('h:mm a').format(start)
        : null;
    final names = seminar.students;
    final shown = names.take(3).map(_firstLast).join(', ');
    final more = names.length > 3 ? ' +${names.length - 3} more' : '';

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: AppDecor.card(),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 46,
            padding: const EdgeInsets.symmetric(vertical: 6),
            decoration: BoxDecoration(
              color: AppColors.accentLight,
              borderRadius: BorderRadius.circular(10),
            ),
            child: Column(
              children: [
                Text(
                  day == null ? '—' : DateFormat('MMM').format(day).toUpperCase(),
                  style: GoogleFonts.inter(fontSize: 9.5, fontWeight: FontWeight.w700, letterSpacing: 0.6, color: AppColors.accentDark),
                ),
                Text(
                  day == null ? '' : '${day.day}',
                  style: GoogleFonts.inter(fontSize: 17, fontWeight: FontWeight.w700, color: AppColors.accentDark, height: 1.2),
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        seminar.title,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: GoogleFonts.inter(fontSize: 13.5, fontWeight: FontWeight.w600, color: AppColors.text1),
                      ),
                    ),
                    if (seminar.isRequired)
                      Container(
                        margin: const EdgeInsets.only(left: 6),
                        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                        decoration: BoxDecoration(
                          color: AppColors.amberBg,
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text(
                          'Required',
                          style: GoogleFonts.inter(fontSize: 10, fontWeight: FontWeight.w600, color: AppColors.amberText),
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 3),
                Text(
                  [?when, ?time, if (seminar.venue != null && seminar.venue!.isNotEmpty) seminar.venue!].join(' · '),
                  style: GoogleFonts.inter(fontSize: 12, color: AppColors.text3),
                ),
                const SizedBox(height: 6),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Padding(
                      padding: EdgeInsets.only(top: 1),
                      child: Icon(Icons.groups_outlined, size: 14, color: AppColors.text3),
                    ),
                    const SizedBox(width: 5),
                    Expanded(
                      child: Text(
                        '$shown$more',
                        style: GoogleFonts.inter(fontSize: 12, color: AppColors.text2, height: 1.35),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  /// "Cruz, Ana" → "Ana Cruz".
  static String _firstLast(String name) {
    final parts = name.split(', ');
    return parts.length == 2 ? '${parts[1]} ${parts[0]}' : name;
  }
}
