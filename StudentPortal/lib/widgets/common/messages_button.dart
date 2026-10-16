import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../providers/notification_provider.dart';
import '../../providers/student_provider.dart';
import '../../screens/messaging/messaging_screen.dart';
import 'student_profile_sheet.dart';

/// The right side of every tab's header: messages from Guidance, then the
/// student's avatar (profile and settings).
class HeaderActions extends StatelessWidget {
  const HeaderActions({super.key});

  @override
  Widget build(BuildContext context) {
    return const Row(
      mainAxisSize: MainAxisSize.min,
      children: [MessagesButton(), SizedBox(width: 8), ProfileButton()],
    );
  }
}

/// Messages from Guidance, in every tab's header with a live unread badge —
/// instead of a floating button covering the content.
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
                  borderRadius: BorderRadius.circular(10),
                  onTap: () async {
                    await Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => const MessagingScreen()),
                    );
                    if (context.mounted) {
                      context.read<NotificationProvider>().fetchUnreadMessages();
                    }
                  },
                  child: const SizedBox(
                    width: 44,
                    height: 44,
                    child: Icon(Icons.mail_outline_rounded, size: 21, color: Color(0xFF2B3648)),
                  ),
                ),
              ),
              if (unread > 0)
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
                        unread > 9 ? '9+' : '$unread',
                        style: GoogleFonts.publicSans(fontSize: 11, fontWeight: FontWeight.w700, color: Colors.white, height: 1),
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

/// The student's initials in a navy circle; opens the profile sheet.
class ProfileButton extends StatelessWidget {
  const ProfileButton({super.key});

  @override
  Widget build(BuildContext context) {
    final initials = context.watch<StudentProvider>().student?.initials ?? '';
    return Tooltip(
      message: 'Your profile',
      child: Semantics(
        button: true,
        label: 'Your profile and settings',
        excludeSemantics: true,
        child: Material(
          color: AppColors.navy,
          shape: const CircleBorder(),
          child: InkWell(
            customBorder: const CircleBorder(),
            onTap: () => StudentProfileSheet.show(context),
            child: SizedBox(
              width: 44,
              height: 44,
              child: Center(
                child: initials.isEmpty
                    ? const Icon(Icons.person_rounded, size: 21, color: Colors.white)
                    : Text(
                        initials,
                        style: GoogleFonts.publicSans(fontSize: 14, fontWeight: FontWeight.w700, color: Colors.white),
                      ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
