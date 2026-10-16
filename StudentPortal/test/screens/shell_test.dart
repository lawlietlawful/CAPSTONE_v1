import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:student_portal/core/services/api_service.dart';
import 'package:student_portal/providers/auth_provider.dart';
import 'package:student_portal/providers/notification_provider.dart';
import 'package:student_portal/providers/seminar_provider.dart';
import 'package:student_portal/providers/settings_provider.dart';
import 'package:student_portal/providers/student_provider.dart';
import 'package:student_portal/screens/main_shell.dart';

Widget _app() => MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AuthProvider()),
        ChangeNotifierProvider(create: (_) => StudentProvider()),
        ChangeNotifierProvider(create: (_) => SeminarProvider()),
        ChangeNotifierProvider(create: (_) => NotificationProvider()),
        ChangeNotifierProvider(create: (_) => SettingsProvider()),
      ],
      child: const MaterialApp(home: MainShell()),
    );

String _day(int fromToday) =>
    DateTime.now().add(Duration(days: fromToday)).toIso8601String().substring(0, 10);

/// A fake server: one student, one seminar tomorrow, and unread counts the
/// test can raise.
class _Server {
  int unreadNotifications = 1;
  int unreadMessages = 0;
  String seminarDate = _day(1);

  late final client = MockClient((request) async {
    final path = request.url.path;
    if (path.endsWith('/student/profile')) {
      return _json({'student': {'id': 1, 'first_name': 'Ana', 'last_name': 'Cruz'}});
    }
    if (path.endsWith('/student/referrals')) return _json({'all_referrals': []});
    if (path.endsWith('/student/seminars')) {
      return _json({'required': [
        {'id': 9, 'title': 'Study Habits', 'date': seminarDate, 'time': '09:00:00', 'venue': 'AVR',
         'is_required': true, 'assigned_by': 'manual', 'status': 'enrolled', 'session_status': 'upcoming'},
      ], 'completed': []});
    }
    if (path.endsWith('/student/notifications')) {
      return _json({
        'unread_count': unreadNotifications,
        'notifications': [
          {'id': unreadNotifications, 'title': unreadNotifications > 1 ? 'Seminar update' : 'Old one',
           'message': 'Study Habits moved to the AVR.', 'type': 'seminar', 'is_read': false,
           'created_at': DateTime.now().toIso8601String()},
        ],
      });
    }
    if (path.endsWith('/messages')) return _json({'data': [], 'unread_count': unreadMessages});
    return _json({});
  });

  static http.Response _json(Object body) => http.Response(jsonEncode(body), 200);
}

void main() {
  late _Server server;

  setUp(() {
    FlutterSecureStorage.setMockInitialValues({});
    server = _Server();
    ApiService.setToken('t');
    ApiService.client = server.client;
  });

  tearDown(() {
    ApiService.clearToken();
    ApiService.client = http.Client();
  });

  Future<void> dispose(WidgetTester tester) async {
    await tester.pumpWidget(const SizedBox()); // stop the shell's timer
    await tester.pump(const Duration(seconds: 8));
  }

  testWidgets('Messages lives in the header with an unread badge, no floating button', (tester) async {
    SharedPreferences.setMockInitialValues({'seminar_reminders_shown': ['9|${_day(0)}']});
    server.unreadMessages = 2;
    await tester.pumpWidget(_app());
    await tester.pumpAndSettle();

    expect(find.byType(FloatingActionButton), findsNothing);
    expect(find.byTooltip('Messages from Guidance'), findsWidgets);
    expect(find.text('2'), findsWidgets);
    await dispose(tester);
  });

  testWidgets('a new notice pops up while the app is open', (tester) async {
    SharedPreferences.setMockInitialValues({'seminar_reminders_shown': ['9|${_day(0)}']});
    await tester.pumpWidget(_app());
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('live-alert')), findsNothing, reason: 'nothing new on opening');

    server.unreadNotifications = 2;
    await tester.pump(const Duration(seconds: 61));
    await tester.pump();
    expect(find.byKey(const Key('live-alert')), findsOneWidget);
    // Home's "Latest alerts" lists it too, so look inside the pop-up.
    expect(
      find.descendant(of: find.byKey(const Key('live-alert')), matching: find.text('Seminar update')),
      findsOneWidget,
    );
    await dispose(tester);
  });

  testWidgets('with "Alert notifications" off, nothing pops up but the badge still updates', (tester) async {
    SharedPreferences.setMockInitialValues({
      'pref_alert_notifications': false,
      'seminar_reminders_shown': ['9|${_day(0)}'],
    });
    await tester.pumpWidget(_app());
    await tester.pumpAndSettle();

    server.unreadNotifications = 3;
    await tester.pump(const Duration(seconds: 61));
    await tester.pump();
    expect(find.byKey(const Key('live-alert')), findsNothing);
    expect(find.text('3'), findsWidgets, reason: 'the Alerts badge counts it');
    await dispose(tester);
  });

  testWidgets('a seminar tomorrow gets one reminder, not again the same day', (tester) async {
    SharedPreferences.setMockInitialValues({});
    await tester.pumpWidget(_app());
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('seminar-reminder')), findsOneWidget);
    expect(find.text('Study Habits is tomorrow'), findsOneWidget);
    await dispose(tester);

    // Opening the app again the same day: no repeat.
    await tester.pumpWidget(_app());
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('seminar-reminder')), findsNothing);
    await dispose(tester);
  });

  testWidgets('a seminar next week gets no reminder yet', (tester) async {
    SharedPreferences.setMockInitialValues({});
    server.seminarDate = _day(6);
    await tester.pumpWidget(_app());
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('seminar-reminder')), findsNothing);
    await dispose(tester);
  });

  testWidgets('with "Seminar reminders" off, no reminder', (tester) async {
    SharedPreferences.setMockInitialValues({'pref_seminar_reminders': false});
    await tester.pumpWidget(_app());
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('seminar-reminder')), findsNothing);
    await dispose(tester);
  });
}
