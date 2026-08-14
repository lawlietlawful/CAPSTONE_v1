import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../core/constants/app_colors.dart';
import '../../core/constants/app_text_styles.dart';
import '../../models/advised_student.dart';

/// Searchable bottom sheet for choosing one of the teacher's advised students.
/// Returns the picked [AdvisedStudent], or null if dismissed.
Future<AdvisedStudent?> showStudentPicker(
  BuildContext context,
  List<AdvisedStudent> students,
) {
  return showModalBottomSheet<AdvisedStudent>(
    context: context,
    backgroundColor: AppColors.surface,
    isScrollControlled: true,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
    ),
    builder: (_) => _StudentPickerSheet(students: students),
  );
}

class _StudentPickerSheet extends StatefulWidget {
  final List<AdvisedStudent> students;
  const _StudentPickerSheet({required this.students});

  @override
  State<_StudentPickerSheet> createState() => _StudentPickerSheetState();
}

class _StudentPickerSheetState extends State<_StudentPickerSheet> {
  final _searchController = TextEditingController();
  String _query = '';

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  List<AdvisedStudent> get _filtered {
    final q = _query.trim().toLowerCase();
    if (q.isEmpty) return widget.students;
    return widget.students.where((s) {
      return s.fullName.toLowerCase().contains(q) ||
          s.schoolId.toLowerCase().contains(q);
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    final results = _filtered;
    // Leave room for the keyboard; cap the sheet at ~75% of the screen.
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;
    final maxHeight = MediaQuery.of(context).size.height * 0.75;

    return Padding(
      padding: EdgeInsets.only(bottom: bottomInset),
      child: ConstrainedBox(
        constraints: BoxConstraints(maxHeight: maxHeight),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const SizedBox(height: 12),
            Container(
              width: 36,
              height: 4,
              decoration: BoxDecoration(
                color: AppColors.border,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 8),
              child: Row(
                children: [
                  Text('Select Student', style: AppTextStyles.pageTitle),
                  const Spacer(),
                  Text('${results.length}', style: AppTextStyles.meta),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: TextField(
                controller: _searchController,
                autofocus: false,
                onChanged: (v) => setState(() => _query = v),
                cursorColor: AppColors.accent,
                style: AppTextStyles.body,
                decoration: InputDecoration(
                  hintText: 'Search by name or student ID',
                  hintStyle: AppTextStyles.meta,
                  prefixIcon: const Icon(Icons.search_rounded,
                      size: 18, color: AppColors.text3),
                  isDense: true,
                  contentPadding:
                      const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                  border: _border(AppColors.border),
                  enabledBorder: _border(AppColors.border),
                  focusedBorder: _border(AppColors.accent),
                ),
              ),
            ),
            const SizedBox(height: 8),
            Flexible(
              child: results.isEmpty
                  ? Padding(
                      padding: const EdgeInsets.symmetric(vertical: 40),
                      child: Text('No students match your search.',
                          style: AppTextStyles.meta),
                    )
                  : ListView.separated(
                      shrinkWrap: true,
                      padding: const EdgeInsets.only(bottom: 16),
                      itemCount: results.length,
                      separatorBuilder: (_, _) => const Divider(
                          height: 1, color: AppColors.border, indent: 20, endIndent: 20),
                      itemBuilder: (_, i) {
                        final s = results[i];
                        return ListTile(
                          onTap: () => Navigator.pop(context, s),
                          leading: CircleAvatar(
                            radius: 18,
                            backgroundColor: AppColors.accentLight,
                            child: Text(
                              _initials(s),
                              style: GoogleFonts.inter(
                                fontSize: 12,
                                fontWeight: FontWeight.w500,
                                color: AppColors.accentDark,
                              ),
                            ),
                          ),
                          title: Text(s.fullName, style: AppTextStyles.body),
                          subtitle: Text(
                            '${s.schoolId} · ${s.yearSection}',
                            style: AppTextStyles.meta,
                          ),
                        );
                      },
                    ),
            ),
            SafeArea(top: false, child: const SizedBox(height: 4)),
          ],
        ),
      ),
    );
  }

  static OutlineInputBorder _border(Color c) => OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: BorderSide(color: c),
      );

  static String _initials(AdvisedStudent s) {
    final f = s.firstName.isNotEmpty ? s.firstName[0] : '';
    final l = s.lastName.isNotEmpty ? s.lastName[0] : '';
    final v = '$f$l'.toUpperCase();
    return v.isEmpty ? '?' : v;
  }
}
