import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../core/constants/app_colors.dart';
import '../../core/constants/app_text_styles.dart';

class NavyHeader extends StatelessWidget {
  final String title;
  final String subtitle;
  final bool showAvatar;
  final String avatarInitials;
  final VoidCallback? onAvatarTap;
  final Widget? trailing;

  const NavyHeader({
    super.key,
    required this.title,
    required this.subtitle,
    this.showAvatar = true,
    this.avatarInitials = '??',
    this.onAvatarTap,
    this.trailing,
  });

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: AppColors.navy,
      // SafeArea(bottom: false) ensures the header extends behind the status
      // bar/notch at the top while leaving the bottom free for content.
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 20),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(title, style: AppTextStyles.navyPageTitle),
                    const SizedBox(height: 3),
                    Text(subtitle, style: AppTextStyles.navySubtitle),
                  ],
                ),
              ),
              if (trailing != null) ...[
                const SizedBox(width: 12),
                trailing!,
              ],
              if (showAvatar) ...[
                const SizedBox(width: 12),
                GestureDetector(
                  onTap: onAvatarTap,
                  child: _AvatarChip(initials: avatarInitials),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _AvatarChip extends StatelessWidget {
  final String initials;
  const _AvatarChip({required this.initials});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 40,
      height: 40,
      decoration: BoxDecoration(
        color: AppColors.navy2,
        shape: BoxShape.circle,
        border: Border.all(
          color: const Color(0x26FFFFFF), // rgba(255,255,255,0.15)
          width: 2,
        ),
      ),
      alignment: Alignment.center,
      child: Text(
        initials.toUpperCase(),
        style: GoogleFonts.inter(
          fontSize: 13,
          fontWeight: FontWeight.w500,
          color: AppColors.navyInitial,
        ),
      ),
    );
  }
}
