import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:teacher_portal/core/services/api_service.dart';
import 'package:teacher_portal/providers/teacher_provider.dart';

void main() {
  tearDown(() => ApiService.client = http.Client());

  test('reset forgets the previous teacher, so a shared phone shows nothing of theirs', () async {
    ApiService.client = MockClient((request) async {
      if (request.url.path.endsWith('/teacher/dashboard')) {
        return http.Response(jsonEncode({
          'total_students': 42,
          'my_referrals': 7,
          'pending_referrals': 2,
          'recent_referrals': [],
        }), 200);
      }
      if (request.url.path.endsWith('/teacher/referrals')) {
        return http.Response(jsonEncode({
          'referrals': [],
          'pending_count': 3,
          'types': ['Absences', 'Other'],
          'meta': {'current_page': 1, 'last_page': 1, 'per_page': 20, 'total': 0, 'has_more': false},
        }), 200);
      }
      return http.Response(jsonEncode({
        'students': [
          {'id': 1, 'name': 'Cruz, Ana', 'school_id': '2026-0001', 'risk_level': 'high',
           'open_referrals': 1, 'reports_count': 2},
        ],
        'summary': {'high': 1, 'moderate': 0, 'low': 0, 'not_assessed': 0},
      }), 200);
    });

    final provider = TeacherProvider();
    await provider.loadDashboard();
    await provider.loadRoster();
    await provider.applyReferralFilter(search: 'cruz', status: 'pending');
    expect(provider.dashboard.totalStudents, 42);
    expect(provider.roster, isNotEmpty);
    expect(provider.pendingReferralCount, 3);

    var notified = false;
    provider.addListener(() => notified = true);
    provider.reset();

    expect(notified, isTrue);
    expect(provider.dashboard.totalStudents, 0);
    expect(provider.roster, isEmpty);
    expect(provider.riskSummary, isEmpty);
    expect(provider.referralSearch, isEmpty);
    expect(provider.referralFilterActive, isFalse);
    expect(provider.pendingReferralCount, 0);
  });
}
