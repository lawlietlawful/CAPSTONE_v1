import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../../core/constants/app_colors.dart';
import '../../core/constants/app_text_styles.dart';
import '../../core/utils/date_utils.dart';
import '../../models/notification_model.dart';
import '../../providers/notification_provider.dart';
import '../../providers/student_provider.dart';
import '../../widgets/common/navy_header.dart';
import '../../widgets/common/student_profile_sheet.dart';
import '../../widgets/notifications/notification_item.dart';

class NotificationsScreen extends StatelessWidget {
  const NotificationsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final np = context.watch<NotificationProvider>();
    final initials = context.watch<StudentProvider>().student?.initials ?? '?';
    final subtitle = np.unreadCount > 0
        ? '${np.unreadCount} unread'
        : 'No new notifications';

    return Column(
      children: [
        NavyHeader(
          title: 'Notifications',
          subtitle: subtitle,
          avatarInitials: initials,
          onAvatarTap: () => StudentProfileSheet.show(context),
          trailing: np.unreadCount > 0
              ? _MarkAllReadButton(
                  onTap: () => context.read<NotificationProvider>().markAllRead(),
                )
              : null,
        ),
        Expanded(
          child: ColoredBox(
            color: AppColors.background,
            child: RefreshIndicator(
              onRefresh: () => context.read<NotificationProvider>().fetch(),
              color: AppColors.accent,
              child: _buildBody(context, np),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildBody(BuildContext context, NotificationProvider np) {
    if (np.isLoading && np.notifications.isEmpty) {
      return const CustomScrollView(
        physics: AlwaysScrollableScrollPhysics(),
        slivers: [
          SliverFillRemaining(
            hasScrollBody: false,
            child: Center(
              child: CircularProgressIndicator(color: AppColors.accent),
            ),
          ),
        ],
      );
    }

    if (np.error != null && np.notifications.isEmpty) {
      return CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          SliverFillRemaining(
            hasScrollBody: false,
            child: Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.wifi_off_rounded,
                      size: 40, color: AppColors.text3),
                  const SizedBox(height: 12),
                  Text('Could not load notifications',
                      style: AppTextStyles.sectionTitle),
                  const SizedBox(height: 4),
                  Text('Pull down to retry', style: AppTextStyles.meta),
                ],
              ),
            ),
          ),
        ],
      );
    }

    if (np.notifications.isEmpty) {
      return CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          SliverFillRemaining(
            hasScrollBody: false,
            child: Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.notifications_none_outlined,
                      size: 40, color: AppColors.text3),
                  const SizedBox(height: 12),
                  Text('No notifications yet', style: AppTextStyles.meta),
                ],
              ),
            ),
          ),
        ],
      );
    }

    final groups = _groupByDay(np.notifications);

    return ListView.builder(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
      itemCount: groups.length,
      itemBuilder: (context, groupIndex) {
        final group = groups[groupIndex];
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: EdgeInsets.only(top: groupIndex == 0 ? 0 : 12),
              child: Text(
                group.label,
                style: GoogleFonts.inter(
                  fontSize: 11,
                  fontWeight: FontWeight.w500,
                  color: AppColors.text3,
                ),
              ),
            ),
            const SizedBox(height: 8),
            ...group.items.map(
              (n) => Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: NotificationItem(
                  notification: n,
                  onTap: () =>
                      context.read<NotificationProvider>().markRead(n.id),
                ),
              ),
            ),
          ],
        );
      },
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

class _MarkAllReadButton extends StatelessWidget {
  final VoidCallback onTap;
  const _MarkAllReadButton({required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        decoration: BoxDecoration(
          color: const Color(0x1A3B82F6),
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: const Color(0x593B82F6)),
        ),
        child: Text(
          'Mark all read',
          style: GoogleFonts.inter(
            fontSize: 11,
            fontWeight: FontWeight.w500,
            color: AppColors.navyAccent,
          ),
        ),
      ),
    );
  }
}
