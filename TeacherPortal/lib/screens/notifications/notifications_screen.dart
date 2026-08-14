import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../core/constants/app_text_styles.dart';
import '../../models/app_notification.dart';
import '../../providers/teacher_provider.dart';
import '../../widgets/common/form_widgets.dart';
import '../referrals/referral_detail_screen.dart';

/// The teacher's activity feed: reports escalating, and counselors acting on
/// referrals they filed. Read/unread is shown; "Mark all read" clears the bell.
class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) context.read<TeacherProvider>().loadNotifications();
    });
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<TeacherProvider>();

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text('Notifications',
            style: GoogleFonts.inter(fontSize: 16, fontWeight: FontWeight.w500)),
        actions: [
          if (provider.unreadNotifications > 0)
            TextButton(
              onPressed: () => provider.markAllNotificationsRead(),
              child: Text('Mark all read',
                  style: GoogleFonts.inter(
                      fontSize: 12.5,
                      fontWeight: FontWeight.w500,
                      color: AppColors.accent)),
            ),
        ],
      ),
      body: _buildBody(provider),
    );
  }

  Widget _buildBody(TeacherProvider provider) {
    if (provider.loadingNotifications && provider.notifications.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (provider.notificationsError != null && provider.notifications.isEmpty) {
      return CenteredMessage(
        icon: Icons.wifi_off_rounded,
        title: 'Could not load notifications',
        message: provider.notificationsError!,
        actionLabel: 'Retry',
        onAction: () => provider.loadNotifications(),
      );
    }

    if (provider.notifications.isEmpty) {
      return RefreshIndicator(
        onRefresh: () => provider.loadNotifications(),
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: const [
            SizedBox(height: 140),
            CenteredMessage(
              icon: Icons.notifications_none_rounded,
              title: 'No notifications',
              message:
                  "You'll be notified when a report escalates or Guidance acts "
                  'on one of your referrals.',
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () => provider.loadNotifications(),
      child: ListView.separated(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
        itemCount: provider.notifications.length,
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (_, i) => _NotificationCard(item: provider.notifications[i]),
      ),
    );
  }
}

class _NotificationCard extends StatelessWidget {
  final AppNotification item;
  const _NotificationCard({required this.item});

  ({IconData icon, Color bg, Color fg}) get _visual {
    switch (item.type) {
      case 'report_escalated':
        return (
          icon: Icons.trending_up_rounded,
          bg: AppColors.purpleBg,
          fg: AppColors.purpleText
        );
      case 'referral_status':
        return (
          icon: Icons.support_agent_rounded,
          bg: AppColors.accentLight,
          fg: AppColors.accentDark
        );
      default:
        return (
          icon: Icons.notifications_rounded,
          bg: AppColors.background,
          fg: AppColors.text3
        );
    }
  }

  String get _timeLabel {
    final raw = item.createdAt;
    if (raw == null || raw.isEmpty) return '';
    try {
      final dt = DateTime.parse(raw).toLocal();
      final diff = DateTime.now().difference(dt);
      if (diff.inMinutes < 1) return 'Just now';
      if (diff.inMinutes < 60) return '${diff.inMinutes}m ago';
      if (diff.inHours < 24) return '${diff.inHours}h ago';
      if (diff.inDays < 7) return '${diff.inDays}d ago';
      return DateFormat('MMM d').format(dt);
    } catch (_) {
      return '';
    }
  }

  @override
  Widget build(BuildContext context) {
    final v = _visual;
    return InkWell(
      borderRadius: BorderRadius.circular(12),
      onTap: !item.isLinkable
          ? null
          : () async {
              final provider = context.read<TeacherProvider>();
              provider.markNotificationRead(item.id);
              await Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) =>
                      ReferralDetailScreen(referralId: item.referenceId!),
                ),
              );
            },
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          // Unread gets a faint tint + accent border so it stands out.
          color: item.isRead ? AppColors.surface : AppColors.accentLight,
          border: Border.all(
              color: item.isRead ? AppColors.border : AppColors.accent.withValues(alpha: 0.4)),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 34,
              height: 34,
              decoration: BoxDecoration(color: v.bg, shape: BoxShape.circle),
              child: Icon(v.icon, size: 17, color: v.fg),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(item.title,
                            style: AppTextStyles.sectionTitle),
                      ),
                      if (!item.isRead)
                        Container(
                          width: 8,
                          height: 8,
                          margin: const EdgeInsets.only(left: 6, top: 4),
                          decoration: const BoxDecoration(
                              color: AppColors.accent, shape: BoxShape.circle),
                        ),
                    ],
                  ),
                  const SizedBox(height: 3),
                  Text(item.message,
                      style: GoogleFonts.inter(
                          fontSize: 12.5, color: AppColors.text2, height: 1.4)),
                  if (_timeLabel.isNotEmpty) ...[
                    const SizedBox(height: 6),
                    Text(_timeLabel, style: AppTextStyles.meta),
                  ],
                ],
              ),
            ),
            if (item.isLinkable)
              const Padding(
                padding: EdgeInsets.only(left: 4, top: 2),
                child: Icon(Icons.chevron_right_rounded,
                    size: 18, color: AppColors.text3),
              ),
          ],
        ),
      ),
    );
  }
}
