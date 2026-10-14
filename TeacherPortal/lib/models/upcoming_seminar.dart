/// A seminar in the next two weeks that some of the teacher's advisees are
/// enrolled in, from GET /api/teacher/seminars/upcoming — so the teacher
/// knows who will be out of class, and when.
class UpcomingSeminar {
  final int id;
  final String title;
  final String? date; // yyyy-MM-dd
  final String? time; // HH:mm:ss
  final String? venue;
  final bool isRequired;

  /// The teacher's advisees on the roster, "Last, First".
  final List<String> students;

  const UpcomingSeminar({
    required this.id,
    required this.title,
    this.date,
    this.time,
    this.venue,
    this.isRequired = false,
    this.students = const [],
  });

  factory UpcomingSeminar.fromJson(Map<String, dynamic> json) {
    return UpcomingSeminar(
      id: (json['id'] as num).toInt(),
      title: json['title']?.toString() ?? '',
      date: json['date']?.toString(),
      time: json['time']?.toString(),
      venue: json['venue']?.toString(),
      isRequired: json['is_required'] == true,
      students: (json['students'] as List? ?? [])
          .map((s) => (s as Map<String, dynamic>)['name']?.toString() ?? '')
          .where((n) => n.isNotEmpty)
          .toList(),
    );
  }

  /// The seminar's date and time together, when both are known.
  DateTime? get startsAt {
    if (date == null || date!.isEmpty) return null;
    return DateTime.tryParse('${date}T${(time == null || time!.isEmpty) ? '00:00:00' : time}');
  }
}
