import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:student_portal/core/services/api_service.dart';
import 'package:student_portal/models/referral_model.dart';
import 'package:student_portal/providers/notification_provider.dart';
import 'package:student_portal/providers/seminar_provider.dart';
import 'package:student_portal/providers/student_provider.dart';

void main() {
  tearDown(() => ApiService.client = http.Client());

  test('a referral holds only type, status, dates and counselor', () {
    final r = ReferralModel.fromJson({
      'id': 4, 'type_label': 'Absences', 'status': 'in_progress', 'counselor_name': 'Rosa Santos',
      'created_at': '2026-10-01T08:00:00Z',
      // Even if an old server sent these, the app has nowhere to keep them.
      'reason': 'What a teacher wrote', 'referred_by_name': 'Sir Santos', 'priority': 'high',
    });
    expect(r.typeLabel, 'Absences');
    expect(r.isActive, isTrue);
    expect(r.counselorName, 'Rosa Santos');
  });

  test('the providers never ask for a risk level, and reset forgets the student', () async {
    final requested = <String>[];
    ApiService.client = MockClient((request) async {
      requested.add(request.url.path);
      if (request.url.path.endsWith('/student/profile')) {
        return http.Response(jsonEncode({'student': {'id': 7, 'first_name': 'Ana', 'last_name': 'Cruz'}}), 200);
      }
      if (request.url.path.endsWith('/student/referrals')) {
        return http.Response(jsonEncode({'all_referrals': [
          {'id': 1, 'type_label': 'Absences', 'status': 'pending', 'created_at': '2026-10-01'},
        ]}), 200);
      }
      if (request.url.path.endsWith('/student/seminars')) {
        return http.Response(jsonEncode({'required': [
          {'id': 3, 'title': 'Study Habits', 'date': '2026-10-20', 'time': '09:00:00', 'venue': 'AVR',
           'is_required': true, 'assigned_by': 'manual', 'status': 'enrolled', 'session_status': 'upcoming'},
        ], 'completed': []}), 200);
      }
      return http.Response(jsonEncode({'unread_count': 2, 'notifications': []}), 200);
    });

    final student = StudentProvider();
    final seminars = SeminarProvider();
    final notifications = NotificationProvider();
    await Future.wait([student.fetchAll(), seminars.fetch(), notifications.fetch()]);

    expect(requested.any((p) => p.contains('risk')), isFalse);
    expect(student.student?.firstName, 'Ana');
    expect(student.activeReferral?.typeLabel, 'Absences');
    expect(seminars.required, hasLength(1));
    expect(notifications.unreadCount, 2);

    student.reset();
    seminars.reset();
    notifications.reset();

    expect(student.student, isNull);
    expect(student.referrals, isEmpty);
    expect(seminars.required, isEmpty);
    expect(notifications.unreadCount, 0);
  });

  test('a failed load shows a plain message, not a programmer error', () async {
    ApiService.client = MockClient((_) async => throw http.ClientException('offline'));
    final student = StudentProvider();

    await student.fetchAll();

    expect(student.error, contains("Can't reach the server"));
    expect(student.error, isNot(contains('ApiException')));
  });
}
