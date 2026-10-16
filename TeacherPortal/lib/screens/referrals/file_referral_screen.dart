import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../core/constants/app_text_styles.dart';
import '../../core/services/api_service.dart';
import '../../models/advised_student.dart';
import '../../models/referral.dart';
import '../../providers/teacher_provider.dart';
import '../../widgets/common/form_widgets.dart';
import '../../widgets/common/status_badges.dart';
import '../../widgets/common/surfaces.dart';
import '../../widgets/premium/success_check.dart';
import '../../widgets/common/duplicate_referral_dialog.dart';
import '../../widgets/common/student_picker_sheet.dart';
import '../messaging/compose_message_screen.dart';

/// Refers a student to the guidance office. Mirrors the web referral form
/// (resources/views/teacher/referrals/index.blade.php modal): a fixed set of
/// referral types, with a free-text box required only when "Other" is picked.
///
/// Priority is NOT asked for — the backend's ML engine derives it from the
/// student's risk level.
class FileReferralScreen extends StatefulWidget {
  /// Opens with this student already chosen (from a student's own page).
  final int? initialStudentId;

  const FileReferralScreen({super.key, this.initialStudentId});

  @override
  State<FileReferralScreen> createState() => _FileReferralScreenState();
}

class _FileReferralScreenState extends State<FileReferralScreen> {
  AdvisedStudent? _student;
  String? _referralType;

  final _otherController = TextEditingController();
  final _reasonController = TextEditingController();

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
      // Pulls the authoritative referral-type list from the server.
      if (provider.referrals.isEmpty) provider.loadReferrals();
    });
  }

  @override
  void dispose() {
    _scroll.dispose();
    _otherController.dispose();
    _reasonController.dispose();
    super.dispose();
  }

  Future<void> _pickStudent() async {
    final provider = context.read<TeacherProvider>();
    final picked = await showStudentPicker(context, provider.students);
    if (picked != null) setState(() => _student = picked);
  }

  Future<void> _submit() async {
    if (_student == null) {
      _showError('Please select a student.');
      return;
    }
    if (_referralType == null) {
      _showError('Please choose a referral type.');
      return;
    }
    // Mirrors the backend's `required_if:referral_type,Other` rule so the
    // teacher gets the error instantly instead of after a round-trip.
    if (_referralType == 'Other' && _otherController.text.trim().isEmpty) {
      _showError('Please specify the referral reason.');
      return;
    }
    if (_reasonController.text.trim().isEmpty) {
      _showError('Please describe the reason for referral.');
      return;
    }
    setState(() => _formError = null);
    await _send();
  }

  Future<void> _send({bool confirmDuplicate = false}) async {
    final provider = context.read<TeacherProvider>();
    try {
      final referral = await provider.submitReferral(
        studentId: _student!.id,
        referralType: _referralType!,
        referralTypeOther:
            _referralType == 'Other' ? _otherController.text.trim() : null,
        reason: _reasonController.text.trim(),
        confirmDuplicate: confirmDuplicate,
      );
      if (!mounted) return;
      // Counters on the dashboard are now stale.
      provider.loadDashboard();

      await showReferralResultSheet(context, referral);
      if (mounted) Navigator.of(context).pop(referral);
    } on ApiException catch (e) {
      if (!mounted) return;
      final existing = e.data?['existing'];
      if (e.statusCode == 409 && existing is Map<String, dynamic>) {
        final choice = await showDuplicateReferralDialog(
          context,
          studentName: _student!.fullName,
          existing: existing,
        );
        if (!mounted) return;
        switch (choice) {
          case DuplicateChoice.fileAnyway:
            await _send(confirmDuplicate: true);
          case DuplicateChoice.messageCounselor:
            await Navigator.of(context).push(MaterialPageRoute(
              builder: (_) => ComposeMessageScreen(
                initialCounselorId: (existing['counselor_id'] as num?)?.toInt(),
                initialSubject: 'About ${_student!.fullName} (already referred)',
              ),
            ));
          case DuplicateChoice.cancel:
          case null:
            break;
        }
        return;
      }
      _showError(e.message);
    } catch (_) {
      if (mounted) {
        _showError('Unable to submit. Check your connection.');
      }
    }
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
        title: const Text('File a referral'),
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
                  label: 'Submit Referral',
                  loading: provider.submitting,
                  onPressed: _submit,
                ),
              ),
            ),
    );
  }

  bool _prefilled = false;

  /// Picks [FileReferralScreen.initialStudentId] once the student list is in.
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

        const FieldLabel('Reason for referral'),
        const SizedBox(height: 8),
        ...provider.referralTypes.map(
          (type) => _RadioRow(
            label: type,
            selected: _referralType == type,
            onTap: () => setState(() => _referralType = type),
          ),
        ),

        // Only required — and only shown — when "Other" is selected.
        if (_referralType == 'Other') ...[
          const SizedBox(height: 4),
          InputField(
            controller: _otherController,
            hint: 'Please specify',
          ),
        ],
        const SizedBox(height: 20),

        const FieldLabel('Details'),
        const SizedBox(height: 6),
        InputField(
          controller: _reasonController,
          hint: 'Describe the situation for the guidance counselor...',
          maxLines: 4,
        ),

        const SizedBox(height: 16),
        const InfoNote(
          text: "Priority is assessed automatically from the student's risk "
              'level. The parent will be notified by SMS.',
        ),

        if (_formError != null) ...[
          const SizedBox(height: 14),
          ErrorBanner(_formError!),
        ],
      ],
    );
  }
}

// ─────────────────────────────────────────────────────────

class _RadioRow extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;

  const _RadioRow({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Semantics(
        inMutuallyExclusiveGroup: true,
        checked: selected,
        child: Material(
          color: selected ? AppColors.accentLight : AppColors.surface,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(10),
            side: BorderSide(
              color: selected ? AppColors.accent : AppColors.borderStrong,
              width: selected ? 1.5 : 1,
            ),
          ),
          child: InkWell(
            onTap: onTap,
            borderRadius: BorderRadius.circular(10),
            child: ConstrainedBox(
              constraints: const BoxConstraints(minHeight: 48),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                child: Row(
                  children: [
                    Icon(
                      selected
                          ? Icons.radio_button_checked_rounded
                          : Icons.radio_button_unchecked_rounded,
                      size: 20,
                      color: selected ? AppColors.accent : AppColors.text3,
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        label,
                        style: GoogleFonts.publicSans(
                          fontSize: 14.5,
                          fontWeight: selected ? FontWeight.w600 : FontWeight.w400,
                          color: selected ? AppColors.accentDark : AppColors.text1,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Confirmation sheet shown after a referral is filed, surfacing the
/// ML-assigned priority and risk level.
Future<void> showReferralResultSheet(BuildContext context, Referral referral) {
  return showModalBottomSheet<void>(
    context: context,
    backgroundColor: AppColors.surface,
    // Matches the incident sheet: the ML priority is the only place the teacher
    // learns the outcome, so it shouldn't be dismissible by tapping away.
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
                Text('Referral submitted', style: AppTextStyles.pageTitle),
              ],
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                Text('AI-assigned priority', style: AppTextStyles.label),
                const SizedBox(width: 8),
                AppBadge.priority(referral.priority),
                if (referral.riskLevel != null) ...[
                  const SizedBox(width: 10),
                  Text('Risk', style: AppTextStyles.label),
                  const SizedBox(width: 6),
                  AppBadge.severity(referral.riskLevel!),
                ],
              ],
            ),
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: AppColors.accentLight,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(Icons.sms_outlined,
                      size: 16, color: AppColors.accentDark),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Guidance has been notified and the parent has received '
                      'an SMS about this referral.',
                      style: GoogleFonts.publicSans(
                        fontSize: 12,
                        color: AppColors.accentDark,
                        height: 1.4,
                      ),
                    ),
                  ),
                ],
              ),
            ),
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
