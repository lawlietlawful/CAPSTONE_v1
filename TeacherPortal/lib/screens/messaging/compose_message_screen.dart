import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../core/services/api_service.dart';
import '../../providers/teacher_provider.dart';
import '../../widgets/common/form_widgets.dart';

/// Starts a new top-level thread with a counselor. Teachers may only message
/// a counselor (Message::canInitiate on the backend) — there is no recipient
/// picker beyond that fixed list.
class ComposeMessageScreen extends StatefulWidget {
  const ComposeMessageScreen({super.key});

  @override
  State<ComposeMessageScreen> createState() => _ComposeMessageScreenState();
}

class _ComposeMessageScreenState extends State<ComposeMessageScreen> {
  final _subjectController = TextEditingController();
  final _contentController = TextEditingController();

  List<Map<String, dynamic>> _counselors = [];
  int? _selectedCounselorId;
  bool _loading = true;
  String? _loadError;
  bool _sending = false;
  String? _formError;

  @override
  void initState() {
    super.initState();
    _loadCounselors();
  }

  @override
  void dispose() {
    _subjectController.dispose();
    _contentController.dispose();
    super.dispose();
  }

  Future<void> _loadCounselors() async {
    setState(() {
      _loading = true;
      _loadError = null;
    });
    try {
      final counselors = await context.read<TeacherProvider>().fetchCounselors();
      if (!mounted) return;
      setState(() {
        _counselors = counselors;
        _selectedCounselorId =
            counselors.isNotEmpty ? counselors.first['id'] as int : null;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() => _loadError = 'Unable to load counselors. Check your connection.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _send() async {
    if (_selectedCounselorId == null) {
      setState(() => _formError = 'Please choose a counselor.');
      return;
    }
    if (_contentController.text.trim().isEmpty) {
      setState(() => _formError = 'Please write a message.');
      return;
    }
    setState(() {
      _formError = null;
      _sending = true;
    });
    try {
      await context.read<TeacherProvider>().sendMessage(
            receiverId: _selectedCounselorId!,
            subject: _subjectController.text.trim().isEmpty
                ? null
                : _subjectController.text.trim(),
            content: _contentController.text.trim(),
          );
      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      setState(() => _formError = e.message);
    } catch (_) {
      setState(() => _formError = 'Unable to send. Check your connection.');
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text(
          'New Message',
          style: GoogleFonts.inter(fontSize: 16, fontWeight: FontWeight.w500),
        ),
      ),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    if (_loading) return const Center(child: CircularProgressIndicator());

    if (_loadError != null) {
      return CenteredMessage(
        icon: Icons.wifi_off_rounded,
        title: 'Could not load counselors',
        message: _loadError!,
        actionLabel: 'Retry',
        onAction: _loadCounselors,
      );
    }

    if (_counselors.isEmpty) {
      return const CenteredMessage(
        icon: Icons.support_agent_rounded,
        title: 'No counselor available',
        message: 'There is no guidance counselor account set up to message yet.',
      );
    }

    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
      children: [
        const FieldLabel('To'),
        const SizedBox(height: 6),
        _CounselorField(
          counselors: _counselors,
          selectedId: _selectedCounselorId,
          onChanged: (id) => setState(() => _selectedCounselorId = id),
        ),
        const SizedBox(height: 18),

        const FieldLabel('Subject (optional)'),
        const SizedBox(height: 6),
        InputField(controller: _subjectController, hint: 'e.g. About Angelo Fernandez'),
        const SizedBox(height: 18),

        const FieldLabel('Message'),
        const SizedBox(height: 6),
        InputField(
          controller: _contentController,
          hint: 'Type your message...',
          maxLines: 6,
        ),

        if (_formError != null) ...[
          const SizedBox(height: 14),
          ErrorBanner(_formError!),
        ],
        const SizedBox(height: 22),
        PrimaryButton(label: 'Send Message', loading: _sending, onPressed: _send),
      ],
    );
  }
}

class _CounselorField extends StatelessWidget {
  final List<Map<String, dynamic>> counselors;
  final int? selectedId;
  final ValueChanged<int?> onChanged;

  const _CounselorField({
    required this.counselors,
    required this.selectedId,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.surface,
        border: Border.all(color: AppColors.border),
        borderRadius: BorderRadius.circular(10),
      ),
      padding: const EdgeInsets.symmetric(horizontal: 12),
      child: DropdownButtonHideUnderline(
        child: DropdownButton<int>(
          value: selectedId,
          isExpanded: true,
          icon: const Icon(Icons.expand_more_rounded, color: AppColors.text3, size: 18),
          style: GoogleFonts.inter(fontSize: 13, color: AppColors.text1),
          items: counselors
              .map(
                (c) => DropdownMenuItem<int>(
                  value: c['id'] as int,
                  child: Text('${c['name']}'),
                ),
              )
              .toList(),
          onChanged: onChanged,
        ),
      ),
    );
  }
}
