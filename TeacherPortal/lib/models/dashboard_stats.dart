import 'referral.dart';

/// Home-screen counters and the five most recent referrals, as returned by
/// GET /api/teacher/dashboard. Mirrors the web teacher dashboard.
class DashboardStats {
  final int totalStudents;
  final int myReferrals;
  final int pendingReferrals;
  final List<Referral> recentReferrals;

  /// The teacher's Employee ID and the classes they advise ("BSIT · 1st Year
  /// · Block 1"), for the Profile tab.
  final String? employeeId;
  final List<String> advisory;

  const DashboardStats({
    required this.totalStudents,
    required this.myReferrals,
    required this.pendingReferrals,
    required this.recentReferrals,
    this.employeeId,
    this.advisory = const [],
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
      employeeId: json['employee_id']?.toString(),
      advisory: (json['advisory'] as List? ?? []).map((e) => e.toString()).toList(),
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
