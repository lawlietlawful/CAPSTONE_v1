import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../core/constants/app_decor.dart';
import '../../core/constants/app_text_styles.dart';
import '../../providers/auth_provider.dart';
import '../../providers/teacher_provider.dart';
import '../../widgets/common/navy_header.dart';
import '../../widgets/common/notification_bell.dart';
import 'change_password_screen.dart';

/// The signed-in teacher's account: name, email, and sign out. Its own
/// bottom-nav tab rather than a header-avatar bottom sheet, so it's reachable
/// the same way as every other destination instead of a hidden tap target.
class ProfileScreen extends StatelessWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final dashboard = context.watch<TeacherProvider>().dashboard;

    return Column(
      children: [
        const NavyHeader(
          title: 'Profile',
          subtitle: 'Your account',
          showAvatar: false,
          trailing: HeaderActions(),
        ),
        Expanded(
          child: Container(
            color: AppColors.background,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Container(
                  padding: const EdgeInsets.all(18),
                  decoration: AppDecor.card(),
                  child: Row(
                    children: [
                      Container(
                        width: 52,
                        height: 52,
                        decoration: const BoxDecoration(
                          color: AppColors.accentLight,
                          shape: BoxShape.circle,
                        ),
                        alignment: Alignment.center,
                        child: Text(
                          auth.initials,
                          style: GoogleFonts.inter(
                            fontSize: 16,
                            fontWeight: FontWeight.w500,
                            color: AppColors.accentDark,
                          ),
                        ),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              auth.name.isEmpty ? 'Teacher' : auth.name,
                              style: AppTextStyles.pageTitle,
                            ),
                            if (auth.email.isNotEmpty) ...[
                              const SizedBox(height: 3),
                              Text(auth.email, style: AppTextStyles.meta),
                            ],
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                _AccountDetails(
                  employeeId: dashboard.employeeId,
                  advisory: dashboard.advisory,
                ),
                const SizedBox(height: 16),
                InkWell(
                  borderRadius: BorderRadius.circular(12),
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => const ChangePasswordScreen(),
                    ),
                  ),
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 16,
                      vertical: 14,
                    ),
                    decoration: AppDecor.card(),
                    child: Row(
                      children: [
                        const Icon(
                          Icons.lock_outline_rounded,
                          size: 18,
                          color: AppColors.text3,
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            'Change password',
                            style: AppTextStyles.sectionTitle,
                          ),
                        ),
                        const Icon(
                          Icons.chevron_right_rounded,
                          size: 18,
                          color: AppColors.text3,
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 10),
                SizedBox(
                  width: double.infinity,
                  child: OutlinedButton.icon(
                    onPressed: () async {
                      final auth = context.read<AuthProvider>();
                      final teacher = context.read<TeacherProvider>();
                      final navigator = Navigator.of(
                        context,
                        rootNavigator: true,
                      );
                      final confirmed = await showDialog<bool>(
                        context: context,
                        builder: (ctx) => AlertDialog(
                          title: Text(
                            'Sign out?',
                            style: GoogleFonts.inter(fontSize: 15, fontWeight: FontWeight.w600),
                          ),
                          content: Text(
                            teacher.drafts.isEmpty
                                ? "You'll need your Employee ID and password to sign back in."
                                : 'You have ${teacher.drafts.length} unsent report${teacher.drafts.length == 1 ? '' : 's'} '
                                    'saved on this phone. Signing out deletes ${teacher.drafts.length == 1 ? 'it' : 'them'}. '
                                    'Send ${teacher.drafts.length == 1 ? 'it' : 'them'} first from the Reports tab.',
                            style: GoogleFonts.inter(fontSize: 13, color: AppColors.text2, height: 1.45),
                          ),
                          actions: [
                            TextButton(
                              onPressed: () => Navigator.pop(ctx, false),
                              child: Text('Cancel', style: GoogleFonts.inter(color: AppColors.text2)),
                            ),
                            TextButton(
                              onPressed: () => Navigator.pop(ctx, true),
                              child: Text('Sign out', style: GoogleFonts.inter(color: AppColors.red, fontWeight: FontWeight.w600)),
                            ),
                          ],
                        ),
                      );
                      if (confirmed != true) return;
                      await auth.logout();
                      teacher.reset();
                      navigator.pushNamedAndRemoveUntil('/login', (_) => false);
                    },
                    icon: const Icon(
                      Icons.logout_rounded,
                      size: 18,
                      color: AppColors.red,
                    ),
                    label: Text(
                      'Sign out',
                      style: GoogleFonts.inter(
                        fontSize: 13,
                        fontWeight: FontWeight.w500,
                        color: AppColors.red,
                      ),
                    ),
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 13),
                      side: const BorderSide(color: AppColors.border),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(10),
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 24),
                Center(
                  child: Text(
                    'Student Referral System v1.0 · MU',
                    style: GoogleFonts.inter(
                      fontSize: 11,
                      color: AppColors.text3,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

/// Employee ID and the classes this teacher advises — the classes decide
/// which students appear under My Students.
class _AccountDetails extends StatelessWidget {
  final String? employeeId;
  final List<String> advisory;

  const _AccountDetails({required this.employeeId, required this.advisory});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: AppDecor.card(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.badge_outlined, size: 17, color: AppColors.text3),
              const SizedBox(width: 10),
              Text('Employee ID', style: AppTextStyles.meta),
              const Spacer(),
              Text(
                (employeeId == null || employeeId!.isEmpty) ? '—' : employeeId!,
                style: GoogleFonts.inter(fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.text1),
              ),
            ],
          ),
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 12),
            child: Divider(height: 1, color: AppColors.border),
          ),
          Row(
            children: [
              const Icon(Icons.groups_outlined, size: 17, color: AppColors.text3),
              const SizedBox(width: 10),
              Text('My advisory', style: AppTextStyles.meta),
            ],
          ),
          const SizedBox(height: 10),
          if (advisory.isEmpty)
            Text(
              'No classes assigned yet. Ask the Guidance Office to assign your advisory.',
              style: GoogleFonts.inter(fontSize: 12, color: AppColors.text3, height: 1.4),
            )
          else
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                for (final cls in advisory)
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                    decoration: BoxDecoration(
                      color: AppColors.accentLight,
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Text(
                      cls,
                      style: GoogleFonts.inter(fontSize: 12, fontWeight: FontWeight.w500, color: AppColors.accentDark),
                    ),
                  ),
              ],
            ),
        ],
      ),
    );
  }
}
