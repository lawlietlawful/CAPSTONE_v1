import 'roster_student.dart';

/// A single item in a student's case history — either a behavioral report or a
/// guidance referral, unified so the detail screen can render one timeline.
class TimelineItem {
  final String kind; // 'report' | 'referral'
  final int id;
  final String title;
  final String detail;
  final String status;
  final String? severity; // reports only
  final String? priority; // referrals only
  final bool escalated; // report escalated into a referral
  final bool auto; // referral was auto-created from a report
  final String? date;

  const TimelineItem({
    required this.kind,
    required this.id,
    required this.title,
    required this.detail,
    required this.status,
    this.severity,
    this.priority,
    this.escalated = false,
    this.auto = false,
    this.date,
  });

  bool get isReport => kind == 'report';

  factory TimelineItem.fromJson(Map<String, dynamic> json) {
    return TimelineItem(
      kind: json['kind']?.toString() ?? 'report',
      id: json['id'] as int,
      title: json['title']?.toString() ?? '',
      detail: json['detail']?.toString() ?? '',
      status: json['status']?.toString() ?? '',
      severity: json['severity']?.toString(),
      priority: json['priority']?.toString(),
      escalated: json['escalated'] == true,
      auto: json['auto'] == true,
      date: json['date']?.toString(),
    );
  }
}

/// A seminar/intervention — either one the student has been assigned to
/// ([isAssigned] true, `status` is the enrollment state: enrolled/attended/
/// missed) or a candidate upcoming session matching a recommended tag that
/// nobody has enrolled the student in yet ([isAssigned] false, `status` is
/// the seminar's own lifecycle state: upcoming/ongoing).
class SeminarItem {
  final int id;
  final String title;
  final String? description;
  final String status;

  /// The seminar's own lifecycle (upcoming/ongoing/completed/cancelled). Only
  /// populated when [isAssigned] is true — a session can be "completed" while
  /// `status` (the enrollment pivot) is still stuck on "enrolled" because
  /// nobody recorded attendance.
  final String? sessionStatus;
  final String? date;
  final String? time;
  final String? venue;
  final String? speaker;
  final bool isRequired;
  final String? triggerReason;
  final bool isAssigned;

  const SeminarItem({
    required this.id,
    required this.title,
    this.description,
    required this.status,
    this.sessionStatus,
    this.date,
    this.time,
    this.venue,
    this.speaker,
    this.isRequired = false,
    this.triggerReason,
    this.isAssigned = true,
  });

  /// True once the session itself has happened but nobody recorded whether
  /// the student attended.
  bool get attendanceNotRecorded =>
      isAssigned && sessionStatus == 'completed' && status == 'enrolled';

  factory SeminarItem.fromJson(
    Map<String, dynamic> json, {
    bool isAssigned = true,
  }) {
    return SeminarItem(
      id: json['id'] as int,
      title: json['title']?.toString() ?? '',
      description: json['description']?.toString(),
      status: json['status']?.toString() ?? '',
      sessionStatus: json['session_status']?.toString(),
      date: json['date']?.toString(),
      time: json['time']?.toString(),
      venue: json['venue']?.toString(),
      speaker: json['speaker']?.toString(),
      isRequired: json['is_required'] == true,
      triggerReason: json['trigger_reason']?.toString(),
      isAssigned: isAssigned,
    );
  }
}

/// Everything the student detail screen shows: the header (risk + counts), the
/// full case timeline, and assigned seminars.
class StudentDetail {
  final RosterStudent student;
  final List<TimelineItem> timeline;
  final List<SeminarItem> seminars;

  const StudentDetail({
    required this.student,
    required this.timeline,
    required this.seminars,
  });

  factory StudentDetail.fromJson(Map<String, dynamic> json) {
    return StudentDetail(
      student: RosterStudent.fromJson(json['student'] as Map<String, dynamic>),
      timeline: (json['timeline'] as List? ?? [])
          .map((e) => TimelineItem.fromJson(e as Map<String, dynamic>))
          .toList(),
      seminars: (json['seminars'] as List? ?? [])
          .map((e) => SeminarItem.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }
}
