import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../../core/constants/app_colors.dart';
import '../../core/constants/app_text_styles.dart';
import '../../models/student_model.dart';
import '../../providers/auth_provider.dart';
import '../../providers/student_provider.dart';
import '../../screens/settings/settings_screen.dart';

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
    return Container(
      decoration: const BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      padding: const EdgeInsets.fromLTRB(24, 12, 24, 0),
      child: SafeArea(
        top: false,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // Drag handle
            Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: AppColors.border,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            const SizedBox(height: 24),

            // Avatar
            Container(
              width: 60,
              height: 60,
              decoration: const BoxDecoration(
                color: AppColors.navy2,
                shape: BoxShape.circle,
              ),
              alignment: Alignment.center,
              child: Text(
                student?.initials ?? '?',
                style: GoogleFonts.inter(
                  fontSize: 22,
                  fontWeight: FontWeight.w500,
                  color: AppColors.navyInitial,
                ),
              ),
            ),
            const SizedBox(height: 12),

            // Name
            Text(
              student?.fullName ?? '—',
              style: AppTextStyles.pageTitle,
            ),
            const SizedBox(height: 4),
            Text(
              '${student?.section ?? '—'} · ${student?.gradeLevelDisplay ?? '—'}',
              style: AppTextStyles.meta,
            ),
            const SizedBox(height: 20),

            // Info rows
            Container(
              decoration: BoxDecoration(
                color: AppColors.background,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: AppColors.border),
              ),
              child: Column(
                children: [
                  _InfoRow(
                    icon: Icons.badge_outlined,
                    label: 'ID: ${student?.schoolId ?? '—'}',
                  ),
                  const Divider(height: 1, color: AppColors.border),
                  _InfoRow(
                    icon: Icons.email_outlined,
                    label: student?.email ?? '—',
                  ),
                  const Divider(height: 1, color: AppColors.border),
                  const _InfoRow(
                    icon: Icons.school_outlined,
                    label: 'S.Y. 2025–2026',
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),

            // Settings row
            GestureDetector(
              onTap: onOpenSettings,
              behavior: HitTestBehavior.opaque,
              child: Container(
                decoration: BoxDecoration(
                  color: AppColors.background,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: AppColors.border),
                ),
                padding:
                    const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                child: Row(
                  children: [
                    const Icon(Icons.settings_outlined,
                        size: 16, color: AppColors.text2),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text('Settings', style: AppTextStyles.body),
                    ),
                    const Icon(Icons.chevron_right,
                        size: 18, color: AppColors.text3),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 12),

            // Sign out button
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: onSignOut,
                icon: const Icon(
                  Icons.logout_rounded,
                  size: 18,
                  color: AppColors.red,
                ),
                label: Text(
                  'Sign Out',
                  style: GoogleFonts.inter(
                    fontSize: 14,
                    fontWeight: FontWeight.w500,
                    color: AppColors.red,
                  ),
                ),
                style: OutlinedButton.styleFrom(
                  side: const BorderSide(color: AppColors.red),
                  padding: const EdgeInsets.symmetric(vertical: 13),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10),
                  ),
                ),
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
  const _InfoRow({required this.icon, required this.label});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      child: Row(
        children: [
          Icon(icon, size: 16, color: AppColors.text3),
          const SizedBox(width: 10),
          Expanded(child: Text(label, style: AppTextStyles.body)),
        ],
      ),
    );
  }
}
