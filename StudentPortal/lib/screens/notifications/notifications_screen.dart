import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/constants/app_colors.dart';
import '../../core/constants/app_decor.dart';
import '../../core/constants/app_text_styles.dart';
import '../../core/utils/date_utils.dart';
import '../../models/notification_model.dart';
import '../../providers/notification_provider.dart';
import '../../widgets/common/messages_button.dart';
import '../../widgets/common/page_header.dart';
import '../../widgets/common/surfaces.dart';
import '../../widgets/notifications/notification_item.dart';
import '../../widgets/premium/motion.dart';
import '../../widgets/premium/skeleton.dart';

/// Everything the system has told the student, newest first and grouped by
/// day ("Today", "Yesterday", "Jun 25"). Tapping one marks it read.
class NotificationsScreen extends StatelessWidget {
  const NotificationsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final np = context.watch<NotificationProvider>();
    final subtitle = np.unreadCount > 0 ? '${np.unreadCount} unread' : 'All caught up';

    return Column(
      children: [
        PageHeader(
          title: 'Notifications',
          subtitle: subtitle,
          trailing: const HeaderActions(),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: () => context.read<NotificationProvider>().fetch(),
            color: AppColors.accent,
            child: _buildBody(context, np),
          ),
        ),
      ],
    );
  }

  Widget _buildBody(BuildContext context, NotificationProvider np) {
    if (np.isLoading && np.notifications.isEmpty) {
      return const SkeletonList(count: 5, leading: true, padding: EdgeInsets.fromLTRB(16, 4, 16, 16));
    }

    if (np.error != null && np.notifications.isEmpty) {
      return const _Message(
        icon: Icons.wifi_off_rounded,
        title: 'Could not load notifications',
        message: 'Pull down to try again.',
      );
    }

    if (np.notifications.isEmpty) {
      return const _Message(
        icon: Icons.notifications_none_outlined,
        title: 'No notifications yet',
        message: 'Seminar reminders and notices from Guidance will show up here.',
      );
    }

    final groups = _groupByDay(np.notifications);

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 32),
      children: [
        if (np.unreadCount > 0)
          Align(
            alignment: Alignment.centerRight,
            child: TextButton.icon(
              key: const Key('mark-all-read'),
              onPressed: () => context.read<NotificationProvider>().markAllRead(),
              icon: const Icon(Icons.done_all_rounded, size: 18),
              label: const Text('Mark all read'),
            ),
          )
        else
          const SizedBox(height: 8),
        for (var g = 0; g < groups.length; g++)
          FadeSlideIn(
            index: g,
            child: Padding(
              padding: EdgeInsets.only(top: g == 0 ? 0 : 18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  GroupLabel(groups[g].label),
                  CardGroup(
                    children: [
                      for (final n in groups[g].items)
                        NotificationItem(
                          key: ValueKey(n.id),
                          notification: n,
                          onTap: () => context.read<NotificationProvider>().markRead(n.id),
                        ),
                    ],
                  ),
                ],
              ),
            ),
          ),
      ],
    );
  }

  /// Groups a newest-first list into consecutive day buckets
  /// labelled "Today" / "Yesterday" / "Jun 25".
  List<_DayGroup> _groupByDay(List<NotificationModel> notifications) {
    final groups = <_DayGroup>[];
    for (final n in notifications) {
      final label = AppDateUtils.relativeDay(n.createdAt);
      if (groups.isEmpty || groups.last.label != label) {
        groups.add(_DayGroup(label));
      }
      groups.last.items.add(n);
    }
    return groups;
  }
}

class _DayGroup {
  final String label;
  final List<NotificationModel> items = [];
  _DayGroup(this.label);
}

class _Message extends StatelessWidget {
  final IconData icon;
  final String title;
  final String message;
  const _Message({required this.icon, required this.title, required this.message});

  @override
  Widget build(BuildContext context) {
    return CustomScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      slivers: [
        SliverFillRemaining(
          hasScrollBody: false,
          child: Center(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 36),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 56,
                    height: 56,
                    decoration: AppDecor.card(radius: 28),
                    child: Icon(icon, size: 26, color: AppColors.text3),
                  ),
                  const SizedBox(height: 14),
                  Text(title, textAlign: TextAlign.center, style: AppTextStyles.sectionHeading),
                  const SizedBox(height: 4),
                  Text(message, textAlign: TextAlign.center, style: AppTextStyles.meta.copyWith(fontSize: 13.5)),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }
}
