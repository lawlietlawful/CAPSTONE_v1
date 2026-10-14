import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../core/constants/app_colors.dart';

/// Pieces shared by the sign-in and activation screens.

class AuthFieldLabel extends StatelessWidget {
  final String text;
  const AuthFieldLabel(this.text, {super.key});

  @override
  Widget build(BuildContext context) {
    return Text(
      text,
      style: GoogleFonts.inter(fontSize: 12, color: AppColors.text2),
    );
  }
}

/// A text field in the app's sign-in style. A password field ([obscureText])
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
  final String? hintText;

  const AuthTextField({
    super.key,
    required this.controller,
    required this.icon,
    this.obscureText = false,
    this.keyboardType,
    this.textInputAction,
    this.textCapitalization = TextCapitalization.none,
    this.onSubmitted,
    this.autofillHints,
    this.hintText,
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
      borderSide: BorderSide(color: AppColors.border),
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
      style: GoogleFonts.inter(fontSize: 13.5, color: AppColors.text1),
      decoration: InputDecoration(
        filled: true,
        fillColor: AppColors.background,
        hintText: widget.hintText,
        hintStyle: GoogleFonts.inter(fontSize: 13, color: AppColors.text3),
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 14, vertical: 11),
        prefixIcon: Icon(widget.icon, color: AppColors.text3, size: 18),
        prefixIconConstraints:
            const BoxConstraints(minWidth: 44, minHeight: 44),
        suffixIcon: widget.obscureText
            ? IconButton(
                tooltip: _reveal ? 'Hide password' : 'Show password',
                icon: Icon(
                  _reveal
                      ? Icons.visibility_off_outlined
                      : Icons.visibility_outlined,
                  size: 18,
                  color: AppColors.text3,
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
              style: GoogleFonts.inter(fontSize: 12, color: AppColors.redText, height: 1.4),
            ),
          ),
        ],
      ),
    );
  }
}
