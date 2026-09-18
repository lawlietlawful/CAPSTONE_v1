import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../core/constants/app_colors.dart';
import '../providers/auth_provider.dart';
import '../providers/teacher_provider.dart';
import '../widgets/common/app_nav_bar.dart';
import 'dashboard/dashboard_screen.dart';
import 'log_incident/log_incident_screen.dart';
import 'profile/profile_screen.dart';
import 'referrals/file_referral_screen.dart';
import 'referrals/referrals_screen.dart';
import 'reports/my_reports_screen.dart';
import 'students/my_students_screen.dart';
import 'messaging/messaging_screen.dart' as messaging_screen;

class MainShell extends StatefulWidget {
  const MainShell({super.key});

  @override
  State<MainShell> createState() => _MainShellState();
}

class _MainShellState extends State<MainShell> {
  int _currentIndex = 0;

  @override
  void initState() {
    super.initState();
    // Restore the cached teacher profile (name/initials) for the headers.
    // Needed when the app boots straight to /home from a saved session.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) context.read<AuthProvider>().loadProfile();
    });
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
      // Light (white) status bar icons work on all navy headers.
      value: SystemUiOverlayStyle.light,
      child: Scaffold(
        // Navy bleeds into the status bar so NavyHeader looks seamless.
        backgroundColor: AppColors.navy,
        body: IndexedStack(index: _currentIndex, children: screens),
        // Two FABs stacked: Messages on top, Log Incident on bottom.
        floatingActionButton: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            FloatingActionButton(
              heroTag: 'msg_fab',
              onPressed: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const messaging_screen.MessagingScreen()),
                );
              },
              backgroundColor: Colors.white,
              foregroundColor: AppColors.accent,
              elevation: 2,
              tooltip: 'Messages',
              child: const Icon(Icons.mail_outline_rounded, size: 24),
            ),
            const SizedBox(height: 12),
            FloatingActionButton(
              heroTag: 'log_fab',
              onPressed: _openLogIncident,
              backgroundColor: AppColors.accent,
              foregroundColor: Colors.white,
              elevation: 2,
              tooltip: 'Log Incident',
              child: const Icon(Icons.edit_note_rounded, size: 24),
            ),
          ],
        ),
        bottomNavigationBar: AppNavBar(
          currentIndex: _currentIndex,
          onTap: _onTap,
        ),
      ),
    );
  }
}
