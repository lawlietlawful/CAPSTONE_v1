import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../core/constants/app_colors.dart';
import '../../core/utils/date_utils.dart';
import '../../models/notification_model.dart';

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
        return Icons.warning_amber_outlined;
      case 'seminar_ai':
        return Icons.memory_outlined;
      case 'sms_sent':
        return Icons.message_outlined;
      default:
        return Icons.school_outlined;
    }
  }

  Color get _iconBg {
    switch (notification.type) {
      case 'referral_alert':
        return AppColors.redBg;
      case 'seminar_ai':
        return AppColors.purpleBg;
      case 'sms_sent':
        return AppColors.accentLight;
      default:
        return AppColors.greenBg;
    }
  }

  Color get _iconColor {
    switch (notification.type) {
      case 'referral_alert':
        return AppColors.red;
      case 'seminar_ai':
        return AppColors.purple;
      case 'sms_sent':
        return AppColors.accent;
      default:
        return AppColors.green;
    }
  }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: AppColors.border),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Icon chip
            Container(
              width: 34,
              height: 34,
              decoration: BoxDecoration(
                color: _iconBg,
                borderRadius: BorderRadius.circular(9),
              ),
              child: Icon(_icon, size: 17, color: _iconColor),
            ),
            const SizedBox(width: 10),

            // Message + time
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    notification.message,
                    maxLines: 3,
                    overflow: TextOverflow.ellipsis,
                    style: GoogleFonts.inter(
                      fontSize: 12.5,
                      fontWeight: FontWeight.w400,
                      color: AppColors.text1,
                      height: 1.5,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    AppDateUtils.formatNotificationTime(notification.createdAt),
                    style: GoogleFonts.inter(
                      fontSize: 11,
                      color: AppColors.text3,
                    ),
                  ),
                ],
              ),
            ),

            // Unread dot
            if (!notification.isRead) ...[
              const SizedBox(width: 8),
              Container(
                width: 7,
                height: 7,
                margin: const EdgeInsets.only(top: 5),
                decoration: const BoxDecoration(
                  color: AppColors.accent,
                  shape: BoxShape.circle,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
