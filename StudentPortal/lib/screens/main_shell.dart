import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import '../core/constants/app_colors.dart';
import '../providers/notification_provider.dart';
import '../widgets/common/app_nav_bar.dart';
import 'home/home_screen.dart';
import 'seminars/seminars_screen.dart';
import 'notifications/notifications_screen.dart';
import 'messaging/messaging_screen.dart' as messaging_screen;

class MainShell extends StatefulWidget {
  const MainShell({super.key});

  @override
  State<MainShell> createState() => _MainShellState();
}

class _MainShellState extends State<MainShell> {
  int _currentIndex = 0;

  // IndexedStack keeps all screens alive — avoids re-fetching data on tab switch.
  static const _screens = [
    HomeScreen(),
    SeminarsScreen(),
    NotificationsScreen(),
  ];

  void _onTap(int index) {
    setState(() => _currentIndex = index);
    if (index == 2) {
      // Refresh notification count when Alerts tab is opened.
      context.read<NotificationProvider>().fetch();
    }
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
        body: IndexedStack(
          index: _currentIndex,
          children: _screens,
        ),
        floatingActionButton: FloatingActionButton(
          onPressed: () {
            Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const messaging_screen.MessagingScreen()),
            );
          },
          backgroundColor: Colors.white,
          foregroundColor: AppColors.accent,
          elevation: 2,
          tooltip: 'Counselor Notices',
          child: const Icon(Icons.mail_outline_rounded, size: 24),
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
