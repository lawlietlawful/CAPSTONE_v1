/// A student the logged-in teacher advises, as returned by
/// GET /api/teacher/students.
class AdvisedStudent {
  final int id;
  final String firstName;
  final String lastName;
  final String educationLevel;
  final String gradeLevel;
  final String? strand;
  final String section;
  final String schoolId;

  const AdvisedStudent({
    required this.id,
    required this.firstName,
    required this.lastName,
    required this.educationLevel,
    required this.gradeLevel,
    this.strand,
    required this.section,
    required this.schoolId,
  });

  String get fullName => '$lastName, $firstName';

  /// "BSIT 4th Year · Block 4" style subtitle (course isn't returned, so we
  /// show year + section which is what the picker needs to disambiguate).
  /// For Basic Education Grade 11/12 this also folds in the strand, e.g.
  /// "Grade 12 · STEM · Section A".
  String get yearSection => [
        gradeLevel,
        if (strand != null && strand!.isNotEmpty) strand!,
        section,
      ].where((s) => s.trim().isNotEmpty).join(' · ');

  factory AdvisedStudent.fromJson(Map<String, dynamic> json) {
    return AdvisedStudent(
      id: (json['id'] as num).toInt(),
      firstName: json['first_name']?.toString() ?? '',
      lastName: json['last_name']?.toString() ?? '',
      educationLevel: json['education_level']?.toString() ?? 'College',
      gradeLevel: json['grade_level']?.toString() ?? '',
      strand: json['strand']?.toString(),
      section: json['section']?.toString() ?? '',
      schoolId: json['school_id']?.toString() ?? '',
    );
  }
}
