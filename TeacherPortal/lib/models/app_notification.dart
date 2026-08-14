/// An in-app notification for the teacher — a report escalating, or a counselor
/// changing the status of a referral they filed.
class AppNotification {
  final int id;
  final String title;
  final String message;
  final String type; // report_escalated | referral_status
  final String? referenceType; // currently always 'referral' when present
  final int? referenceId;
  final bool isRead;
  final String? createdAt;

  const AppNotification({
    required this.id,
    required this.title,
    required this.message,
    required this.type,
    this.referenceType,
    this.referenceId,
    required this.isRead,
    this.createdAt,
  });

  /// Whether tapping this notification can navigate somewhere.
  bool get isLinkable => referenceType == 'referral' && referenceId != null;

  factory AppNotification.fromJson(Map<String, dynamic> json) {
    return AppNotification(
      id: json['id'] as int,
      title: json['title']?.toString() ?? '',
      message: json['message']?.toString() ?? '',
      type: json['type']?.toString() ?? '',
      referenceType: json['reference_type']?.toString(),
      referenceId: json['reference_id'] as int?,
      isRead: json['is_read'] == true,
      createdAt: json['created_at']?.toString(),
    );
  }
}
