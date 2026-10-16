import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../../core/constants/app_colors.dart';
import '../../core/constants/app_decor.dart';
import '../../core/constants/app_text_styles.dart';
import '../../core/utils/date_utils.dart';
import '../../providers/auth_provider.dart';
import '../../providers/settings_provider.dart';
import '../../providers/student_provider.dart';
import '../../widgets/common/page_header.dart';
import '../../widgets/common/surfaces.dart';
import 'change_password_screen.dart';

/// The student's account and preferences: password, reminders and alerts,
/// and about the app.
class SettingsScreen extends StatelessWidget {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final settings = context.watch<SettingsProvider>();
    final student = context.watch<StudentProvider>().student;

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Profile and settings')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 32),
        children: [
          Container(
            padding: const EdgeInsets.all(16),
            decoration: AppDecor.card(),
            child: Row(
              children: [
                InitialsAvatar(initials: student?.initials ?? '?', size: 52, dark: true),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        student?.fullName ?? '—',
                        style: GoogleFonts.publicSans(fontSize: 16, fontWeight: FontWeight.w700, color: AppColors.text1),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        [
                          if ((student?.schoolId ?? '').isNotEmpty) student!.schoolId,
                          if ((student?.section ?? '').isNotEmpty) student!.section,
                        ].join(' · '),
                        style: AppTextStyles.meta.copyWith(fontSize: 13, color: AppColors.text2),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),

          // ── Account ──────────────────────────────────────────
          const SizedBox(height: 22),
          const GroupLabel('Account'),
          CardGroup(
            children: [
              _ActionRow(
                icon: Icons.lock_outline_rounded,
                title: 'Change password',
                subtitle: 'Update your Student Portal password',
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => const ChangePasswordScreen()),
                ),
              ),
            ],
          ),

          // ── Notifications preferences ──────────────────────
          const SizedBox(height: 22),
          const GroupLabel('Notifications'),
          CardGroup(
            children: [
              _SwitchRow(
                title: 'Seminar reminders',
                subtitle: 'The day before and on the day of each seminar',
                value: settings.seminarReminders,
                onChanged: context.read<SettingsProvider>().setSeminarReminders,
              ),
              _SwitchRow(
                title: 'Guidance alerts',
                subtitle: 'Show new notices and messages from Guidance while the app is open',
                value: settings.alertNotifications,
                onChanged: context.read<SettingsProvider>().setAlertNotifications,
              ),
            ],
          ),

          // ── About ──────────────────────────────────────────
          const SizedBox(height: 22),
          const GroupLabel('About'),
          CardGroup(
            children: [
              const _InfoRow(label: 'School', value: 'Misamis University'),
              _InfoRow(label: 'School year', value: AppDateUtils.schoolYear()),
              const _InfoRow(label: 'Version', value: '1.0.0'),
            ],
          ),

          const SizedBox(height: 22),
          SizedBox(
            height: 48,
            child: OutlinedButton.icon(
              onPressed: () => context.read<AuthProvider>().logout(context),
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
              'Student Referral System · Misamis University',
              style: AppTextStyles.meta.copyWith(fontSize: 12),
            ),
          ),
        ],
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────
// Building blocks
// ─────────────────────────────────────────────────────────────

class _ActionRow extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  const _ActionRow({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 14, 10, 14),
        child: Row(
          children: [
            Icon(icon, size: 20, color: AppColors.text2),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: AppTextStyles.sectionTitle),
                  const SizedBox(height: 1),
                  Text(subtitle, style: AppTextStyles.meta),
                ],
              ),
            ),
            const Icon(Icons.chevron_right_rounded, size: 20, color: AppColors.iconMuted),
          ],
        ),
      ),
    );
  }
}

class _SwitchRow extends StatelessWidget {
  final String title;
  final String subtitle;
  final bool value;
  final ValueChanged<bool> onChanged;

  const _SwitchRow({
    required this.title,
    required this.subtitle,
    required this.value,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    return MergeSemantics(
      child: InkWell(
        onTap: () => onChanged(!value),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(14, 12, 10, 12),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: AppTextStyles.sectionTitle),
                    const SizedBox(height: 1),
                    Text(subtitle, style: AppTextStyles.meta.copyWith(height: 1.35)),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              Switch(value: value, onChanged: onChanged),
            ],
          ),
        ),
      ),
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
