import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../core/constants/app_colors.dart';
import '../core/utils/date_utils.dart';
import '../models/seminar_model.dart';
import '../providers/notification_provider.dart';
import '../providers/seminar_provider.dart';
import '../providers/settings_provider.dart';
import '../widgets/common/app_nav_bar.dart';
import 'home/home_screen.dart';
import 'messaging/messaging_screen.dart';
import 'notifications/notifications_screen.dart';
import 'seminars/seminar_detail_screen.dart';
import 'seminars/seminars_screen.dart';

class MainShell extends StatefulWidget {
  const MainShell({super.key});

  @override
  State<MainShell> createState() => _MainShellState();
}

class _MainShellState extends State<MainShell> with WidgetsBindingObserver {
  int _currentIndex = 0;

  /// How often to look for new notifications and messages while the app is
  /// open. Paused while the app is in the background.
  static const pollEvery = Duration(seconds: 60);
  Timer? _poll;
  int? _seenNotifications;
  int? _seenMessages;
  bool _remindersChecked = false;
  SeminarProvider? _seminars;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      if (!mounted) return;
      // Seminar reminders run once Home's first load brings the seminars in.
      _seminars = context.read<SeminarProvider>()..addListener(_onSeminarsChanged);
      _onSeminarsChanged(); // in case they already arrived
      // The first check sets what counts as "already seen", so opening the
      // app doesn't announce every old notification.
      await _checkForNew(announce: false);
      _startPolling();
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _seminars?.removeListener(_onSeminarsChanged);
    _poll?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _checkForNew();
      _startPolling();
    } else if (state == AppLifecycleState.paused || state == AppLifecycleState.hidden) {
      _poll?.cancel();
    }
  }

  void _startPolling() {
    _poll?.cancel();
    _poll = Timer.periodic(pollEvery, (_) => _checkForNew());
  }

  void _onTap(int index) {
    setState(() => _currentIndex = index);
    if (index == 2) {
      // Refresh notification count when Alerts tab is opened.
      context.read<NotificationProvider>().fetch();
    }
  }

  /// Refreshes the unread counts (the badges always stay current). When
  /// either went up and "Alert notifications" is on, shows what arrived.
  Future<void> _checkForNew({bool announce = true}) async {
    final np = context.read<NotificationProvider>();
    final settings = context.read<SettingsProvider>();
    await Future.wait([np.fetch(), np.fetchUnreadMessages(), settings.ready]);
    if (!mounted) return;

    final alertsOn = settings.alertNotifications;
    final newNotification = announce && alertsOn && _seenNotifications != null && np.unreadCount > _seenNotifications!;
    final newMessage = announce && alertsOn && _seenMessages != null && np.unreadMessages > _seenMessages!;
    _seenNotifications = np.unreadCount;
    _seenMessages = np.unreadMessages;

    if (newNotification) {
      final latest = np.notifications.where((n) => !n.isRead).firstOrNull;
      _popUp(
        icon: Icons.notifications_active_outlined,
        title: latest?.title ?? 'New notification',
        body: latest?.message,
        onView: () => _onTap(2),
      );
    } else if (newMessage) {
      _popUp(
        icon: Icons.mark_email_unread_outlined,
        title: 'New message from Guidance',
        onView: () => Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => const MessagingScreen()),
        ),
      );
    }
  }

  void _onSeminarsChanged() {
    final semp = _seminars;
    if (semp == null || _remindersChecked || !semp.hasLoaded || !mounted) return;
    _remindersChecked = true;
    _remindSeminars(semp.required);
  }

  /// "Seminar reminders": the day before and the day of each upcoming
  /// seminar, opening the app shows a reminder — once per seminar per day.
  Future<void> _remindSeminars(List<SeminarModel> upcoming) async {
    final settings = context.read<SettingsProvider>();
    await settings.ready;
    if (!mounted || !settings.seminarReminders) return;
    final today = DateUtils.dateOnly(DateTime.now());
    final todayKey = today.toIso8601String().substring(0, 10);
    final prefs = await SharedPreferences.getInstance();
    final shown = prefs.getStringList(_remindedKey) ?? [];

    for (final seminar in upcoming) {
      final date = DateTime.tryParse(seminar.date);
      if (date == null) continue;
      final days = DateUtils.dateOnly(date).difference(today).inDays;
      if (days < 0 || days > 1) continue;
      final key = '${seminar.id}|$todayKey';
      if (shown.contains(key)) continue;

      await prefs.setStringList(_remindedKey, [...shown, key]);
      if (!mounted) return;
      _popUp(
        icon: Icons.event_available_outlined,
        title: '${seminar.title} is ${days == 0 ? 'today' : 'tomorrow'}',
        body: '${AppDateUtils.formatTime(seminar.time)} · ${seminar.venue}',
        onView: () => Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => SeminarDetailScreen(seminar: seminar)),
        ),
        key: const Key('seminar-reminder'),
      );
      return; // one reminder at a time
    }
  }

  static const _remindedKey = 'seminar_reminders_shown';

  void _popUp({
    required IconData icon,
    required String title,
    String? body,
    required VoidCallback onView,
    Key key = const Key('live-alert'),
  }) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(
        key: key,
        behavior: SnackBarBehavior.floating,
        duration: const Duration(seconds: 7),
        backgroundColor: AppColors.navy,
        content: Row(
          children: [
            Icon(icon, size: 18, color: AppColors.navyAccent),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(title, style: const TextStyle(fontWeight: FontWeight.w600)),
                  if (body != null && body.isNotEmpty)
                    Text(body, maxLines: 2, overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontSize: 12, color: AppColors.navySubText)),
                ],
              ),
            ),
          ],
        ),
        action: SnackBarAction(label: 'View', textColor: AppColors.navyAccent, onPressed: onView),
      ));
  }

  @override
  Widget build(BuildContext context) {
    final unread = context.watch<NotificationProvider>().unreadCount;

    return AnnotatedRegion<SystemUiOverlayStyle>(
      // Light (white) status bar icons work on all navy headers.
      value: SystemUiOverlayStyle.light,
      child: Scaffold(
        // Navy here bleeds into the status bar area so NavyHeader on each
        // screen appears seamless with the system status bar.
        backgroundColor: AppColors.navy,
        // IndexedStack keeps all screens alive — avoids re-fetching data on tab switch.
        body: IndexedStack(
          index: _currentIndex,
          children: [
            HomeScreen(onOpenTab: _onTap),
            const SeminarsScreen(),
            const NotificationsScreen(),
          ],
        ),
        bottomNavigationBar: AppNavBar(
          currentIndex: _currentIndex,
          onTap: _onTap,
          unreadCount: unread,
        ),
      ),
    );
  }
}
