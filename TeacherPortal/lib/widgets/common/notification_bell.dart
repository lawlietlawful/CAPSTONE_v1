import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../providers/teacher_provider.dart';
import '../../screens/notifications/notifications_screen.dart';

/// Navy-header bell with an unread badge. Opens the notifications feed; the
/// badge reflects [TeacherProvider.unreadNotifications] live.
class NotificationBell extends StatelessWidget {
  const NotificationBell({super.key});

  @override
  Widget build(BuildContext context) {
    final unread = context.watch<TeacherProvider>().unreadNotifications;

    return GestureDetector(
      onTap: () async {
        await Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => const NotificationsScreen()),
        );
        // Refresh the count after returning (may have been marked read).
        if (context.mounted) {
          context.read<TeacherProvider>().loadNotifications();
        }
      },
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
              child: const Icon(Icons.notifications_none_rounded,
                  size: 20, color: AppColors.navyText),
            ),
            if (unread > 0)
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
                    unread > 9 ? '9+' : '$unread',
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
    );
  }
}
