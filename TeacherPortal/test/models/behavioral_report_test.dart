import 'package:flutter_test/flutter_test.dart';
import 'package:teacher_portal/models/behavioral_report.dart';

void main() {
  Map<String, dynamic> baseJson({Map<String, dynamic>? overrides}) => {
        'id': 34,
        'student_id': 5,
        'student_name': 'Fernandez, Angelo',
        'incident_type': 'Academic Failure',
        'severity': 'High',
        'status': 'pending',
        'incident_date': '2026-09-20',
        'location': 'Room 101',
        'description': 'Failed to submit three consecutive assignments.',
        'escalated': true,
        'created_at': '2026-09-20T08:15:00Z',
        ...?overrides,
      };

  test('parses a list-payload report with no nested referral', () {
    final r = BehavioralReport.fromJson(baseJson());

    expect(r.id, 34);
    expect(r.incidentType, 'Academic Failure');
    expect(r.severity, 'High');
    expect(r.escalated, isTrue);
    expect(r.escalatedReferral, isNull, reason: 'only the detail endpoint nests it');
  });

  test('isUnassessed is true only for the Unassessed severity, case-insensitively', () {
    expect(BehavioralReport.fromJson(baseJson(overrides: {'severity': 'Unassessed'})).isUnassessed, isTrue);
    expect(BehavioralReport.fromJson(baseJson(overrides: {'severity': 'unassessed'})).isUnassessed, isTrue);
    expect(BehavioralReport.fromJson(baseJson(overrides: {'severity': 'High'})).isUnassessed, isFalse);
  });

  test('parses the nested escalated_referral from the detail endpoint', () {
    final r = BehavioralReport.fromJson(baseJson(overrides: {
      'escalated_referral': {
        'id': 72,
        'student_id': 5,
        'student_name': 'Fernandez, Angelo',
        'referral_type': 'Other',
        'referral_type_other': 'Repeated absences',
        'referral_type_label': 'Other — Repeated absences',
        'reason': 'Escalated automatically.',
        'priority': 'high',
        'status': 'pending',
        'counselor_name': null,
        'risk_level': 'high',
        'is_auto_escalated': true,
        'escalated_from_report_id': 34,
        'created_at': '2026-09-20T08:15:01Z',
        'resolved_at': null,
      },
    }));

    expect(r.escalatedReferral, isNotNull);
    expect(r.escalatedReferral!.id, 72);
    expect(r.escalatedReferral!.referralTypeLabel, 'Other — Repeated absences');
  });

  test('treats a non-map escalated_referral as absent rather than throwing', () {
    final r = BehavioralReport.fromJson(baseJson(overrides: {'escalated_referral': null}));
    expect(r.escalatedReferral, isNull);
  });

  test('defaults missing optional text fields instead of throwing', () {
    final r = BehavioralReport.fromJson(baseJson(overrides: {
      'location': null,
      'student_name': null,
    }));

    expect(r.location, isNull);
    expect(r.studentName, isNull);
    expect(r.description, isNotEmpty);
  });
}
