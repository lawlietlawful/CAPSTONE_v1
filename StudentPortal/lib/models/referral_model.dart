/// One of the student's referrals, as the student is allowed to see it:
/// what kind, where it stands, when, and which counselor has it. What the
/// referrer wrote, who referred them and the priority stay with staff.
class ReferralModel {
  final int id;
  final String typeLabel;
  final String status; // "pending" | "in_progress" | "resolved" | "cancelled"
  final String? counselorName;
  final String createdAt;
  final String? resolvedAt;

  const ReferralModel({
    required this.id,
    required this.typeLabel,
    required this.status,
    this.counselorName,
    required this.createdAt,
    this.resolvedAt,
  });

  bool get isActive => status == 'pending' || status == 'in_progress';

  factory ReferralModel.fromJson(Map<String, dynamic> json) {
    return ReferralModel(
      id: (json['id'] as num).toInt(),
      typeLabel: json['type_label']?.toString() ?? '',
      status: json['status']?.toString() ?? '',
      counselorName: json['counselor_name']?.toString(),
      createdAt: json['created_at']?.toString() ?? '',
      resolvedAt: json['resolved_at']?.toString(),
    );
  }
}
