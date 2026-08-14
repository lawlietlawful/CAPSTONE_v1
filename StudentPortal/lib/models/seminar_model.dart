class SeminarModel {
  final int id;
  final String title;
  final String? description;
  final String date;
  final String time;
  final String venue;
  final String? speaker;
  final bool isRequired;
  final String assignedBy; // "ml_system" | "manual"
  final String status;     // "enrolled" | "attended" | "missed"
  final String? attendedAt;

  const SeminarModel({
    required this.id,
    required this.title,
    this.description,
    required this.date,
    required this.time,
    required this.venue,
    this.speaker,
    required this.isRequired,
    required this.assignedBy,
    required this.status,
    this.attendedAt,
  });

  bool get isAiAssigned => assignedBy == 'ml_system';

  factory SeminarModel.fromJson(Map<String, dynamic> json) {
    return SeminarModel(
      id: (json['id'] as num).toInt(),
      title: json['title'] as String? ?? '',
      description: json['description'] as String?,
      date: json['date'] as String? ?? '',
      time: json['time'] as String? ?? '',
      venue: json['venue'] as String? ?? '',
      speaker: json['speaker'] as String?,
      isRequired: json['is_required'] as bool? ?? false,
      assignedBy: json['assigned_by'] as String? ?? 'manual',
      status: json['status'] as String? ?? 'enrolled',
      attendedAt: json['attended_at'] as String?,
    );
  }
}
