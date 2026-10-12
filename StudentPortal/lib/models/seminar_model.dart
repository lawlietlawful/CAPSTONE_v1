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
  final String status;     // "enrolled" | "attended" | "missed" | "excused"
  final String sessionStatus; // the seminar itself: "upcoming" | "ongoing" | "completed" | "cancelled"
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
    this.sessionStatus = 'upcoming',
    this.attendedAt,
  });

  bool get isAiAssigned => assignedBy == 'ml_system';

  /// How a past seminar turned out for this student: "attended", "missed",
  /// "excused", "cancelled" (the session itself was called off), or
  /// "not_recorded" (it happened but attendance wasn't taken).
  String get outcome {
    if (sessionStatus == 'cancelled') return 'cancelled';
    if (status == 'enrolled') return 'not_recorded';
    return status;
  }

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
      sessionStatus: json['session_status'] as String? ?? 'upcoming',
      attendedAt: json['attended_at'] as String?,
    );
  }
}
