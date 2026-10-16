import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../providers/teacher_provider.dart';
import '../../screens/messaging/messaging_screen.dart';
import '../../screens/notifications/notifications_screen.dart';

/// Messages and notifications, side by side in every tab's header —
/// each with a live unread badge, so neither needs a floating button.
class HeaderActions extends StatelessWidget {
  const HeaderActions({super.key});

  @override
  Widget build(BuildContext context) {
    return const Row(
      mainAxisSize: MainAxisSize.min,
      children: [MessagesButton(), SizedBox(width: 8), NotificationBell()],
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

/// A square header button with an optional red unread badge.
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
        child: SizedBox(
          width: 44,
          height: 44,
          child: Stack(
            clipBehavior: Clip.none,
            children: [
              Material(
                color: AppColors.surface,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
                  side: const BorderSide(color: AppColors.border),
                ),
                child: InkWell(
                  onTap: onTap,
                  borderRadius: BorderRadius.circular(10),
                  child: SizedBox(
                    width: 44,
                    height: 44,
                    child: Icon(icon, size: 21, color: const Color(0xFF2B3648)),
                  ),
                ),
              ),
              if (badge > 0)
                Positioned(
                  right: -5,
                  top: -5,
                  child: IgnorePointer(
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 5),
                      constraints: const BoxConstraints(minWidth: 20, minHeight: 20),
                      decoration: BoxDecoration(
                        color: AppColors.red,
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: AppColors.background, width: 2),
                      ),
                      alignment: Alignment.center,
                      child: Text(
                        badge > 9 ? '9+' : '$badge',
                        style: GoogleFonts.publicSans(
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                          color: Colors.white,
                          height: 1,
                        ),
                      ),
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
