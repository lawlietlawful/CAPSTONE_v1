import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../core/constants/app_colors.dart';
import '../../core/constants/app_text_styles.dart';

/// Field label above an input. Shared by every form in the app. Text in
/// parentheses ("Location (optional)") is shown lighter.
class FieldLabel extends StatelessWidget {
  final String text;
  const FieldLabel(this.text, {super.key});

  @override
  Widget build(BuildContext context) {
    final style = GoogleFonts.publicSans(fontSize: 13.5, fontWeight: FontWeight.w600, color: AppColors.text1);
    final open = text.indexOf(' (');
    if (open < 0) return Text(text, style: style);
    return Text.rich(TextSpan(children: [
      TextSpan(text: text.substring(0, open), style: style),
      TextSpan(
        text: text.substring(open),
        style: style.copyWith(fontWeight: FontWeight.w400, color: AppColors.text3),
      ),
    ]));
  }
}

/// A tappable, input-looking row that opens a picker (student, date, ...).
class SelectorField extends StatelessWidget {
  final IconData icon;
  final String text;
  final String? subtext;
  final bool isPlaceholder;
  final VoidCallback onTap;

  /// Shown on the right instead of the chevron once a value is picked
  /// (e.g. "Change").
  final String? actionLabel;

  const SelectorField({
    super.key,
    required this.icon,
    required this.text,
    this.subtext,
    required this.isPlaceholder,
    required this.onTap,
    this.actionLabel,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.surface,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(10),
        side: const BorderSide(color: AppColors.borderStrong),
      ),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(10),
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 50),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            child: Row(
              children: [
                Icon(icon, size: 19, color: AppColors.text2),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        text,
                        style: GoogleFonts.publicSans(
                          fontSize: 15,
                          fontWeight: isPlaceholder || subtext == null ? FontWeight.w400 : FontWeight.w600,
                          color: isPlaceholder ? AppColors.text3 : AppColors.text1,
                        ),
                      ),
                      if (subtext != null) ...[
                        const SizedBox(height: 1),
                        Text(subtext!, style: AppTextStyles.meta),
                      ],
                    ],
                  ),
                ),
                if (actionLabel != null && !isPlaceholder)
                  Text(actionLabel!, style: AppTextStyles.link)
                else
                  const Icon(Icons.expand_more_rounded, size: 20, color: AppColors.text3),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Standard bordered text input (its look comes from the app theme).
class InputField extends StatelessWidget {
  final TextEditingController controller;
  final String hint;
  final int maxLines;

  const InputField({
    super.key,
    required this.controller,
    required this.hint,
    this.maxLines = 1,
  });

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: controller,
      maxLines: maxLines,
      minLines: maxLines > 1 ? maxLines : null,
      cursorColor: AppColors.accent,
      textCapitalization: TextCapitalization.sentences,
      style: GoogleFonts.publicSans(fontSize: 15, height: 22 / 15, color: AppColors.text1),
      decoration: InputDecoration(hintText: hint),
    );
  }
}

/// Inline red banner for form/server validation errors.
class ErrorBanner extends StatelessWidget {
  final String message;
  const ErrorBanner(this.message, {super.key});

  @override
  Widget build(BuildContext context) {
    return Semantics(
      liveRegion: true,
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: AppColors.redBg,
          borderRadius: BorderRadius.circular(10),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Icon(Icons.error_outline_rounded, color: AppColors.redText, size: 18),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                message,
                style: GoogleFonts.publicSans(fontSize: 13.5, color: AppColors.redText, height: 1.4),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Empty / error / "nothing here yet" state with an optional retry action.
class CenteredMessage extends StatelessWidget {
  final IconData icon;
  final String title;
  final String message;
  final String? actionLabel;
  final VoidCallback? onAction;

  const CenteredMessage({
    super.key,
    required this.icon,
    required this.title,
    required this.message,
    this.actionLabel,
    this.onAction,
  });

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 36),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 56,
              height: 56,
              decoration: BoxDecoration(
                color: AppColors.surface,
                shape: BoxShape.circle,
                border: Border.all(color: AppColors.border),
              ),
              child: Icon(icon, size: 26, color: AppColors.text3),
            ),
            const SizedBox(height: 14),
            Text(title, textAlign: TextAlign.center, style: AppTextStyles.sectionHeading),
            const SizedBox(height: 4),
            Text(
              message,
              textAlign: TextAlign.center,
              style: AppTextStyles.meta.copyWith(fontSize: 13.5, height: 1.45),
            ),
            if (actionLabel != null && onAction != null) ...[
              const SizedBox(height: 16),
              OutlinedButton(onPressed: onAction, child: Text(actionLabel!)),
            ],
          ],
        ),
      ),
    );
  }
}

/// Full-width primary action button with a built-in loading spinner.
class PrimaryButton extends StatelessWidget {
  final String label;
  final bool loading;
  final VoidCallback? onPressed;

  const PrimaryButton({
    super.key,
    required this.label,
    this.loading = false,
    required this.onPressed,
  });

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      height: 50,
      child: FilledButton(
        onPressed: loading ? null : onPressed,
        style: FilledButton.styleFrom(
          backgroundColor: AppColors.accent,
          disabledBackgroundColor: const Color(0xFF8EA7E8),
          foregroundColor: Colors.white,
          disabledForegroundColor: Colors.white,
        ),
        child: loading
            ? const SizedBox(
                width: 20,
                height: 20,
                child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
              )
            : Text(
                label,
                style: GoogleFonts.publicSans(fontSize: 15.5, fontWeight: FontWeight.w600),
              ),
      ),
    );
  }
}

/// Trailing slot for an infinite-scroll list. Shows a small spinner while the
/// next page is loading, and collapses to nothing otherwise — so it can sit as
/// the final item of a ListView without leaving a gap on the last page.
class ListLoadMoreFooter extends StatelessWidget {
  final bool visible;
  const ListLoadMoreFooter({super.key, required this.visible});

  @override
  Widget build(BuildContext context) {
    if (!visible) return const SizedBox.shrink();
    return const Padding(
      padding: EdgeInsets.symmetric(vertical: 20),
      child: Center(
        child: SizedBox(
          width: 22,
          height: 22,
          child: CircularProgressIndicator(strokeWidth: 2.4),
        ),
      ),
    );
  }
}
