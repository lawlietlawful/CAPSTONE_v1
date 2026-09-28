import 'package:flutter_test/flutter_test.dart';
import 'package:teacher_portal/models/referral.dart';

void main() {
  Map<String, dynamic> baseJson({Map<String, dynamic>? overrides}) => {
        'id': 72,
        'student_id': 5,
        'student_name': 'Fernandez, Angelo',
        'referral_type': 'Poor academic performance',
        'referral_type_other': null,
        'referral_type_label': 'Poor academic performance',
        'reason': 'Failing three major subjects this quarter.',
        'priority': 'high',
        'status': 'pending',
        'counselor_name': 'Ma\'am Edago',
        'risk_level': 'low',
        'is_auto_escalated': false,
        'escalated_from_report_id': null,
        'created_at': '2026-09-23T00:42:00Z',
        'resolved_at': null,
        ...?overrides,
      };

  test('parses every field from a full server payload', () {
    final r = Referral.fromJson(baseJson());

    expect(r.id, 72);
    expect(r.studentId, 5);
    expect(r.studentName, 'Fernandez, Angelo');
    expect(r.referralTypeLabel, 'Poor academic performance');
    expect(r.priority, 'high');
    expect(r.status, 'pending');
    expect(r.counselorName, 'Ma\'am Edago');
    expect(r.isAutoEscalated, isFalse);
    expect(r.resolvedAt, isNull);
  });

  test('falls back to referral_type when referral_type_label is missing', () {
    final r = Referral.fromJson(
      baseJson(overrides: {'referral_type_label': null}),
    );

    expect(r.referralTypeLabel, 'Poor academic performance');
  });

  test('reads is_auto_escalated and the source report id together', () {
    final r = Referral.fromJson(baseJson(overrides: {
      'is_auto_escalated': true,
      'escalated_from_report_id': 26,
    }));

    expect(r.isAutoEscalated, isTrue);
    expect(r.escalatedFromReportId, 26);
  });

  test('tolerates a wholly unassigned/unassessed referral', () {
    final r = Referral.fromJson(baseJson(overrides: {
      'counselor_name': null,
      'risk_level': null,
      'student_name': null,
    }));

    expect(r.counselorName, isNull);
    expect(r.riskLevel, isNull);
    expect(r.studentName, isNull);
  });
}
