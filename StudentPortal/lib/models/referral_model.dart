class ReferralModel {
  final int id;
  final String concernType;
  final String reason;
  final String priority;
  final String status; // "pending" | "in_progress" | "resolved"
  final String referredByName;
  final String createdAt;

  const ReferralModel({
    required this.id,
    required this.concernType,
    required this.reason,
    required this.priority,
    required this.status,
    required this.referredByName,
    required this.createdAt,
  });

  bool get isActive => status == 'pending' || status == 'in_progress';

  factory ReferralModel.fromJson(Map<String, dynamic> json) {
    return ReferralModel(
      id: (json['id'] as num).toInt(),
      // Spec key is `concern_type`; keep `referral_type` as a fallback.
      concernType:
          (json['concern_type'] ?? json['referral_type']) as String? ?? '',
      reason: json['reason'] as String? ?? '',
      priority: json['priority'] as String? ?? '',
      status: json['status'] as String? ?? '',
      // Spec key is `referred_by_name`; kept for records, not shown to students.
      referredByName:
          (json['referred_by_name'] ?? json['referred_by']) as String? ?? '',
      createdAt: json['created_at'] as String? ?? '',
    );
  }
}
