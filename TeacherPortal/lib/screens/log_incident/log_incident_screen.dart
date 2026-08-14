import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../core/constants/app_text_styles.dart';
import '../../core/services/api_service.dart';
import '../../models/advised_student.dart';
import '../../models/behavioral_report.dart';
import '../../providers/teacher_provider.dart';
import '../../widgets/common/form_widgets.dart';
import '../../widgets/common/status_badges.dart';
import '../../widgets/common/student_picker_sheet.dart';

/// The speed-critical flow: a teacher quickly records a behavioral incident
/// from their phone, mid-class. Severity is deliberately NOT asked for — the
/// backend's ML engine assesses it (and auto-escalates serious ones).
///
/// Pushed as a route from the floating action button, not a bottom-nav tab:
/// it's an action you complete and leave, not a destination you dwell in.
class LogIncidentScreen extends StatefulWidget {
  const LogIncidentScreen({super.key});

  @override
  State<LogIncidentScreen> createState() => _LogIncidentScreenState();
}

class _LogIncidentScreenState extends State<LogIncidentScreen> {
  AdvisedStudent? _student;
  String? _incidentType;
  DateTime _date = DateTime.now();

  final _locationController = TextEditingController();
  final _descriptionController = TextEditingController();

  String? _formError;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      final provider = context.read<TeacherProvider>();
      if (provider.students.isEmpty) provider.loadStudents();
      // Pulls the authoritative incident-type list. Needed because this screen
      // opens straight from the FAB, without visiting the Reports tab first.
      if (provider.reports.isEmpty) provider.loadReports();
    });
  }

  @override
  void dispose() {
    _locationController.dispose();
    _descriptionController.dispose();
    super.dispose();
  }

  String get _dateLabel => DateFormat('MMM d, yyyy').format(_date);

  Future<void> _pickStudent() async {
    final provider = context.read<TeacherProvider>();
    final picked = await showStudentPicker(context, provider.students);
    if (picked != null) setState(() => _student = picked);
  }

  Future<void> _pickDate() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _date,
      firstDate: DateTime(now.year - 1),
      // An incident can't have happened in the future.
      lastDate: now,
    );
    if (picked != null) setState(() => _date = picked);
  }

  Future<void> _submit() async {
    final provider = context.read<TeacherProvider>();

    if (_student == null) {
      setState(() => _formError = 'Please select a student.');
      return;
    }
    if (_incidentType == null) {
      setState(() => _formError = 'Please choose an incident type.');
      return;
    }
    if (_descriptionController.text.trim().isEmpty) {
      setState(() => _formError = 'Please describe what happened.');
      return;
    }
    setState(() => _formError = null);

    try {
      final report = await provider.submitReport(
        studentId: _student!.id,
        incidentType: _incidentType!,
        incidentDate: DateFormat('yyyy-MM-dd').format(_date),
        location: _locationController.text.trim().isEmpty
            ? null
            : _locationController.text.trim(),
        description: _descriptionController.text.trim(),
      );
      if (!mounted) return;

      // An escalation creates a referral, so the dashboard counters and the
      // referral list are both stale now.
      provider.loadDashboard();
      if (report.escalated) provider.loadReferrals();

      await _showResultSheet(report);
      if (mounted) Navigator.of(context).pop(report);
    } on ApiException catch (e) {
      if (mounted) setState(() => _formError = e.message);
    } catch (_) {
      if (mounted) {
        setState(() => _formError = 'Unable to submit. Check your connection.');
      }
    }
  }

  Future<void> _showResultSheet(BehavioralReport report) {
    return showModalBottomSheet<void>(
      context: context,
      backgroundColor: AppColors.surface,
      // Not dismissible by tapping away: the AI severity / escalation notice is
      // the only place the teacher learns the outcome of what they just filed.
      isDismissible: false,
      enableDrag: false,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (sheetContext) => SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 20, 20, 16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  const Icon(Icons.check_circle_rounded,
                      color: AppColors.green, size: 22),
                  const SizedBox(width: 8),
                  Text('Report submitted', style: AppTextStyles.pageTitle),
                ],
              ),
              const SizedBox(height: 14),
              Row(
                children: [
                  Text(
                    report.isUnassessed ? 'AI severity' : 'AI-assessed severity',
                    style: AppTextStyles.label,
                  ),
                  const SizedBox(width: 8),
                  AppBadge.severity(report.severity),
                ],
              ),
              if (report.isUnassessed) ...[
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppColors.amberBg,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Icon(Icons.cloud_off_rounded,
                          size: 16, color: AppColors.amberText),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          'Your report was saved, but the AI could not assess it '
                          'right now. Guidance will review and grade it shortly.',
                          style: GoogleFonts.inter(
                            fontSize: 12,
                            color: AppColors.amberText,
                            height: 1.4,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
              if (report.escalated) ...[
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppColors.purpleBg,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Icon(Icons.trending_up_rounded,
                          size: 16, color: AppColors.purpleText),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          'This incident was serious enough to be escalated to '
                          'a Guidance referral. The parent has been notified.',
                          style: GoogleFonts.inter(
                            fontSize: 12,
                            color: AppColors.purpleText,
                            height: 1.4,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
              const SizedBox(height: 18),
              PrimaryButton(
                label: 'Done',
                onPressed: () => Navigator.pop(sheetContext),
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<TeacherProvider>();

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text(
          'Log Incident',
          style: GoogleFonts.inter(fontSize: 16, fontWeight: FontWeight.w500),
        ),
      ),
      body: _buildBody(provider),
    );
  }

  Widget _buildBody(TeacherProvider provider) {
    if (provider.loadingStudents && provider.students.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (provider.studentsError != null && provider.students.isEmpty) {
      return CenteredMessage(
        icon: Icons.wifi_off_rounded,
        title: 'Could not load students',
        message: provider.studentsError!,
        actionLabel: 'Retry',
        onAction: () => provider.loadStudents(),
      );
    }

    // Fail-closed: teachers with no course assignments see no students.
    if (provider.students.isEmpty) {
      return const CenteredMessage(
        icon: Icons.groups_outlined,
        title: 'No assigned students',
        message:
            'You are not assigned to any course or section yet. Please contact '
            'the administrator.',
      );
    }

    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
      children: [
        const FieldLabel('Student'),
        const SizedBox(height: 6),
        SelectorField(
          icon: Icons.person_outline_rounded,
          text: _student?.fullName ?? 'Select a student',
          subtext: _student == null
              ? null
              : '${_student!.schoolId} · ${_student!.yearSection}',
          isPlaceholder: _student == null,
          onTap: _pickStudent,
        ),
        const SizedBox(height: 18),

        const FieldLabel('What happened?'),
        const SizedBox(height: 8),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: provider.incidentTypes.map((type) {
            final selected = _incidentType == type;
            return ChoiceChip(
              label: Text(type),
              selected: selected,
              onSelected: (_) => setState(() => _incidentType = type),
              labelStyle: GoogleFonts.inter(
                fontSize: 12,
                fontWeight: selected ? FontWeight.w500 : FontWeight.w400,
                color: selected ? AppColors.accentDark : AppColors.text2,
              ),
              selectedColor: AppColors.accentLight,
              backgroundColor: AppColors.surface,
              side: BorderSide(
                color: selected ? AppColors.accent : AppColors.border,
              ),
              showCheckmark: false,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(8),
              ),
            );
          }).toList(),
        ),
        const SizedBox(height: 18),

        const FieldLabel('Date of incident'),
        const SizedBox(height: 6),
        SelectorField(
          icon: Icons.calendar_today_rounded,
          text: _dateLabel,
          isPlaceholder: false,
          onTap: _pickDate,
        ),
        const SizedBox(height: 18),

        const FieldLabel('Location (optional)'),
        const SizedBox(height: 6),
        InputField(
          controller: _locationController,
          hint: 'e.g. Room 101',
        ),
        const SizedBox(height: 18),

        const FieldLabel('Description'),
        const SizedBox(height: 6),
        InputField(
          controller: _descriptionController,
          hint: 'Briefly describe what happened...',
          maxLines: 4,
        ),

        // The backend's ML engine grades severity — make that explicit so the
        // teacher isn't looking for a severity picker.
        const SizedBox(height: 10),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Icon(Icons.auto_awesome_rounded,
                size: 14, color: AppColors.text3),
            const SizedBox(width: 6),
            Expanded(
              child: Text(
                'Severity is assessed automatically. Serious incidents are '
                'escalated to Guidance and the parent is notified.',
                style: AppTextStyles.meta,
              ),
            ),
          ],
        ),

        if (_formError != null) ...[
          const SizedBox(height: 14),
          ErrorBanner(_formError!),
        ],

        const SizedBox(height: 22),
        PrimaryButton(
          label: 'Submit Report',
          loading: provider.submitting,
          onPressed: _submit,
        ),
      ],
    );
  }
}
