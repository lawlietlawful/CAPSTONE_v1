import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../providers/notification_provider.dart';
import '../../screens/messaging/messaging_screen.dart';

/// Messages from Guidance, in every tab's navy header with a live unread
/// badge — instead of a floating button covering the content.
class MessagesButton extends StatelessWidget {
  const MessagesButton({super.key});

  @override
  Widget build(BuildContext context) {
    final unread = context.watch<NotificationProvider>().unreadMessages;

    return Tooltip(
      message: 'Messages from Guidance',
      child: Semantics(
        button: true,
        label: unread > 0 ? 'Messages, $unread unread' : 'Messages',
        child: GestureDetector(
          onTap: () async {
            await Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => const MessagingScreen()),
            );
            if (context.mounted) {
              context.read<NotificationProvider>().fetchUnreadMessages();
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
                  child: const Icon(Icons.mail_outline_rounded, size: 19, color: AppColors.navyText),
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
                        style: GoogleFonts.inter(fontSize: 10, fontWeight: FontWeight.w700, color: Colors.white, height: 1),
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
