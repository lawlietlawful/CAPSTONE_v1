import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../core/constants/app_decor.dart';
import '../../core/constants/app_text_styles.dart';
import '../../providers/auth_provider.dart';
import '../../providers/teacher_provider.dart';
import '../../widgets/common/notification_bell.dart';
import '../../widgets/common/page_header.dart';
import '../../widgets/common/surfaces.dart';
import 'change_password_screen.dart';

/// The signed-in teacher's account: name, email, and sign out. Its own
/// bottom-nav tab rather than a header-avatar bottom sheet, so it's reachable
/// the same way as every other destination instead of a hidden tap target.
class ProfileScreen extends StatelessWidget {
  const ProfileScreen({super.key});

  Future<void> _signOut(BuildContext context) async {
    final auth = context.read<AuthProvider>();
    final teacher = context.read<TeacherProvider>();
    final navigator = Navigator.of(context, rootNavigator: true);
    final drafts = teacher.drafts.length;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Sign out?'),
        content: Text(
          drafts == 0
              ? "You'll need your Employee ID and password to sign back in."
              : 'You have $drafts unsent report${drafts == 1 ? '' : 's'} '
                  'saved on this phone. Signing out deletes ${drafts == 1 ? 'it' : 'them'}. '
                  'Send ${drafts == 1 ? 'it' : 'them'} first from the Reports tab.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            style: TextButton.styleFrom(foregroundColor: AppColors.text2),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: TextButton.styleFrom(foregroundColor: AppColors.redText),
            child: const Text('Sign out'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    await auth.logout();
    teacher.reset();
    navigator.pushNamedAndRemoveUntil('/login', (_) => false);
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final dashboard = context.watch<TeacherProvider>().dashboard;
    final employeeId = dashboard.employeeId;

    return Column(
      children: [
        const PageHeader(
          title: 'Profile',
          subtitle: 'Your account',
          trailing: HeaderActions(),
        ),
        Expanded(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 2, 16, 32),
            children: [
              Container(
                padding: const EdgeInsets.all(16),
                decoration: AppDecor.card(),
                child: Row(
                  children: [
                    InitialsAvatar(initials: auth.initials, size: 52, dark: true),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(
                            auth.name.isEmpty ? 'Teacher' : auth.name,
                            style: GoogleFonts.publicSans(fontSize: 17, fontWeight: FontWeight.w700, color: AppColors.text1),
                          ),
                          if (auth.email.isNotEmpty) ...[
                            const SizedBox(height: 2),
                            Text(auth.email, style: AppTextStyles.meta.copyWith(fontSize: 13)),
                          ],
                        ],
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 22),
              const GroupLabel('Account'),
              CardGroup(
                children: [
                  _InfoRow(
                    label: 'Employee ID',
                    value: (employeeId == null || employeeId.isEmpty) ? '—' : employeeId,
                  ),
                  // The classes decide which students appear under My Students.
                  Padding(
                    padding: const EdgeInsets.fromLTRB(14, 13, 14, 14),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('My advisory', style: AppTextStyles.body.copyWith(color: AppColors.text2)),
                        const SizedBox(height: 10),
                        if (dashboard.advisory.isEmpty)
                          Text(
                            'No classes assigned yet. Ask the Guidance Office to assign your advisory.',
                            style: AppTextStyles.meta.copyWith(height: 1.4),
                          )
                        else
                          Wrap(
                            spacing: 6,
                            runSpacing: 6,
                            children: [
                              for (final cls in dashboard.advisory)
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                                  decoration: BoxDecoration(
                                    color: AppColors.accentLight,
                                    borderRadius: BorderRadius.circular(6),
                                  ),
                                  child: Text(
                                    cls,
                                    style: GoogleFonts.publicSans(fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.accentDark),
                                  ),
                                ),
                            ],
                          ),
                      ],
                    ),
                  ),
                  InkWell(
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => const ChangePasswordScreen()),
                    ),
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(14, 14, 10, 14),
                      child: Row(
                        children: [
                          const Icon(Icons.lock_outline_rounded, size: 20, color: AppColors.text2),
                          const SizedBox(width: 12),
                          Expanded(child: Text('Change password', style: AppTextStyles.sectionTitle)),
                          const Icon(Icons.chevron_right_rounded, size: 20, color: AppColors.iconMuted),
                        ],
                      ),
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 22),
              SizedBox(
                height: 48,
                child: OutlinedButton.icon(
                  onPressed: () => _signOut(context),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: AppColors.redText,
                    side: const BorderSide(color: Color(0xFFF1C6BF)),
                  ),
                  icon: const Icon(Icons.logout_rounded, size: 19),
                  label: Text('Sign out', style: GoogleFonts.publicSans(fontSize: 15, fontWeight: FontWeight.w600)),
                ),
              ),
              const SizedBox(height: 24),
              Center(
                child: Text(
                  'Student Referral System v1.0 · Misamis University',
                  style: AppTextStyles.meta.copyWith(fontSize: 12),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _InfoRow extends StatelessWidget {
  final String label;
  final String value;
  const _InfoRow({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
      child: Row(
        children: [
          Text(label, style: AppTextStyles.body.copyWith(color: AppColors.text2)),
          const Spacer(),
          Text(value, style: AppTextStyles.sectionTitle),
        ],
      ),
    );
  }
}
