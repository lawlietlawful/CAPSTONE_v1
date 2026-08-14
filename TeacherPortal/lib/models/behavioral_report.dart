import 'referral.dart';

/// A behavioral report the teacher has filed, as returned by
/// GET /api/teacher/behavioral-reports and the POST create response.
class BehavioralReport {
  final int id;
  final int studentId;
  final String? studentName;
  final String incidentType;
  final String severity;
  final String status;
  final String? incidentDate; // yyyy-MM-dd
  final String? location;
  final String description;
  final bool escalated;
  final String? createdAt; // ISO8601

  /// Only populated by GET /api/teacher/behavioral-reports/{id} (the detail
  /// endpoint). Null on list payloads even when [escalated] is true — use
  /// [escalated] for badges, this for the detail card.
  final Referral? escalatedReferral;

  /// The ML engine was unreachable when this was filed, so it carries no
  /// severity grade yet. Guidance re-runs the assessment later.
  bool get isUnassessed => severity.toLowerCase() == 'unassessed';

  const BehavioralReport({
    required this.id,
    required this.studentId,
    required this.studentName,
    required this.incidentType,
    required this.severity,
    required this.status,
    required this.incidentDate,
    required this.location,
    required this.description,
    required this.escalated,
    required this.createdAt,
    this.escalatedReferral,
  });

  factory BehavioralReport.fromJson(Map<String, dynamic> json) {
    final referral = json['escalated_referral'];
    return BehavioralReport(
      id: (json['id'] as num).toInt(),
      studentId: (json['student_id'] as num).toInt(),
      studentName: json['student_name']?.toString(),
      incidentType: json['incident_type']?.toString() ?? '',
      severity: json['severity']?.toString() ?? '',
      status: json['status']?.toString() ?? '',
      incidentDate: json['incident_date']?.toString(),
      location: json['location']?.toString(),
      description: json['description']?.toString() ?? '',
      escalated: json['escalated'] == true,
      createdAt: json['created_at']?.toString(),
      escalatedReferral: referral is Map<String, dynamic>
          ? Referral.fromJson(referral)
          : null,
    );
  }
}
