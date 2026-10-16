import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../core/constants/app_colors.dart';
import '../../core/constants/app_decor.dart';

/// Several rows in one white card, split by hairlines — denser and calmer
/// than a separate card per row. Kept in sync with the other portal's copy.
class CardGroup extends StatelessWidget {
  final List<Widget> children;
  final EdgeInsetsGeometry? padding;

  const CardGroup({super.key, required this.children, this.padding});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: padding,
      clipBehavior: Clip.antiAlias,
      decoration: AppDecor.card(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          for (var i = 0; i < children.length; i++) ...[
            if (i > 0) const Divider(height: 1, thickness: 1, color: AppColors.divider),
            children[i],
          ],
        ],
      ),
    );
  }
}

/// A grey note with an icon: how something works, or what happens next.
class InfoNote extends StatelessWidget {
  final IconData icon;
  final String text;
  final Color background;
  final Color foreground;

  const InfoNote({
    super.key,
    required this.text,
    this.icon = Icons.info_outline_rounded,
    this.background = AppColors.background,
    this.foreground = AppColors.text2,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: background, borderRadius: BorderRadius.circular(10)),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.only(top: 1),
            child: Icon(icon, size: 18, color: foreground),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(text, style: GoogleFonts.publicSans(fontSize: 13, height: 19 / 13, color: foreground)),
          ),
        ],
      ),
    );
  }
}

/// Initials in a circle — a person in a list.
class InitialsAvatar extends StatelessWidget {
  final String initials;
  final double size;
  final bool dark;

  const InitialsAvatar({super.key, required this.initials, this.size = 38, this.dark = false});

  /// "Cruz, Ana" → "AC"; "Ana Cruz" → "AC".
  static String of(String? name) {
    final n = (name ?? '').trim();
    if (n.isEmpty) return '?';
    if (n.contains(', ')) {
      final parts = n.split(', ');
      final first = parts[1].isNotEmpty ? parts[1][0] : '';
      final last = parts[0].isNotEmpty ? parts[0][0] : '';
      return '$first$last'.toUpperCase();
    }
    final words = n.split(RegExp(r'\s+')).where((w) => w.isNotEmpty).toList();
    final letters = words.length == 1 ? words.first[0] : '${words.first[0]}${words.last[0]}';
    return letters.toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: dark ? AppColors.navy : const Color(0xFFE4EAF5),
        shape: BoxShape.circle,
      ),
      child: Text(
        initials,
        style: GoogleFonts.publicSans(
          fontSize: size * 0.34,
          fontWeight: FontWeight.w700,
          color: dark ? Colors.white : AppColors.accentDark,
        ),
      ),
    );
  }
}

/// The calendar tile on seminar cards: "OCT / 17".
class DateTile extends StatelessWidget {
  final DateTime? date;
  final bool emphasised;

  const DateTile({super.key, required this.date, this.emphasised = false});

  static const _months = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];

  @override
  Widget build(BuildContext context) {
    final d = date;
    return Container(
      width: 52,
      padding: const EdgeInsets.symmetric(vertical: 8),
      decoration: BoxDecoration(
        color: emphasised ? AppColors.navy : AppColors.background,
        borderRadius: BorderRadius.circular(10),
        border: emphasised ? null : Border.all(color: AppColors.border),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            d == null ? '—' : _months[d.month - 1],
            style: GoogleFonts.publicSans(
              fontSize: 11,
              fontWeight: FontWeight.w700,
              letterSpacing: 0.6,
              color: emphasised ? AppColors.navySubText : AppColors.accent,
            ),
          ),
          Text(
            d == null ? '' : '${d.day}',
            style: GoogleFonts.publicSans(
              fontSize: 21,
              height: 26 / 21,
              fontWeight: FontWeight.w700,
              color: emphasised ? Colors.white : AppColors.text1,
            ),
          ),
        ],
      ),
    );
  }
}

/// A rectangular choice chip: tinted with a blue edge when chosen.
class ChoiceChipButton extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;

  const ChoiceChipButton({super.key, required this.label, required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      selected: selected,
      child: Material(
        color: selected ? AppColors.accentLight : AppColors.surface,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(8),
          side: BorderSide(
            color: selected ? AppColors.accent : AppColors.borderStrong,
            width: selected ? 1.5 : 1,
          ),
        ),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(8),
          child: Container(
            height: 40,
            alignment: Alignment.center,
            padding: const EdgeInsets.symmetric(horizontal: 14),
            child: Text(
              label,
              style: GoogleFonts.publicSans(
                fontSize: 13.5,
                fontWeight: selected ? FontWeight.w600 : FontWeight.w500,
                color: selected ? AppColors.accentDark : const Color(0xFF2B3648),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// A row of equal segments on a grey track; the chosen one is a white tile.
class SegmentedTabs<T> extends StatelessWidget {
  final List<({String label, T value})> options;
  final T selected;
  final ValueChanged<T> onChanged;

  const SegmentedTabs({
    super.key,
    required this.options,
    required this.selected,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: AppColors.segmentTrack,
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        children: [
          for (final o in options)
            Expanded(
              child: _Segment(
                label: o.label,
                selected: o.value == selected,
                onTap: () => onChanged(o.value),
              ),
            ),
        ],
      ),
    );
  }
}

class _Segment extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;

  const _Segment({required this.label, required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      selected: selected,
      child: GestureDetector(
        onTap: onTap,
        behavior: HitTestBehavior.opaque,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          curve: Curves.easeOut,
          height: 38,
          alignment: Alignment.center,
          padding: const EdgeInsets.symmetric(horizontal: 4),
          decoration: BoxDecoration(
            color: selected ? AppColors.surface : Colors.transparent,
            borderRadius: BorderRadius.circular(7),
            boxShadow: selected
                ? const [BoxShadow(color: Color(0x140F172A), blurRadius: 2, offset: Offset(0, 1))]
                : const [],
          ),
          child: Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: GoogleFonts.publicSans(
              fontSize: 13,
              fontWeight: selected ? FontWeight.w600 : FontWeight.w500,
              color: selected ? AppColors.text1 : AppColors.text2,
            ),
          ),
        ),
      ),
    );
  }
}
