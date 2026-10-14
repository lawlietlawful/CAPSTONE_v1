import 'package:flutter_test/flutter_test.dart';
import 'package:teacher_portal/models/referral_detail.dart';
import 'package:teacher_portal/models/student_detail.dart';

void main() {
  test("another teacher's timeline entry is a summary, not theirs to open", () {
    final theirs = TimelineItem.fromJson({
      'kind': 'report', 'id': 9, 'mine': false, 'title': 'Disciplinary Incident',
      'detail': null, 'status': 'reviewed', 'date': '2026-10-01',
    });
    final mine = TimelineItem.fromJson({
      'kind': 'report', 'id': 10, 'mine': true, 'title': 'Truancy',
      'detail': 'Skipped my class twice.', 'status': 'pending',
    });

    expect(theirs.mine, isFalse);
    expect(theirs.detail, isEmpty);
    expect(mine.mine, isTrue);
    expect(mine.detail, 'Skipped my class twice.');
  });

  test('a referral carries who handles it, for the "Message counselor" button', () {
    final detail = ReferralDetail.fromJson({
      'referral': {
        'id': 1, 'student_id': 2, 'student_name': 'Cruz, Ana', 'referral_type': 'Absences',
        'referral_type_label': 'Absences', 'reason': 'x', 'priority': 'medium',
        'status': 'in_progress', 'counselor_id': 7, 'counselor_name': 'Rosa Santos',
      },
      'journey': [],
    });

    expect(detail.referral.counselorId, 7);
    expect(detail.referral.counselorName, 'Rosa Santos');
  });
}
