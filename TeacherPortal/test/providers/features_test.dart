import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:teacher_portal/core/services/api_service.dart';
import 'package:teacher_portal/core/services/draft_store.dart';
import 'package:teacher_portal/models/referral.dart';
import 'package:teacher_portal/models/report_draft.dart';
import 'package:teacher_portal/models/upcoming_seminar.dart';
import 'package:teacher_portal/providers/teacher_provider.dart';

ReportDraft _draft(int id, {String name = 'Cruz, Ana'}) => ReportDraft(
      id: id,
      studentId: 7,
      studentName: name,
      incidentType: 'Truancy',
      incidentDate: '2026-10-14',
      location: 'Room 101',
      description: 'Left after first period.',
      savedAt: '2026-10-14T09:00:00',
    );

Map<String, dynamic> _report(int id) => {
      'id': id, 'student_id': 7, 'student_name': 'Cruz, Ana', 'incident_type': 'Truancy',
      'severity': 'Low', 'status': 'pending', 'incident_date': '2026-10-14',
      'location': 'Room 101', 'description': 'Left after first period.', 'escalated': false,
    };

void main() {
  setUp(() => SharedPreferences.setMockInitialValues({}));
  tearDown(() => ApiService.client = http.Client());

  group('report drafts', () {
    test('a saved draft survives an app restart', () async {
      final provider = TeacherProvider();
      await provider.saveDraft(_draft(1));

      final restarted = TeacherProvider();
      await restarted.loadDrafts();
      expect(restarted.drafts.single.studentName, 'Cruz, Ana');
      expect(restarted.drafts.single.description, 'Left after first period.');
    });

    test('drafts are sent in order once there is a connection', () async {
      final posted = <String>[];
      ApiService.client = MockClient((request) async {
        if (request.method == 'POST') {
          posted.add((jsonDecode(request.body) as Map)['description'] as String);
          return http.Response(jsonEncode({'report': _report(posted.length)}), 201);
        }
        return http.Response('{}', 200);
      });
      final provider = TeacherProvider();
      await provider.saveDraft(_draft(1));
      await provider.saveDraft(_draft(2));

      expect(await provider.sendDrafts(), 2);
      expect(posted, hasLength(2));
      expect(provider.drafts, isEmpty);
      expect(await DraftStore.load(), isEmpty, reason: 'sent drafts leave the phone');
    });

    test('still offline: drafts stay put', () async {
      ApiService.client = MockClient((_) async => throw http.ClientException('offline'));
      final provider = TeacherProvider();
      await provider.saveDraft(_draft(1));

      expect(await provider.sendDrafts(), 0);
      expect(provider.drafts, hasLength(1));
      expect(provider.draftErrors, isEmpty);
    });

    test('a draft the server refuses stays, with the reason', () async {
      ApiService.client = MockClient((_) async => http.Response(
          jsonEncode({'message': 'You are not assigned to this student.'}), 403));
      final provider = TeacherProvider();
      await provider.saveDraft(_draft(1));

      await provider.sendDrafts();
      expect(provider.drafts, hasLength(1));
      expect(provider.draftErrors[1], 'You are not assigned to this student.');
    });

    test('discarding a draft removes it from the phone', () async {
      final provider = TeacherProvider();
      await provider.saveDraft(_draft(1));
      await provider.saveDraft(_draft(2));
      await provider.discardDraft(1);

      expect((await DraftStore.load()).map((d) => d.id), [2]);
    });

    test('reset forgets drafts in memory (sign-out clears the stored ones)', () async {
      final provider = TeacherProvider();
      await provider.saveDraft(_draft(1));
      provider.reset();
      expect(provider.drafts, isEmpty);
    });
  });

  test('a duplicate referral comes back as a 409 carrying the existing one', () async {
    ApiService.client = MockClient((_) async => http.Response(jsonEncode({
          'message': 'This student already has an open referral with Guidance.',
          'duplicate': true,
          'existing': {'id': 4, 'mine': false, 'referral_type_label': 'Absences', 'status': 'pending',
                       'counselor_id': 9, 'counselor_name': 'Rosa Santos'},
        }), 409));

    await expectLater(
      TeacherProvider().submitReferral(studentId: 7, referralType: 'Tardiness', reason: 'Late.'),
      throwsA(isA<ApiException>()
          .having((e) => e.statusCode, 'status', 409)
          .having((e) => e.data?['existing']['counselor_name'], 'existing', 'Rosa Santos')),
    );
  });

  test('confirming a duplicate sends confirm_duplicate', () async {
    late Map<String, dynamic> body;
    ApiService.client = MockClient((request) async {
      body = jsonDecode(request.body) as Map<String, dynamic>;
      return http.Response(jsonEncode({'referral': {
        'id': 5, 'student_id': 7, 'referral_type': 'Tardiness', 'reason': 'Late.',
        'priority': 'low', 'status': 'pending',
      }}), 201);
    });

    await TeacherProvider().submitReferral(
        studentId: 7, referralType: 'Tardiness', reason: 'Late.', confirmDuplicate: true);
    expect(body['confirm_duplicate'], isTrue);
  });

  test('upcoming seminars and follow-ups parse from the API', () {
    final s = UpcomingSeminar.fromJson({
      'id': 1, 'title': 'Study Habits', 'date': '2026-10-17', 'time': '13:30:00',
      'venue': 'AVR', 'is_required': true,
      'students': [{'id': 7, 'name': 'Cruz, Ana'}, {'id': 8, 'name': 'Reyes, Ben'}],
    });
    expect(s.students, ['Cruz, Ana', 'Reyes, Ben']);
    expect(s.startsAt, DateTime(2026, 10, 17, 13, 30));

    final r = Referral.fromJson({
      'id': 5, 'student_id': 7, 'referral_type': 'Absences', 'reason': 'x', 'priority': 'low',
      'status': 'resolved', 'follow_up': {'outcome': 'improved', 'label': 'Improved', 'note': 'Better now.'},
    });
    expect(r.isResolved, isTrue);
    expect(r.followUp!.outcome, 'improved');
    expect(r.followUp!.note, 'Better now.');
  });
}
