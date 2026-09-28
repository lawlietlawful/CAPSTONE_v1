import 'package:flutter_test/flutter_test.dart';
import 'package:teacher_portal/widgets/common/status_badges.dart';

/// AppBadge.status used to title-case a raw enum by upper-casing only its
/// first letter, so "in_progress" rendered as "In_progress" everywhere a
/// referral's status appeared (the Referrals list, the Referral detail
/// header, the Dashboard's recent-referral cards). It now splits on
/// underscores too.
void main() {
  group('AppBadge.status', () {
    test('title-cases a multi-word status instead of leaving the underscore',
        () {
      expect(AppBadge.status('in_progress').label, 'In Progress');
    });

    test('capitalizes a single-word status', () {
      expect(AppBadge.status('pending').label, 'Pending');
      expect(AppBadge.status('cancelled').label, 'Cancelled');
    });

    test('gives resolved and reviewed their own explicit labels', () {
      expect(AppBadge.status('resolved').label, 'Resolved');
      expect(AppBadge.status('reviewed').label, 'Reviewed');
    });

    test('falls back to "Pending" for an empty status', () {
      expect(AppBadge.status('').label, 'Pending');
    });
  });

  group('AppBadge.severity', () {
    test('never renders an unassessed report as a harmless "Low"', () {
      final badge = AppBadge.severity('Unassessed');
      expect(badge.label, 'Pending AI');
      expect(badge.icon, isNotNull);
    });

    test('treats High and Critical the same way', () {
      expect(AppBadge.severity('High').label, 'High');
      expect(AppBadge.severity('Critical').label, 'Critical');
    });
  });

  group('AppBadge.priority', () {
    test('capitalizes and colors by priority band', () {
      expect(AppBadge.priority('high').label, 'High');
      expect(AppBadge.priority('moderate').label, 'Moderate');
      expect(AppBadge.priority('').label, 'Low');
    });
  });

  group('AppBadge.risk', () {
    test('labels an unknown level as not assessed', () {
      expect(AppBadge.risk('not_assessed').label, 'Not assessed');
      expect(AppBadge.risk('').label, 'Not assessed');
    });

    test('labels a high-risk student distinctly from moderate/low', () {
      expect(AppBadge.risk('high').label, 'High risk');
      expect(AppBadge.risk('low').label, 'Low risk');
    });
  });
}
