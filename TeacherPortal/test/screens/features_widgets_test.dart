import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:teacher_portal/core/services/api_service.dart';
import 'package:teacher_portal/providers/auth_provider.dart';
import 'package:teacher_portal/providers/teacher_provider.dart';
import 'package:teacher_portal/screens/main_shell.dart';
import 'package:teacher_portal/screens/students/my_students_screen.dart';
import 'package:teacher_portal/widgets/common/duplicate_referral_dialog.dart';
import 'package:teacher_portal/widgets/common/follow_up_card.dart';

Widget _wrap(Widget child) => MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AuthProvider()),
        ChangeNotifierProvider(create: (_) => TeacherProvider()),
      ],
      child: MaterialApp(home: child),
    );

Map<String, dynamic> _student(int id, String name, String risk, String schoolId) => {
      'id': id, 'name': name, 'school_id': schoolId, 'education_level': 'College',
      'year_level': '1st Year', 'section': 'Block 1', 'risk_level': risk,
      'open_referrals': 0, 'reports_count': 0,
    };

void main() {
  setUp(() => SharedPreferences.setMockInitialValues({}));
  tearDown(() => ApiService.client = http.Client());

  testWidgets('My Students can be searched and filtered by risk', (tester) async {
    ApiService.client = MockClient((_) async => http.Response(jsonEncode({
          'students': [
            _student(1, 'Cruz, Ana', 'high', '2026-0001'),
            _student(2, 'Reyes, Ben', 'moderate', '2026-0002'),
            _student(3, 'Santos, Carla', 'low', '2026-0003'),
            _student(4, 'Cruz, Dino', 'not_assessed', '2026-0004'),
          ],
          'summary': {'high': 1, 'moderate': 1, 'low': 1, 'not_assessed': 1},
        }), 200));

    await tester.pumpWidget(_wrap(const Scaffold(body: MyStudentsScreen())));
    await tester.pumpAndSettle();
    expect(find.text('Cruz, Ana'), findsOneWidget);
    expect(find.text('Santos, Carla'), findsOneWidget);

    await tester.enterText(find.byKey(const Key('roster-search')), 'cruz');
    await tester.pump();
    expect(find.text('Cruz, Ana'), findsOneWidget);
    expect(find.text('Cruz, Dino'), findsOneWidget);
    expect(find.text('Reyes, Ben'), findsNothing);

    await tester.enterText(find.byKey(const Key('roster-search')), 'cruz ana');
    await tester.pump();
    expect(find.text('Cruz, Dino'), findsNothing);

    await tester.enterText(find.byKey(const Key('roster-search')), '');
    await tester.tap(find.byKey(const Key('risk-chip-at_risk')));
    await tester.pump();
    expect(find.text('Cruz, Ana'), findsOneWidget);
    expect(find.text('Reyes, Ben'), findsOneWidget);
    expect(find.text('Santos, Carla'), findsNothing);

    await tester.enterText(find.byKey(const Key('roster-search')), 'nobody');
    await tester.pump();
    expect(find.text('No students match'), findsOneWidget);
    await tester.tap(find.text('Clear filters'));
    await tester.pump();
    expect(find.text('Santos, Carla'), findsOneWidget);
  });

  testWidgets('the follow-up card needs an outcome, then sends it', (tester) async {
    late Map<String, dynamic> sent;
    var saved = false;
    ApiService.client = MockClient((request) async {
      sent = jsonDecode(request.body) as Map<String, dynamic>;
      return http.Response(jsonEncode({'referral': {
        'id': 5, 'student_id': 7, 'referral_type': 'Absences', 'reason': 'x', 'priority': 'low', 'status': 'resolved',
        'follow_up': {'outcome': 'improved', 'label': 'Improved'},
      }}), 200);
    });

    await tester.pumpWidget(_wrap(Scaffold(
      body: FollowUpCard(referralId: 5, studentName: 'Cruz, Ana', followUp: null, onSaved: () => saved = true),
    )));
    expect(find.textContaining('How is Ana doing'), findsOneWidget);

    await tester.tap(find.byKey(const Key('send-follow-up')));
    await tester.pump();
    expect(find.text('Choose how the student is doing.'), findsOneWidget);

    await tester.tap(find.text('Improved'));
    await tester.enterText(find.byType(TextField), 'Attending every class.');
    await tester.tap(find.byKey(const Key('send-follow-up')));
    await tester.pumpAndSettle();

    expect(sent, {'outcome': 'improved', 'note': 'Attending every class.'});
    expect(saved, isTrue);
  });

  testWidgets("the duplicate warning explains the open referral and lets the teacher choose", (tester) async {
    DuplicateChoice? choice;
    await tester.pumpWidget(_wrap(Builder(
      builder: (context) => Scaffold(
        body: TextButton(
          onPressed: () async => choice = await showDuplicateReferralDialog(context,
              studentName: 'Cruz, Ana',
              existing: {
                'mine': false, 'referral_type_label': 'Absences', 'status': 'in_progress',
                'created_at': '2026-10-01T08:00:00Z', 'counselor_id': 9, 'counselor_name': 'Rosa Santos',
              }),
          child: const Text('open'),
        ),
      ),
    )));

    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();
    expect(find.text('Cruz, Ana already has an open referral with Guidance.'), findsOneWidget);
    expect(find.textContaining('Filed by another staff member'), findsOneWidget);
    expect(find.text('Handled by Rosa Santos'), findsOneWidget);

    await tester.tap(find.text('File anyway'));
    await tester.pumpAndSettle();
    expect(choice, DuplicateChoice.fileAnyway);
  });

  testWidgets('a new notification while the app is open pops up a live alert', (tester) async {
    var unread = 1;
    ApiService.setToken('t');
    ApiService.client = MockClient((request) async {
      final path = request.url.path;
      if (path.endsWith('/teacher/notifications')) {
        return http.Response(jsonEncode({
          'unread_count': unread,
          'notifications': [
            {'id': unread, 'title': unread > 1 ? 'Referral update' : 'Old one',
             'message': 'Your referral for Ana Cruz has been resolved.', 'type': 'referral_status', 'is_read': false},
          ],
        }), 200);
      }
      if (path.endsWith('/messages')) return http.Response(jsonEncode({'data': [], 'unread_count': 0}), 200);
      return http.Response('{}', 200);
    });

    await tester.pumpWidget(_wrap(const MainShell()));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('live-alert')), findsNothing, reason: 'nothing new on opening the app');

    unread = 2;
    await tester.pump(const Duration(seconds: 61));
    await tester.pump();
    expect(find.byKey(const Key('live-alert')), findsOneWidget);
    expect(find.text('Referral update'), findsOneWidget);

    ApiService.clearToken();
    await tester.pumpWidget(const SizedBox()); // dispose the shell's timer
  });
}
