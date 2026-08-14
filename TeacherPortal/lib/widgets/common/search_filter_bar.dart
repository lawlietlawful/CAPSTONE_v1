import 'dart:async';

import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../core/constants/app_colors.dart';

/// A status option for the filter row. `value == null` means "All".
typedef StatusOption = ({String label, String? value});

/// Search field + status filter chips shared by the Reports and Referrals
/// lists. Search input is debounced so we don't hit the API on every keystroke.
class SearchFilterBar extends StatefulWidget {
  final String hint;
  final List<StatusOption> statuses;
  final String? selectedStatus;
  final String initialSearch;
  final ValueChanged<String> onSearchChanged;
  final ValueChanged<String?> onStatusChanged;

  /// Opens the "more filters" sheet (type/severity/priority/date range).
  /// Omitted entirely when a screen has no extra filter dimensions.
  final VoidCallback? onMoreFilters;

  /// Whether a filter beyond search/status is currently applied — shown as a
  /// small dot on the filter icon so it isn't a silent, easy-to-forget state.
  final bool moreFiltersActive;

  const SearchFilterBar({
    super.key,
    required this.hint,
    required this.statuses,
    required this.selectedStatus,
    required this.initialSearch,
    required this.onSearchChanged,
    required this.onStatusChanged,
    this.onMoreFilters,
    this.moreFiltersActive = false,
  });

  @override
  State<SearchFilterBar> createState() => _SearchFilterBarState();
}

class _SearchFilterBarState extends State<SearchFilterBar> {
  late final TextEditingController _controller;
  Timer? _debounce;

  @override
  void initState() {
    super.initState();
    _controller = TextEditingController(text: widget.initialSearch);
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _controller.dispose();
    super.dispose();
  }

  void _onChanged(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 400), () {
      widget.onSearchChanged(value);
    });
  }

  void _clear() {
    _controller.clear();
    _debounce?.cancel();
    widget.onSearchChanged('');
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      color: AppColors.background,
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
      child: Column(
        children: [
          // Search field (+ optional "more filters" button)
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _controller,
                  onChanged: _onChanged,
                  textInputAction: TextInputAction.search,
                  style: GoogleFonts.inter(
                    fontSize: 13.5,
                    color: AppColors.text1,
                  ),
                  decoration: InputDecoration(
                    isDense: true,
                    hintText: widget.hint,
                    hintStyle: GoogleFonts.inter(
                      fontSize: 13.5,
                      color: AppColors.text3,
                    ),
                    prefixIcon: const Icon(
                      Icons.search_rounded,
                      size: 19,
                      color: AppColors.text3,
                    ),
                    suffixIcon: ValueListenableBuilder<TextEditingValue>(
                      valueListenable: _controller,
                      builder: (_, value, _) => value.text.isEmpty
                          ? const SizedBox.shrink()
                          : IconButton(
                              icon: const Icon(
                                Icons.close_rounded,
                                size: 17,
                                color: AppColors.text3,
                              ),
                              onPressed: _clear,
                              splashRadius: 18,
                            ),
                    ),
                    filled: true,
                    fillColor: AppColors.surface,
                    contentPadding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 11,
                    ),
                    border: _border(AppColors.border),
                    enabledBorder: _border(AppColors.border),
                    focusedBorder: _border(AppColors.accent),
                  ),
                ),
              ),
              if (widget.onMoreFilters != null) ...[
                const SizedBox(width: 8),
                _FilterIconButton(
                  active: widget.moreFiltersActive,
                  onTap: widget.onMoreFilters!,
                ),
              ],
            ],
          ),
          const SizedBox(height: 10),
          // Status filter chips
          SizedBox(
            height: 32,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: widget.statuses.length,
              separatorBuilder: (_, _) => const SizedBox(width: 8),
              itemBuilder: (_, i) {
                final opt = widget.statuses[i];
                final selected = widget.selectedStatus == opt.value;
                return _FilterChip(
                  label: opt.label,
                  selected: selected,
                  onTap: () => widget.onStatusChanged(opt.value),
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  OutlineInputBorder _border(Color color) => OutlineInputBorder(
    borderRadius: BorderRadius.circular(10),
    borderSide: BorderSide(color: color),
  );
}

/// The "more filters" trigger next to the search field. Shows a small accent
/// dot when a filter beyond search/status is currently applied.
class _FilterIconButton extends StatelessWidget {
  final bool active;
  final VoidCallback onTap;

  const _FilterIconButton({required this.active, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 44,
        height: 44,
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(
            color: active ? AppColors.accent : AppColors.border,
          ),
        ),
        child: Stack(
          clipBehavior: Clip.none,
          alignment: Alignment.center,
          children: [
            Icon(
              Icons.tune_rounded,
              size: 19,
              color: active ? AppColors.accent : AppColors.text3,
            ),
            if (active)
              Positioned(
                top: 8,
                right: 9,
                child: Container(
                  width: 7,
                  height: 7,
                  decoration: const BoxDecoration(
                    color: AppColors.accent,
                    shape: BoxShape.circle,
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _FilterChip extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;

  const _FilterChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        alignment: Alignment.center,
        padding: const EdgeInsets.symmetric(horizontal: 14),
        decoration: BoxDecoration(
          color: selected ? AppColors.accent : AppColors.surface,
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
