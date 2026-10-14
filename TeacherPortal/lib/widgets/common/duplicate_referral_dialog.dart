import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';

import '../../core/constants/app_colors.dart';
import 'status_badges.dart';

enum DuplicateChoice { fileAnyway, messageCounselor, cancel }

/// Shown when the student already has an open referral with Guidance: what
/// it is and who has it, and the teacher's three ways forward. Someone
/// else's referral shows what and when only — never what they wrote.
Future<DuplicateChoice?> showDuplicateReferralDialog(
  BuildContext context, {
  required String studentName,
  required Map<String, dynamic> existing,
}) {
  final mine = existing['mine'] == true;
  final type = existing['referral_type_label']?.toString() ?? 'Referral';
  final status = existing['status']?.toString() ?? 'pending';
  final counselor = existing['counselor_name']?.toString();
  final filed = DateTime.tryParse(existing['created_at']?.toString() ?? '');
  final filedLabel = filed == null ? '' : DateFormat('MMM d, yyyy').format(filed.toLocal());

  return showDialog<DuplicateChoice>(
    context: context,
    builder: (ctx) => AlertDialog(
      key: const Key('duplicate-referral-dialog'),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      title: Text(
        'Already referred',
        style: GoogleFonts.inter(fontSize: 16, fontWeight: FontWeight.w600, color: AppColors.text1),
      ),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            '$studentName already has an open referral with Guidance.',
            style: GoogleFonts.inter(fontSize: 13, color: AppColors.text2, height: 1.45),
          ),
          const SizedBox(height: 14),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: AppColors.background,
              border: Border.all(color: AppColors.border),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        type,
                        style: GoogleFonts.inter(fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.text1),
                      ),
                    ),
                    AppBadge.status(status),
                  ],
                ),
                const SizedBox(height: 6),
                Text(
                  [
                    mine ? 'Filed by you' : 'Filed by another staff member',
                    if (filedLabel.isNotEmpty) filedLabel,
                  ].join(' · '),
                  style: GoogleFonts.inter(fontSize: 12, color: AppColors.text3),
                ),
                const SizedBox(height: 2),
                Text(
                  counselor == null ? 'Not yet assigned to a counselor' : 'Handled by $counselor',
                  style: GoogleFonts.inter(fontSize: 12, color: AppColors.text3),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          Text(
            'Adding to it is often better than a second referral.',
            style: GoogleFonts.inter(fontSize: 12, color: AppColors.text3, height: 1.4),
          ),
        ],
      ),
      actionsPadding: const EdgeInsets.fromLTRB(20, 0, 20, 14),
      actions: [
        Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            FilledButton(
              onPressed: () => Navigator.pop(ctx, DuplicateChoice.messageCounselor),
              style: FilledButton.styleFrom(
                backgroundColor: AppColors.accent,
                padding: const EdgeInsets.symmetric(vertical: 12),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
              child: Text(
                counselor == null ? 'Message Guidance' : 'Message $counselor',
                style: GoogleFonts.inter(fontWeight: FontWeight.w600),
              ),
            ),
            const SizedBox(height: 4),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                TextButton(
                  onPressed: () => Navigator.pop(ctx, DuplicateChoice.cancel),
                  child: Text('Cancel', style: GoogleFonts.inter(color: AppColors.text2)),
                ),
                TextButton(
                  onPressed: () => Navigator.pop(ctx, DuplicateChoice.fileAnyway),
                  child: Text('File anyway', style: GoogleFonts.inter(color: AppColors.text2)),
                ),
              ],
            ),
          ],
        ),
      ],
    ),
  );
}
