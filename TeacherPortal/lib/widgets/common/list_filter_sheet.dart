import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';

import '../../core/constants/app_colors.dart';
import '../../core/constants/app_text_styles.dart';

/// A labelled value for a secondary filter dimension (priority/severity).
typedef FilterOption = ({String label, String value});

/// Shared "more filters" bottom sheet for the Referrals and Reports lists:
/// a type dropdown-as-chips, a secondary dimension (priority/severity), and a
/// date range. Search and status live in [SearchFilterBar] already — this
/// covers only the extra dimensions layered on top of it.
class ListFilterSheet extends StatefulWidget {
  final String typeLabel;
  final List<String> typeOptions;
  final String? selectedType;
  final String secondaryLabel;
  final List<FilterOption> secondaryOptions;
  final String? selectedSecondary;
  final String? dateFrom; // yyyy-MM-dd
  final String? dateTo;
  final void Function(
    String? type,
    String? secondary,
    String? dateFrom,
    String? dateTo,
  )
  onApply;

  const ListFilterSheet({
    super.key,
    required this.typeLabel,
    required this.typeOptions,
    required this.selectedType,
    required this.secondaryLabel,
    required this.secondaryOptions,
    required this.selectedSecondary,
    required this.dateFrom,
    required this.dateTo,
    required this.onApply,
  });

  @override
  State<ListFilterSheet> createState() => _ListFilterSheetState();
}

class _ListFilterSheetState extends State<ListFilterSheet> {
  String? _type;
  String? _secondary;
  DateTime? _from;
  DateTime? _to;

  @override
  void initState() {
    super.initState();
    _type = widget.selectedType;
    _secondary = widget.selectedSecondary;
    _from = _parse(widget.dateFrom);
    _to = _parse(widget.dateTo);
  }

  DateTime? _parse(String? raw) {
    if (raw == null || raw.isEmpty) return null;
    try {
      return DateTime.parse(raw);
    } catch (_) {
      return null;
    }
  }

  String _fmt(DateTime d) => DateFormat('yyyy-MM-dd').format(d);
  String _label(DateTime d) => DateFormat('MMM d, yyyy').format(d);

  Future<void> _pickDate({required bool isFrom}) async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: (isFrom ? _from : _to) ?? now,
      firstDate: DateTime(now.year - 3),
      lastDate: now,
    );
    if (picked != null) {
      setState(() {
        if (isFrom) {
          _from = picked;
        } else {
          _to = picked;
        }
      });
    }
  }

  void _clearAll() {
    setState(() {
      _type = null;
      _secondary = null;
      _from = null;
      _to = null;
    });
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      child: Padding(
        padding: EdgeInsets.only(
          left: 20,
          right: 20,
          top: 20,
          bottom: 16 + MediaQuery.of(context).viewInsets.bottom,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text('Filters', style: AppTextStyles.pageTitle),
                ),
                GestureDetector(
                  onTap: _clearAll,
                  child: Text(
                    'Clear all',
                    style: GoogleFonts.inter(
                      fontSize: 12.5,
                      fontWeight: FontWeight.w500,
                      color: AppColors.accent,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 18),

            Text(widget.typeLabel, style: AppTextStyles.sectionTitle),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: widget.typeOptions
                  .map(
                    (t) => _Chip(
                      label: t,
                      selected: _type == t,
                      onTap: () =>
                          setState(() => _type = _type == t ? null : t),
                    ),
                  )
                  .toList(),
            ),
            const SizedBox(height: 18),

            Text(widget.secondaryLabel, style: AppTextStyles.sectionTitle),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: widget.secondaryOptions
                  .map(
                    (o) => _Chip(
                      label: o.label,
                      selected: _secondary == o.value,
                      onTap: () => setState(
                        () =>
                            _secondary = _secondary == o.value ? null : o.value,
                      ),
                    ),
                  )
                  .toList(),
            ),
            const SizedBox(height: 18),

            Text('Date range', style: AppTextStyles.sectionTitle),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: _DateField(
                    label: _from == null ? 'From' : _label(_from!),
                    isPlaceholder: _from == null,
                    onTap: () => _pickDate(isFrom: true),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _DateField(
                    label: _to == null ? 'To' : _label(_to!),
                    isPlaceholder: _to == null,
                    onTap: () => _pickDate(isFrom: false),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 24),

            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () {
                  widget.onApply(
                    _type,
                    _secondary,
                    _from == null ? null : _fmt(_from!),
                    _to == null ? null : _fmt(_to!),
                  );
                  Navigator.pop(context);
                },
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.accent,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 13),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10),
                  ),
                  elevation: 0,
                ),
                child: Text(
                  'Apply Filters',
                  style: GoogleFonts.inter(
                    fontSize: 14,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Chip extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;

  const _Chip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
        decoration: BoxDecoration(
          color: selected ? AppColors.accent : AppColors.background,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(
            color: selected ? AppColors.accent : AppColors.border,
          ),
        ),
        child: Text(
          label,
          style: GoogleFonts.inter(
            fontSize: 12.5,
            fontWeight: selected ? FontWeight.w600 : FontWeight.w400,
            color: selected ? Colors.white : AppColors.text2,
          ),
        ),
      ),
    );
  }
}

class _DateField extends StatelessWidget {
  final String label;
  final bool isPlaceholder;
  final VoidCallback onTap;

  const _DateField({
    required this.label,
    required this.isPlaceholder,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(10),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
        decoration: BoxDecoration(
          color: AppColors.background,
          border: Border.all(color: AppColors.border),
          borderRadius: BorderRadius.circular(10),
        ),
        child: Row(
          children: [
            const Icon(
              Icons.calendar_today_outlined,
              size: 15,
              color: AppColors.text3,
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                label,
                style: GoogleFonts.inter(
                  fontSize: 12.5,
                  color: isPlaceholder ? AppColors.text3 : AppColors.text1,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
