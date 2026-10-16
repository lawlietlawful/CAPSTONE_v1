import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../../core/constants/app_colors.dart';
import '../../core/constants/app_text_styles.dart';
import '../../core/utils/date_utils.dart';
import '../../models/student_model.dart';
import '../../providers/auth_provider.dart';
import '../../providers/student_provider.dart';
import '../../screens/settings/settings_screen.dart';
import 'surfaces.dart';

/// Shared student profile bottom sheet, opened by tapping the avatar
/// in any screen header.
class StudentProfileSheet extends StatelessWidget {
  final StudentModel? student;
  final VoidCallback onSignOut;
  final VoidCallback onOpenSettings;

  const StudentProfileSheet({
    super.key,
    required this.student,
    required this.onSignOut,
    required this.onOpenSettings,
  });

  /// Opens the sheet reading the student from [StudentProvider].
  static void show(BuildContext context) {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (sheetCtx) => StudentProfileSheet(
        student: context.read<StudentProvider>().student,
        onSignOut: () {
          Navigator.pop(sheetCtx);
          context.read<AuthProvider>().logout(context);
        },
        onOpenSettings: () {
          Navigator.pop(sheetCtx);
          Navigator.of(context).push(
            MaterialPageRoute(builder: (_) => const SettingsScreen()),
          );
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final details = [
      if ((student?.gradeLevelDisplay ?? '').isNotEmpty) student!.gradeLevelDisplay,
      if ((student?.section ?? '').isNotEmpty) student!.section,
    ].join(' · ');

    return Container(
      decoration: const BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      padding: const EdgeInsets.fromLTRB(20, 10, 20, 0),
      child: SafeArea(
        top: false,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Drag handle
            Center(
              child: Container(
                width: 36,
                height: 4,
                decoration: BoxDecoration(
                  color: AppColors.borderStrong,
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),
            const SizedBox(height: 18),

            Row(
              children: [
                InitialsAvatar(initials: student?.initials ?? '?', size: 52, dark: true),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        student?.fullName ?? '—',
                        style: GoogleFonts.publicSans(fontSize: 17, fontWeight: FontWeight.w700, color: AppColors.text1),
                      ),
                      if (details.isNotEmpty) ...[
                        const SizedBox(height: 2),
                        Text(details, style: AppTextStyles.meta.copyWith(fontSize: 13, color: AppColors.text2)),
                      ],
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 18),

            CardGroup(
              children: [
                _InfoRow(icon: Icons.badge_outlined, label: 'Student ID', value: student?.schoolId ?? '—'),
                // Many students sign in with their ID and have no email.
                if ((student?.email ?? '').trim().isNotEmpty)
                  _InfoRow(icon: Icons.email_outlined, label: 'Email', value: student!.email),
                _InfoRow(icon: Icons.school_outlined, label: 'School year', value: AppDateUtils.schoolYear()),
                InkWell(
                  onTap: onOpenSettings,
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(14, 14, 10, 14),
                    child: Row(
                      children: [
                        const Icon(Icons.settings_outlined, size: 19, color: AppColors.text2),
                        const SizedBox(width: 12),
                        Expanded(child: Text('Settings', style: AppTextStyles.sectionTitle)),
                        const Icon(Icons.chevron_right_rounded, size: 20, color: AppColors.iconMuted),
                      ],
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),

            SizedBox(
              height: 48,
              child: OutlinedButton.icon(
                onPressed: onSignOut,
                style: OutlinedButton.styleFrom(
                  foregroundColor: AppColors.redText,
                  side: const BorderSide(color: Color(0xFFF1C6BF)),
                ),
                icon: const Icon(Icons.logout_rounded, size: 19),
                label: Text('Sign out', style: GoogleFonts.publicSans(fontSize: 15, fontWeight: FontWeight.w600)),
              ),
            ),
            const SizedBox(height: 16),
          ],
        ),
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;
  const _InfoRow({required this.icon, required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return MergeSemantics(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
        child: Row(
          children: [
            Icon(icon, size: 19, color: AppColors.text2),
            const SizedBox(width: 12),
            Text(label, style: AppTextStyles.body.copyWith(color: AppColors.text2)),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                value,
                textAlign: TextAlign.right,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: AppTextStyles.sectionTitle,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
