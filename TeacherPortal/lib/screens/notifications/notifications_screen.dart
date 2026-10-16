import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../widgets/premium/skeleton.dart';
import '../../core/constants/app_text_styles.dart';
import '../../models/app_notification.dart';
import '../../providers/teacher_provider.dart';
import '../../widgets/common/form_widgets.dart';
import '../../widgets/common/page_header.dart';
import '../../widgets/common/surfaces.dart';
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
        title: const Text('Notifications'),
        actions: [
          if (provider.unreadNotifications > 0)
            TextButton(
              onPressed: () => provider.markAllNotificationsRead(),
              child: const Text('Mark all read'),
            ),
          const SizedBox(width: 6),
        ],
      ),
      body: _buildBody(provider),
    );
  }

  Widget _buildBody(TeacherProvider provider) {
    if (provider.loadingNotifications && provider.notifications.isEmpty) {
      return const SkeletonList(leading: true);
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

    final groups = <({String label, List<AppNotification> items})>[];
    for (final n in provider.notifications) {
      final label = _dayLabel(n.createdAt);
      if (groups.isEmpty || groups.last.label != label) {
        groups.add((label: label, items: <AppNotification>[]));
      }
      groups.last.items.add(n);
    }

    return RefreshIndicator(
      onRefresh: () => provider.loadNotifications(),
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
        children: [
          for (var g = 0; g < groups.length; g++)
            Padding(
              padding: EdgeInsets.only(top: g == 0 ? 0 : 18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  GroupLabel(groups[g].label),
                  CardGroup(
                    children: [for (final n in groups[g].items) _NotificationRow(item: n)],
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }

  /// "Today" / "Yesterday" / "Sep 23".
  static String _dayLabel(String? raw) {
    final dt = DateTime.tryParse(raw ?? '')?.toLocal();
    if (dt == null) return 'Earlier';
    final today = DateUtils.dateOnly(DateTime.now());
    final days = today.difference(DateUtils.dateOnly(dt)).inDays;
    if (days == 0) return 'Today';
    if (days == 1) return 'Yesterday';
    return DateFormat(dt.year == today.year ? 'MMM d' : 'MMM d, yyyy').format(dt);
  }
}

class _NotificationRow extends StatelessWidget {
  final AppNotification item;
  const _NotificationRow({required this.item});

  ({IconData icon, Color bg, Color fg}) get _visual {
    switch (item.type) {
      case 'report_escalated':
        return (icon: Icons.trending_up_rounded, bg: AppColors.purpleBg, fg: AppColors.purpleText);
      case 'referral_status':
        return (icon: Icons.outbox_rounded, bg: AppColors.accentLight, fg: AppColors.accent);
      default:
        return (icon: Icons.notifications_none_rounded, bg: AppColors.background, fg: AppColors.text2);
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
      return DateFormat('h:mm a').format(dt);
    } catch (_) {
      return '';
    }
  }

  @override
  Widget build(BuildContext context) {
    final v = _visual;
    final unread = !item.isRead;
    return Semantics(
      label: unread ? 'Unread' : null,
      child: InkWell(
        onTap: () async {
          final provider = context.read<TeacherProvider>();
          if (unread) provider.markNotificationRead(item.id);
          if (!item.isLinkable) return;
          await Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) => ReferralDetailScreen(referralId: item.referenceId!),
            ),
          );
        },
        child: Padding(
          padding: const EdgeInsets.fromLTRB(14, 14, 10, 14),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(color: v.bg, borderRadius: BorderRadius.circular(10)),
                child: Icon(v.icon, size: 18, color: v.fg),
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
                            item.title,
                            style: AppTextStyles.sectionTitle.copyWith(
                              fontWeight: unread ? FontWeight.w600 : FontWeight.w500,
                            ),
                          ),
                        ),
                        if (_timeLabel.isNotEmpty) ...[
                          const SizedBox(width: 8),
                          Text(_timeLabel, style: AppTextStyles.meta.copyWith(fontSize: 12)),
                        ],
                      ],
                    ),
                    const SizedBox(height: 2),
                    Text(
                      item.message,
                      style: GoogleFonts.publicSans(fontSize: 13, color: AppColors.text2, height: 19 / 13),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              SizedBox(
                width: 20,
                child: Column(
                  children: [
                    if (unread)
                      Container(
                        width: 8,
                        height: 8,
                        margin: const EdgeInsets.only(top: 6),
                        decoration: const BoxDecoration(color: AppColors.accent, shape: BoxShape.circle),
                      ),
                    if (item.isLinkable)
                      Padding(
                        padding: EdgeInsets.only(top: unread ? 8 : 0),
                        child: const Icon(Icons.chevron_right_rounded, size: 20, color: AppColors.iconMuted),
                      ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
