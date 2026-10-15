import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../core/constants/app_decor.dart';
import '../../core/services/api_service.dart';
import '../../models/referral.dart';
import '../../providers/teacher_provider.dart';

/// On a resolved referral: the teacher tells Guidance how the student is
/// doing in class now. Shows what they said, with a way to update it.
class FollowUpCard extends StatefulWidget {
  final int referralId;
  final String? studentName;
  final ReferralFollowUp? followUp;
  final VoidCallback onSaved;

  const FollowUpCard({
    super.key,
    required this.referralId,
    required this.studentName,
    required this.followUp,
    required this.onSaved,
  });

  @override
  State<FollowUpCard> createState() => _FollowUpCardState();
}

class _FollowUpCardState extends State<FollowUpCard> {
  late bool _editing = widget.followUp == null;
  String? _outcome;
  final _note = TextEditingController();
  bool _saving = false;
  String? _error;

  static const _choices = [
    ('improved', 'Improved', Icons.trending_up_rounded, AppColors.green, AppColors.greenBg),
    ('no_change', 'No change', Icons.trending_flat_rounded, AppColors.text2, AppColors.background),
    ('worse', 'Worse', Icons.trending_down_rounded, AppColors.red, AppColors.redBg),
  ];

  @override
  void initState() {
    super.initState();
    _outcome = widget.followUp?.outcome;
    _note.text = widget.followUp?.note ?? '';
  }

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (_outcome == null) {
      setState(() => _error = 'Choose how the student is doing.');
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await context.read<TeacherProvider>().submitFollowUp(
            referralId: widget.referralId,
            outcome: _outcome!,
            note: _note.text.trim().isEmpty ? null : _note.text.trim(),
          );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Thanks — your follow-up was sent to Guidance.')),
      );
      widget.onSaved();
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: AppDecor.card(),
      child: _editing ? _form() : _summary(widget.followUp!),
    );
  }

  Widget _summary(ReferralFollowUp f) {
    final choice = _choices.firstWhere((c) => c.$1 == f.outcome, orElse: () => _choices[1]);
    final at = DateTime.tryParse(f.at ?? '');
    return Column(
      key: const Key('follow-up-summary'),
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
              decoration: BoxDecoration(color: choice.$5, borderRadius: BorderRadius.circular(8)),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(choice.$3, size: 15, color: choice.$4),
                  const SizedBox(width: 5),
                  Text(f.label, style: GoogleFonts.inter(fontSize: 12.5, fontWeight: FontWeight.w600, color: choice.$4)),
                ],
              ),
            ),
            const Spacer(),
            TextButton(
              onPressed: () => setState(() => _editing = true),
              child: Text('Update', style: GoogleFonts.inter(fontSize: 12.5, color: AppColors.accent)),
            ),
          ],
        ),
        if (f.note != null && f.note!.isNotEmpty) ...[
          const SizedBox(height: 8),
          Text(f.note!, style: GoogleFonts.inter(fontSize: 13, color: AppColors.text2, height: 1.45)),
        ],
        if (at != null) ...[
          const SizedBox(height: 6),
          Text(
            'Sent to Guidance ${DateFormat('MMM d, yyyy').format(at.toLocal())}',
            style: GoogleFonts.inter(fontSize: 11.5, color: AppColors.text3),
          ),
        ],
      ],
    );
  }

  Widget _form() {
    final name = widget.studentName == null ? 'the student' : _firstName(widget.studentName!);
    return Column(
      key: const Key('follow-up-form'),
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Guidance resolved this referral. How is $name doing in your class now?',
          style: GoogleFonts.inter(fontSize: 13, color: AppColors.text2, height: 1.45),
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            for (final c in _choices) ...[
              Expanded(
                child: InkWell(
                  borderRadius: BorderRadius.circular(10),
                  onTap: () => setState(() {
                    _outcome = c.$1;
                    _error = null;
                  }),
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 150),
                    padding: const EdgeInsets.symmetric(vertical: 10),
                    decoration: BoxDecoration(
                      color: _outcome == c.$1 ? c.$5 : AppColors.surface,
                      border: Border.all(
                        color: _outcome == c.$1 ? c.$4 : AppColors.border,
                        width: _outcome == c.$1 ? 1.5 : 1,
                      ),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Column(
                      children: [
                        Icon(c.$3, size: 18, color: c.$4),
                        const SizedBox(height: 3),
                        Text(c.$2, style: GoogleFonts.inter(fontSize: 12, fontWeight: FontWeight.w600, color: c.$4)),
                      ],
                    ),
                  ),
                ),
              ),
              if (c != _choices.last) const SizedBox(width: 8),
            ],
          ],
        ),
        const SizedBox(height: 12),
        TextField(
          controller: _note,
          maxLines: 3,
          maxLength: 2000,
          style: GoogleFonts.inter(fontSize: 13, color: AppColors.text1),
          decoration: InputDecoration(
            hintText: 'What have you noticed? (optional)',
            hintStyle: GoogleFonts.inter(fontSize: 13, color: AppColors.text3),
            counterText: '',
            filled: true,
            fillColor: AppColors.background,
            contentPadding: const EdgeInsets.all(12),
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(10),
              borderSide: const BorderSide(color: AppColors.border),
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(10),
              borderSide: const BorderSide(color: AppColors.border),
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(10),
              borderSide: const BorderSide(color: AppColors.accent, width: 1.5),
            ),
          ),
        ),
        if (_error != null) ...[
          const SizedBox(height: 8),
          Text(_error!, style: GoogleFonts.inter(fontSize: 12, color: AppColors.redText)),
        ],
        const SizedBox(height: 12),
        Row(
          children: [
            if (widget.followUp != null)
              TextButton(
                onPressed: _saving ? null : () => setState(() => _editing = false),
                child: Text('Cancel', style: GoogleFonts.inter(color: AppColors.text2)),
              ),
            const Spacer(),
            FilledButton(
              key: const Key('send-follow-up'),
              onPressed: _saving ? null : _save,
              style: FilledButton.styleFrom(
                backgroundColor: AppColors.accent,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
              child: _saving
                  ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : Text('Send to Guidance', style: GoogleFonts.inter(fontWeight: FontWeight.w600)),
            ),
          ],
        ),
      ],
    );
  }

  /// "Cruz, Ana" → "Ana".
  static String _firstName(String name) {
    final parts = name.split(', ');
    return parts.length == 2 ? parts[1] : name;
  }
}
