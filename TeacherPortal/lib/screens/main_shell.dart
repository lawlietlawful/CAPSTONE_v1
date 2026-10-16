import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../core/constants/app_colors.dart';
import '../providers/auth_provider.dart';
import '../providers/teacher_provider.dart';
import '../widgets/common/app_nav_bar.dart';
import 'dashboard/dashboard_screen.dart';
import 'log_incident/log_incident_screen.dart';
import 'messaging/messaging_screen.dart';
import 'notifications/notifications_screen.dart';
import 'profile/profile_screen.dart';
import 'referrals/file_referral_screen.dart';
import 'referrals/referrals_screen.dart';
import 'reports/my_reports_screen.dart';
import 'students/my_students_screen.dart';

class MainShell extends StatefulWidget {
  const MainShell({super.key});

  @override
  State<MainShell> createState() => _MainShellState();
}

class _MainShellState extends State<MainShell> with WidgetsBindingObserver {
  int _currentIndex = 0;

  /// How often to look for new notifications and messages while the app is
  /// open (live alerts). Paused while the app is in the background.
  static const pollEvery = Duration(seconds: 60);
  Timer? _poll;
  int? _seenNotifications;
  int? _seenMessages;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    // Restore the cached teacher profile (name/initials) for the headers.
    // Needed when the app boots straight to /home from a saved session.
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      if (!mounted) return;
      context.read<AuthProvider>().loadProfile();
      final provider = context.read<TeacherProvider>();
      // The first check sets what counts as "already seen", so opening the
      // app doesn't announce every old notification.
      await _checkForNew(announce: false);
      // Unsent reports from an earlier offline moment: load, then try them.
      await provider.loadDrafts();
      _sendDraftsQuietly();
      _startPolling();
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _poll?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _checkForNew();
      _sendDraftsQuietly();
      _startPolling();
    } else if (state == AppLifecycleState.paused || state == AppLifecycleState.hidden) {
      _poll?.cancel();
    }
  }

  void _startPolling() {
    _poll?.cancel();
    _poll = Timer.periodic(pollEvery, (_) => _checkForNew());
  }

  /// Refreshes the unread counts; when either went up, shows what arrived.
  Future<void> _checkForNew({bool announce = true}) async {
    final provider = context.read<TeacherProvider>();
    await Future.wait([provider.loadNotifications(), provider.loadMessages()]);
    if (!mounted) return;

    final notifications = provider.unreadNotifications;
    final messages = provider.unreadMessages;
    final newNotification = announce && _seenNotifications != null && notifications > _seenNotifications!;
    final newMessage = announce && _seenMessages != null && messages > _seenMessages!;
    _seenNotifications = notifications;
    _seenMessages = messages;

    if (newNotification) {
      final latest = provider.notifications.where((n) => !n.isRead).firstOrNull;
      _alert(
        icon: Icons.notifications_active_outlined,
        title: latest?.title ?? 'New notification',
        body: latest?.message,
        onView: () => Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => const NotificationsScreen()),
        ),
      );
    } else if (newMessage) {
      _alert(
        icon: Icons.mark_email_unread_outlined,
        title: 'New message from Guidance',
        onView: () => Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => const MessagingScreen()),
        ),
      );
    }
  }

  void _alert({required IconData icon, required String title, String? body, required VoidCallback onView}) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(
        key: const Key('live-alert'),
        behavior: SnackBarBehavior.floating,
        duration: const Duration(seconds: 6),
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

  Future<void> _sendDraftsQuietly() async {
    final provider = context.read<TeacherProvider>();
    if (provider.drafts.isEmpty) return;
    final sent = await provider.sendDrafts();
    if (sent > 0 && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text('$sent saved report${sent == 1 ? ' was' : 's were'} sent.'),
      ));
      provider.loadReports();
    }
  }

  void _onTap(int index) {
    setState(() => _currentIndex = index);
    // Re-sync from the server whenever a tab is opened, so a submission made
    // elsewhere (or by the auto-escalation pipeline) is reflected.
    final provider = context.read<TeacherProvider>();
    if (index == 0) provider.loadDashboard();
    if (index == 1) provider.loadRoster();
    if (index == 2) provider.loadReferrals();
    if (index == 3) provider.loadReports();
  }

  Future<void> _openLogIncident() async {
    await Navigator.of(
      context,
    ).push(MaterialPageRoute(builder: (_) => const LogIncidentScreen()));
    if (!mounted) return;
    // The report list is the tab most likely to be stale after logging.
    context.read<TeacherProvider>().loadReports();
  }

  Future<void> _openFileReferral() async {
    await Navigator.of(
      context,
    ).push(MaterialPageRoute(builder: (_) => const FileReferralScreen()));
    if (!mounted) return;
    context.read<TeacherProvider>().loadReferrals();
  }

  @override
  Widget build(BuildContext context) {
    // IndexedStack keeps all tabs alive — avoids re-fetching on every switch.
    final screens = [
      DashboardScreen(
        onNavigate: _onTap,
        onLogIncident: _openLogIncident,
        onFileReferral: _openFileReferral,
      ),
      const MyStudentsScreen(),
      ReferralsScreen(onFileReferral: _openFileReferral),
      const MyReportsScreen(),
      const ProfileScreen(),
    ];

    return AnnotatedRegion<SystemUiOverlayStyle>(
      // Dark status bar icons on the light page headers.
      value: SystemUiOverlayStyle.dark,
      child: Scaffold(
        backgroundColor: AppColors.background,
        body: IndexedStack(index: _currentIndex, children: screens),
        // One action that matters from any tab: reporting a concern. Messages
        // and notifications live in each header (HeaderActions).
        floatingActionButton: _currentIndex == 4
            ? null
            : FloatingActionButton.extended(
                heroTag: 'log_fab',
                onPressed: _openLogIncident,
                backgroundColor: AppColors.accent,
                foregroundColor: Colors.white,
                elevation: 3,
                highlightElevation: 4,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                icon: const Icon(Icons.edit_note_rounded, size: 22),
                label: Text(
                  'Report a concern',
                  style: GoogleFonts.publicSans(fontSize: 15, fontWeight: FontWeight.w600),
                ),
              ),
        bottomNavigationBar: AppNavBar(
          currentIndex: _currentIndex,
          onTap: _onTap,
        ),
      ),
    );
  }
}
