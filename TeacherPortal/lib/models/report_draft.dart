/// A behavioral report that couldn't be sent (no connection, server down),
/// kept on the phone until it can be. Holds exactly what the form posts.
class ReportDraft {
  /// Local id (creation time in ms) — drafts never reach the server as-is.
  final int id;
  final int studentId;
  final String studentName;
  final String incidentType;
  final String incidentDate; // yyyy-MM-dd
  final String? location;
  final String description;
  final String savedAt; // ISO8601

  const ReportDraft({
    required this.id,
    required this.studentId,
    required this.studentName,
    required this.incidentType,
    required this.incidentDate,
    this.location,
    required this.description,
    required this.savedAt,
  });

  Map<String, dynamic> toJson() => {
        'id': id,
        'student_id': studentId,
        'student_name': studentName,
        'incident_type': incidentType,
        'incident_date': incidentDate,
        'location': location,
        'description': description,
        'saved_at': savedAt,
      };

  factory ReportDraft.fromJson(Map<String, dynamic> json) {
    return ReportDraft(
      id: (json['id'] as num).toInt(),
      studentId: (json['student_id'] as num).toInt(),
      studentName: json['student_name']?.toString() ?? '',
      incidentType: json['incident_type']?.toString() ?? '',
      incidentDate: json['incident_date']?.toString() ?? '',
      location: json['location']?.toString(),
      description: json['description']?.toString() ?? '',
      savedAt: json['saved_at']?.toString() ?? '',
    );
  }
}
