class StudentModel {
  final int id;
  final String firstName;
  final String lastName;
  final String email;
  final String educationLevel;
  final String gradeLevel;
  final String? strand;
  final String section;
  final String schoolId;

  const StudentModel({
    required this.id,
    required this.firstName,
    required this.lastName,
    required this.email,
    required this.educationLevel,
    required this.gradeLevel,
    this.strand,
    required this.section,
    required this.schoolId,
  });

  /// "Grade 11 · STEM" for Basic Education, or just the plain year level
  /// ("4th Year") for College, where there's no strand to show.
  String get gradeLevelDisplay => strand == null || strand!.isEmpty
      ? gradeLevel
      : '$gradeLevel · $strand';

  String get fullName => '$firstName $lastName'.trim();

  String get initials {
    final f = firstName.isNotEmpty ? firstName[0] : '';
    final l = lastName.isNotEmpty ? lastName[0] : '';
    return '$f$l'.toUpperCase();
  }

  factory StudentModel.fromJson(Map<String, dynamic> json) {
    return StudentModel(
      id: (json['id'] as num).toInt(),
      firstName: json['first_name'] as String? ?? '',
      lastName: json['last_name'] as String? ?? '',
      email: json['email'] as String? ?? '',
      educationLevel: json['education_level']?.toString() ?? 'College',
      gradeLevel: json['grade_level']?.toString() ?? '',
      strand: json['strand']?.toString(),
      section: json['section']?.toString() ?? '',
      schoolId: json['school_id']?.toString() ?? '',
    );
  }
}
