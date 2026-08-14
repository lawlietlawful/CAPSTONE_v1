import 'referral.dart';

/// Home-screen counters and the five most recent referrals, as returned by
/// GET /api/teacher/dashboard. Mirrors the web teacher dashboard.
class DashboardStats {
  final int totalStudents;
  final int myReferrals;
  final int pendingReferrals;
  final List<Referral> recentReferrals;

  const DashboardStats({
    required this.totalStudents,
    required this.myReferrals,
    required this.pendingReferrals,
    required this.recentReferrals,
  });

  factory DashboardStats.fromJson(Map<String, dynamic> json) {
    final recent = (json['recent_referrals'] as List? ?? []);
    return DashboardStats(
      totalStudents: (json['total_students'] as num?)?.toInt() ?? 0,
      myReferrals: (json['my_referrals'] as num?)?.toInt() ?? 0,
      pendingReferrals: (json['pending_referrals'] as num?)?.toInt() ?? 0,
      recentReferrals: recent
          .map((e) => Referral.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }

  /// The empty state a fresh screen renders before the first load resolves.
  static const empty = DashboardStats(
    totalStudents: 0,
    myReferrals: 0,
    pendingReferrals: 0,
    recentReferrals: [],
  );
}
