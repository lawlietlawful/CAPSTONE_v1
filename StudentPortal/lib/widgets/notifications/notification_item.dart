import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../core/constants/app_colors.dart';
import '../../core/constants/app_text_styles.dart';
import '../../core/utils/date_utils.dart';
import '../../models/notification_model.dart';

/// One notification as a row inside a grouped card: an icon for its kind,
/// the title and time, the message, and a dot while it's unread.
class NotificationItem extends StatelessWidget {
  final NotificationModel notification;
  final VoidCallback? onTap;

  const NotificationItem({
    super.key,
    required this.notification,
    this.onTap,
  });

  IconData get _icon {
    switch (notification.type) {
      case 'referral_alert':
        return Icons.chat_bubble_outline_rounded;
      case 'seminar_ai':
        return Icons.event_note_outlined;
      case 'sms_sent':
        return Icons.sms_outlined;
      default:
        return Icons.school_outlined;
    }
  }

  (Color, Color) get _tone {
    switch (notification.type) {
      case 'referral_alert':
        return (AppColors.navy, Colors.white);
      case 'seminar_ai':
      case 'sms_sent':
        return (AppColors.accentLight, AppColors.accent);
      default:
        return (AppColors.greenBg, AppColors.greenText);
    }
  }

  @override
  Widget build(BuildContext context) {
    final unread = !notification.isRead;
    final (bg, fg) = _tone;
    final title = notification.title.trim().isEmpty ? 'Notification' : notification.title;

    return Semantics(
      label: unread ? 'Unread' : null,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(10)),
                child: Icon(_icon, size: 18, color: fg),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Text(
                            title,
                            style: GoogleFonts.publicSans(
                              fontSize: 14,
                              fontWeight: unread ? FontWeight.w600 : FontWeight.w500,
                              color: unread ? AppColors.text1 : const Color(0xFF2B3648),
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          AppDateUtils.formatNotificationTime(notification.createdAt),
                          style: AppTextStyles.meta.copyWith(fontSize: 12),
                        ),
                      ],
                    ),
                    const SizedBox(height: 2),
                    Text(
                      notification.message,
                      maxLines: 3,
                      overflow: TextOverflow.ellipsis,
                      style: GoogleFonts.publicSans(fontSize: 13, height: 19 / 13, color: AppColors.text2),
                    ),
                  ],
                ),
              ),
              if (unread) ...[
                const SizedBox(width: 10),
                Container(
                  width: 8,
                  height: 8,
                  margin: const EdgeInsets.only(top: 6),
                  decoration: const BoxDecoration(color: AppColors.accent, shape: BoxShape.circle),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
