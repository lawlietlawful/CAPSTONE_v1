import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../core/constants/app_text_styles.dart';
import '../../core/services/api_service.dart';
import '../../models/advised_student.dart';
import '../../models/behavioral_report.dart';
import '../../models/report_draft.dart';
import '../../providers/teacher_provider.dart';
import '../../widgets/common/form_widgets.dart';
import '../../widgets/common/status_badges.dart';
import '../../widgets/common/surfaces.dart';
import '../../widgets/premium/success_check.dart';
import '../../widgets/common/student_picker_sheet.dart';

/// The speed-critical flow: a teacher quickly records a behavioral incident
/// from their phone, mid-class. Severity is deliberately NOT asked for — the
/// backend's ML engine assesses it (and auto-escalates serious ones).
///
/// Pushed as a route from the floating action button, not a bottom-nav tab:
/// it's an action you complete and leave, not a destination you dwell in.
class LogIncidentScreen extends StatefulWidget {
  /// Opens with this student already chosen (from a student's own page).
  final int? initialStudentId;

  const LogIncidentScreen({super.key, this.initialStudentId});

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
  final _scroll = ScrollController();

  /// Shows a form error and scrolls it into view (it sits under the fields).
  void _showError(String message) {
    setState(() => _formError = message);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!_scroll.hasClients) return;
      _scroll.animateTo(
        _scroll.position.maxScrollExtent,
        duration: const Duration(milliseconds: 250),
        curve: Curves.easeOut,
      );
    });
  }

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
    _scroll.dispose();
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
      _showError('Please select a student.');
      return;
    }
    if (_incidentType == null) {
      _showError('Please choose what happened.');
      return;
    }
    if (_descriptionController.text.trim().isEmpty) {
      _showError('Please describe what happened.');
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
      if (!mounted) return;
      if (e.isNetworkError) {
        await _offerDraft(e.message);
      } else {
        _showError(e.message);
      }
    } catch (_) {
      if (mounted) {
        _showError('Unable to submit. Check your connection.');
      }
    }
  }

  /// No connection (common in classrooms): keep the report on the phone and
  /// send it later, rather than make the teacher type it all again.
  Future<void> _offerDraft(String reason) async {
    final save = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        title: Text(
          "Couldn't send the report",
          style: GoogleFonts.publicSans(fontSize: 16, fontWeight: FontWeight.w600),
        ),
        content: Text(
          '$reason\n\nSave it as a draft? It stays on this phone and is sent '
          'as soon as you have a connection.',
          style: GoogleFonts.publicSans(fontSize: 13, color: AppColors.text2, height: 1.45),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: Text('Keep editing', style: GoogleFonts.publicSans(color: AppColors.text2)),
          ),
          FilledButton(
            key: const Key('save-draft'),
            onPressed: () => Navigator.pop(ctx, true),
            style: FilledButton.styleFrom(backgroundColor: AppColors.accent),
            child: Text('Save draft', style: GoogleFonts.publicSans(fontWeight: FontWeight.w600)),
          ),
        ],
      ),
    );
    if (save != true || !mounted) return;

    await context.read<TeacherProvider>().saveDraft(ReportDraft(
          id: DateTime.now().millisecondsSinceEpoch,
          studentId: _student!.id,
          studentName: _student!.fullName,
          incidentType: _incidentType!,
          incidentDate: DateFormat('yyyy-MM-dd').format(_date),
          location: _locationController.text.trim().isEmpty
              ? null
              : _locationController.text.trim(),
          description: _descriptionController.text.trim(),
          savedAt: DateTime.now().toIso8601String(),
        ));
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
      content: Text('Saved as a draft. It will be sent when you are back online.'),
    ));
    Navigator.of(context).pop();
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
                  const SuccessCheck(size: 34),
                  const SizedBox(width: 12),
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
                          style: GoogleFonts.publicSans(
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
                          'This concern was serious enough to be escalated to '
                          'a Guidance referral. The parent has been notified.',
                          style: GoogleFonts.publicSans(
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
    _prefill(provider);
    final ready = provider.students.isNotEmpty;

    return Scaffold(
      backgroundColor: AppColors.surface,
      appBar: AppBar(
        backgroundColor: AppColors.surface,
        leading: IconButton(
          tooltip: 'Close',
          icon: const Icon(Icons.close_rounded),
          onPressed: () => Navigator.of(context).maybePop(),
        ),
        title: const Text('Report a concern'),
        shape: const Border(bottom: BorderSide(color: AppColors.divider)),
      ),
      body: _buildBody(provider),
      bottomNavigationBar: !ready
          ? null
          : SafeArea(
              top: false,
              child: Container(
                padding: const EdgeInsets.fromLTRB(20, 12, 20, 12),
                decoration: const BoxDecoration(
                  color: AppColors.surface,
                  border: Border(top: BorderSide(color: AppColors.divider)),
                ),
                child: PrimaryButton(
                  label: 'Submit Report',
                  loading: provider.submitting,
                  onPressed: _submit,
                ),
              ),
            ),
    );
  }

  bool _prefilled = false;

  /// Picks [LogIncidentScreen.initialStudentId] once the student list is in.
  void _prefill(TeacherProvider provider) {
    if (_prefilled || widget.initialStudentId == null || provider.students.isEmpty) return;
    _prefilled = true;
    for (final s in provider.students) {
      if (s.id == widget.initialStudentId) {
        _student = s;
        break;
      }
    }
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
      controller: _scroll,
      padding: const EdgeInsets.fromLTRB(20, 18, 20, 24),
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
          actionLabel: 'Change',
          onTap: _pickStudent,
        ),
        const SizedBox(height: 20),

        const FieldLabel('What happened?'),
        const SizedBox(height: 8),
        LayoutBuilder(
          builder: (context, constraints) {
            final width = (constraints.maxWidth - 8) / 2;
            return Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final type in provider.incidentTypes)
                  SizedBox(
                    width: width,
                    child: ChoiceChipButton(
                      label: type,
                      selected: _incidentType == type,
                      onTap: () => setState(() => _incidentType = type),
                    ),
                  ),
              ],
            );
          },
        ),
        const SizedBox(height: 20),

        const FieldLabel('Date it happened'),
        const SizedBox(height: 6),
        SelectorField(
          icon: Icons.calendar_today_rounded,
          text: _isToday ? 'Today, $_dateLabel' : _dateLabel,
          isPlaceholder: false,
          onTap: _pickDate,
        ),
        const SizedBox(height: 20),

        const FieldLabel('Location (optional)'),
        const SizedBox(height: 6),
        InputField(
          controller: _locationController,
          hint: 'e.g. Room 101',
        ),
        const SizedBox(height: 20),

        const FieldLabel('Description'),
        const SizedBox(height: 6),
        InputField(
          controller: _descriptionController,
          hint: 'Briefly describe what happened...',
          maxLines: 4,
        ),

        // The backend's ML engine grades severity — make that explicit so the
        // teacher isn't looking for a severity picker.
        const SizedBox(height: 16),
        const InfoNote(
          text: 'Severity is assessed automatically. Serious concerns are '
              'escalated to Guidance and the parent is notified. No signal? '
              "You can save the report on this phone and it's sent later.",
        ),

        if (_formError != null) ...[
          const SizedBox(height: 14),
          ErrorBanner(_formError!),
        ],
      ],
    );
  }

  bool get _isToday => DateUtils.isSameDay(_date, DateTime.now());
}
