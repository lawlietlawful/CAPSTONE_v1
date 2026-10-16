import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../core/constants/app_colors.dart';

/// Pieces shared by the sign-in and activation screens.

/// A text field in the app's sign-in style: the [label] sits inside the box
/// ("Enter your Employee ID") and floats up onto the border once the field is
/// focused or filled, like Google's sign-in. A password field ([obscureText])
/// gets a show/hide button.
class AuthTextField extends StatefulWidget {
  final TextEditingController controller;
  final IconData icon;
  final bool obscureText;
  final TextInputType? keyboardType;
  final TextInputAction? textInputAction;
  final TextCapitalization textCapitalization;
  final ValueChanged<String>? onSubmitted;
  final List<String>? autofillHints;
  final String label;

  const AuthTextField({
    super.key,
    required this.controller,
    required this.icon,
    required this.label,
    this.obscureText = false,
    this.keyboardType,
    this.textInputAction,
    this.textCapitalization = TextCapitalization.none,
    this.onSubmitted,
    this.autofillHints,
  });

  @override
  State<AuthTextField> createState() => _AuthTextFieldState();
}

class _AuthTextFieldState extends State<AuthTextField> {
  bool _reveal = false;

  @override
  Widget build(BuildContext context) {
    const defaultBorder = OutlineInputBorder(
      borderRadius: BorderRadius.all(Radius.circular(10)),
      borderSide: BorderSide(color: AppColors.borderStrong),
    );
    const focusedBorder = OutlineInputBorder(
      borderRadius: BorderRadius.all(Radius.circular(10)),
      borderSide: BorderSide(color: AppColors.accent, width: 1.5),
    );

    return TextField(
      controller: widget.controller,
      obscureText: widget.obscureText && !_reveal,
      keyboardType: widget.keyboardType,
      textInputAction: widget.textInputAction,
      textCapitalization: widget.textCapitalization,
      onSubmitted: widget.onSubmitted,
      autofillHints: widget.autofillHints,
      cursorColor: AppColors.accent,
      autocorrect: false,
      enableSuggestions: !widget.obscureText,
      style: GoogleFonts.publicSans(fontSize: 15, color: AppColors.text1),
      decoration: InputDecoration(
        filled: true,
        fillColor: AppColors.surface,
        labelText: widget.label,
        labelStyle: GoogleFonts.publicSans(fontSize: 15, color: AppColors.text3),
        floatingLabelStyle: WidgetStateTextStyle.resolveWith(
          (states) => GoogleFonts.publicSans(
            fontSize: 14,
            fontWeight: FontWeight.w600,
            color: states.contains(WidgetState.focused) ? AppColors.accent : AppColors.text2,
          ),
        ),
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
        prefixIcon: Icon(widget.icon, color: AppColors.text2, size: 19),
        prefixIconConstraints:
            const BoxConstraints(minWidth: 44, minHeight: 44),
        suffixIcon: widget.obscureText
            ? IconButton(
                tooltip: _reveal ? 'Hide password' : 'Show password',
                icon: Icon(
                  _reveal
                      ? Icons.visibility_off_outlined
                      : Icons.visibility_outlined,
                  size: 20,
                  color: AppColors.text2,
                ),
                onPressed: () => setState(() => _reveal = !_reveal),
              )
            : null,
        border: defaultBorder,
        enabledBorder: defaultBorder,
        focusedBorder: focusedBorder,
        errorBorder: defaultBorder,
        focusedErrorBorder: focusedBorder,
      ),
    );
  }
}

class AuthErrorBanner extends StatelessWidget {
  final String message;
  const AuthErrorBanner(this.message, {super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: AppColors.redBg,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: const Color(0x33EF4444)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.error_outline_rounded, color: AppColors.redText, size: 16),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              message,
              style: GoogleFonts.publicSans(fontSize: 13.5, color: AppColors.redText, height: 1.4),
            ),
          ),
        ],
      ),
    );
  }
}
