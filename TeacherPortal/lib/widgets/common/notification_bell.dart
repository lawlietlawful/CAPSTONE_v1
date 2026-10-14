import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../providers/teacher_provider.dart';
import '../../screens/messaging/messaging_screen.dart';
import '../../screens/notifications/notifications_screen.dart';

/// Messages and notifications, side by side in every tab's navy header —
/// each with a live unread badge, so neither needs a floating button.
class HeaderActions extends StatelessWidget {
  const HeaderActions({super.key});

  @override
  Widget build(BuildContext context) {
    return const Row(
      mainAxisSize: MainAxisSize.min,
      children: [MessagesButton(), SizedBox(width: 10), NotificationBell()],
    );
  }
}

/// Opens the notifications feed; the badge reflects
/// [TeacherProvider.unreadNotifications] live.
class NotificationBell extends StatelessWidget {
  const NotificationBell({super.key});

  @override
  Widget build(BuildContext context) {
    return HeaderIconButton(
      icon: Icons.notifications_none_rounded,
      tooltip: 'Notifications',
      badge: context.watch<TeacherProvider>().unreadNotifications,
      onTap: () async {
        await Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => const NotificationsScreen()),
        );
        // Refresh the count after returning (may have been marked read).
        if (context.mounted) {
          context.read<TeacherProvider>().loadNotifications();
        }
      },
    );
  }
}

/// Opens the counselor messages; the badge reflects
/// [TeacherProvider.unreadMessages] live.
class MessagesButton extends StatelessWidget {
  const MessagesButton({super.key});

  @override
  Widget build(BuildContext context) {
    return HeaderIconButton(
      icon: Icons.mail_outline_rounded,
      tooltip: 'Messages',
      badge: context.watch<TeacherProvider>().unreadMessages,
      onTap: () async {
        await Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => const MessagingScreen()),
        );
        if (context.mounted) {
          context.read<TeacherProvider>().loadMessages();
        }
      },
    );
  }
}

/// A round navy-header button with an optional red unread badge.
class HeaderIconButton extends StatelessWidget {
  final IconData icon;
  final String tooltip;
  final int badge;
  final VoidCallback onTap;

  const HeaderIconButton({
    super.key,
    required this.icon,
    required this.tooltip,
    required this.onTap,
    this.badge = 0,
  });

  @override
  Widget build(BuildContext context) {
    return Tooltip(
      message: tooltip,
      child: Semantics(
        button: true,
        label: badge > 0 ? '$tooltip, $badge unread' : tooltip,
        child: GestureDetector(
          onTap: onTap,
          child: SizedBox(
            width: 40,
            height: 40,
            child: Stack(
              clipBehavior: Clip.none,
              children: [
                Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    color: AppColors.navy2,
                    shape: BoxShape.circle,
                    border: Border.all(color: const Color(0x26FFFFFF), width: 2),
                  ),
                  alignment: Alignment.center,
                  child: Icon(icon, size: 20, color: AppColors.navyText),
                ),
                if (badge > 0)
                  Positioned(
                    right: -2,
                    top: -2,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                      constraints: const BoxConstraints(minWidth: 18, minHeight: 18),
                      decoration: BoxDecoration(
                        color: AppColors.red,
                        borderRadius: BorderRadius.circular(9),
                        border: Border.all(color: AppColors.navy, width: 1.5),
                      ),
                      alignment: Alignment.center,
                      child: Text(
                        badge > 9 ? '9+' : '$badge',
                        style: GoogleFonts.inter(
                          fontSize: 10,
                          fontWeight: FontWeight.w700,
                          color: Colors.white,
                          height: 1,
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
