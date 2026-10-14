/// A guidance referral the teacher has filed, as returned by
/// GET /api/teacher/referrals, the POST create response, and nested inside a
/// behavioral report's `escalated_referral`.
class Referral {
  final int id;
  final int studentId;
  final String? studentName;
  final String referralType;
  final String? referralTypeOther;

  /// Server-rendered display text — already reads `Other — <text>` when the
  /// type is Other. Never rebuild this client-side; it would drift.
  final String referralTypeLabel;

  final String reason;
  final String priority;
  final String status;

  /// The counselor handling it (null until one is assigned) — lets the
  /// detail screen offer to message them.
  final int? counselorId;
  final String? counselorName;

  /// ML-assessed risk level for the student at the time of referral.
  /// Null when the ML engine was unreachable.
  final String? riskLevel;

  /// True when this referral was created automatically by escalating a
  /// behavioral report, rather than filed directly by the teacher.
  /// Server-computed — `reason` is already cleaned of the old
  /// "[AUTO-ESCALATED ...]" text marker, so this is the only reliable signal.
  final bool isAutoEscalated;

  /// The behavioral report this referral escalated from, when [isAutoEscalated].
  final int? escalatedFromReportId;

  final String? createdAt; // ISO8601
  final String? resolvedAt; // ISO8601

  /// What the teacher reported after Guidance resolved it, if anything.
  final ReferralFollowUp? followUp;

  bool get isResolved => status == 'resolved';

  const Referral({
    required this.id,
    required this.studentId,
    required this.studentName,
    required this.referralType,
    required this.referralTypeOther,
    required this.referralTypeLabel,
    required this.reason,
    required this.priority,
    required this.status,
    this.counselorId,
    required this.counselorName,
    required this.riskLevel,
    required this.isAutoEscalated,
    this.escalatedFromReportId,
    required this.createdAt,
    required this.resolvedAt,
    this.followUp,
  });

  factory Referral.fromJson(Map<String, dynamic> json) {
    return Referral(
      id: (json['id'] as num).toInt(),
      studentId: (json['student_id'] as num).toInt(),
      studentName: json['student_name']?.toString(),
      referralType: json['referral_type']?.toString() ?? '',
      referralTypeOther: json['referral_type_other']?.toString(),
      referralTypeLabel:
          json['referral_type_label']?.toString() ??
          json['referral_type']?.toString() ??
          '',
      reason: json['reason']?.toString() ?? '',
      priority: json['priority']?.toString() ?? '',
      status: json['status']?.toString() ?? '',
      counselorId: (json['counselor_id'] as num?)?.toInt(),
      counselorName: json['counselor_name']?.toString(),
      riskLevel: json['risk_level']?.toString(),
      isAutoEscalated: json['is_auto_escalated'] == true,
      escalatedFromReportId: (json['escalated_from_report_id'] as num?)
          ?.toInt(),
      createdAt: json['created_at']?.toString(),
      resolvedAt: json['resolved_at']?.toString(),
      followUp: json['follow_up'] is Map<String, dynamic>
          ? ReferralFollowUp.fromJson(json['follow_up'] as Map<String, dynamic>)
          : null,
    );
  }
}

/// The referring teacher's report on how the student is doing in class after
/// Guidance resolved the referral.
class ReferralFollowUp {
  /// improved | no_change | worse
  final String outcome;
  final String label;
  final String? note;
  final String? at; // ISO8601

  const ReferralFollowUp({
    required this.outcome,
    required this.label,
    this.note,
    this.at,
  });

  static const outcomes = {
    'improved': 'Improved',
    'no_change': 'No change',
    'worse': 'Worse',
  };

  factory ReferralFollowUp.fromJson(Map<String, dynamic> json) {
    final outcome = json['outcome']?.toString() ?? 'no_change';
    return ReferralFollowUp(
      outcome: outcome,
      label: json['label']?.toString() ?? outcomes[outcome] ?? outcome,
      note: json['note']?.toString(),
      at: json['at']?.toString(),
    );
  }
}
