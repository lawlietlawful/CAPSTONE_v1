/// A student on the teacher's "My Students" roster, carrying their current ML
/// risk level and open-case counts. Also used as the header of the student
/// detail screen (where `course` is populated).
class RosterStudent {
  final int id;
  final String name;
  final String schoolId;
  final String educationLevel;
  final String yearLevel;
  final String? strand;
  final String section;
  final String riskLevel; // high | moderate | low | not_assessed
  final double? riskScore;
  final String? riskReason;
  final String? recommendedSeminarTag;
  final int openReferrals;
  final int reportsCount;
  final String? course;

  const RosterStudent({
    required this.id,
    required this.name,
    required this.schoolId,
    required this.educationLevel,
    required this.yearLevel,
    this.strand,
    required this.section,
    required this.riskLevel,
    required this.riskScore,
    this.riskReason,
    this.recommendedSeminarTag,
    required this.openReferrals,
    required this.reportsCount,
    this.course,
  });

  factory RosterStudent.fromJson(Map<String, dynamic> json) {
    final score = json['risk_score'];
    return RosterStudent(
      id: json['id'] as int,
      name: json['name']?.toString() ?? '',
      schoolId: json['school_id']?.toString() ?? '',
      educationLevel: json['education_level']?.toString() ?? 'College',
      yearLevel: json['year_level']?.toString() ?? '',
      strand: json['strand']?.toString(),
      section: json['section']?.toString() ?? '',
      riskLevel: json['risk_level']?.toString() ?? 'not_assessed',
      riskScore: score == null ? null : double.tryParse(score.toString()),
      riskReason: json['risk_reason']?.toString(),
      recommendedSeminarTag: json['recommended_seminar_tag']?.toString(),
      openReferrals: (json['open_referrals'] as num?)?.toInt() ?? 0,
      reportsCount: (json['reports_count'] as num?)?.toInt() ?? 0,
      course: json['course']?.toString(),
    );
  }

  /// "4th Year · Block 4" — omits blanks. Folds in the strand for Basic
  /// Education Grade 11/12 students, e.g. "Grade 12 · STEM · Block 4".
  String get yearSection => [
        yearLevel,
        if (strand != null && strand!.isNotEmpty) strand!,
        section,
      ].where((s) => s.isNotEmpty).join(' · ');

  bool get isAtRisk => riskLevel == 'high' || riskLevel == 'moderate';
}
