import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../providers/auth_provider.dart';

/// First-time account setup: the Admin has already created the student's
/// profile with a locked placeholder password. Here the student proves
/// identity with their Student ID + birthdate (already on file) and picks
/// their own password.
class ActivateAccountScreen extends StatefulWidget {
  const ActivateAccountScreen({super.key});

  @override
  State<ActivateAccountScreen> createState() => _ActivateAccountScreenState();
}

class _ActivateAccountScreenState extends State<ActivateAccountScreen> {
  final _idController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmController = TextEditingController();
  DateTime? _birthdate;

  @override
  void dispose() {
    _idController.dispose();
    _passwordController.dispose();
    _confirmController.dispose();
    super.dispose();
  }

  Future<void> _pickBirthdate() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: DateTime(now.year - 18, now.month, now.day),
      firstDate: DateTime(now.year - 100),
      lastDate: now,
      helpText: 'Select your birthdate',
    );
    if (picked != null) {
      setState(() => _birthdate = picked);
    }
  }

  Future<void> _handleActivate() async {
    final id = _idController.text.trim();
    final password = _passwordController.text;
    final confirm = _confirmController.text;
    final auth = context.read<AuthProvider>();

    if (id.isEmpty || _birthdate == null || password.isEmpty || confirm.isEmpty) {
      _showError('Please fill in all fields.');
      return;
    }
    if (password.length < 8) {
      _showError('Password must be at least 8 characters.');
      return;
    }
    if (password != confirm) {
      _showError('Password and confirmation do not match.');
      return;
    }

    final success = await auth.activate(
      studentIdNumber: id,
      birthdate: _birthdate!,
      newPassword: password,
    );
    if (success && mounted) {
      Navigator.pushReplacementNamed(context, '/home');
    }
  }

  void _showError(String message) {
    // Reuses AuthProvider's error slot so the same banner used for server
    // errors also shows client-side validation issues.
    context.read<AuthProvider>().setLocalError(message);
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();

    return AnnotatedRegion<SystemUiOverlayStyle>(
      // Dark status-bar icons for the light background.
      value: SystemUiOverlayStyle.dark,
      child: Scaffold(
        backgroundColor: AppColors.surface,
        resizeToAvoidBottomInset: true,
        appBar: AppBar(
          backgroundColor: AppColors.surface,
          elevation: 0,
          iconTheme: const IconThemeData(color: AppColors.text1),
        ),
        body: SafeArea(
          top: false,
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 28),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                _buildLogoChip(),
                const SizedBox(height: 20),
                Text(
                  'Activate Your Account',
                  style: GoogleFonts.inter(
                    fontSize: 22,
                    fontWeight: FontWeight.w600,
                    color: AppColors.text1,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  'Verify your identity to set up your password',
                  textAlign: TextAlign.center,
                  style: GoogleFonts.inter(
                    fontSize: 13,
                    color: AppColors.text3,
                  ),
                ),
                const SizedBox(height: 32),
                _buildForm(context, auth),
                const SizedBox(height: 24),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildLogoChip() {
    return Container(
      width: 64,
      height: 64,
      decoration: BoxDecoration(
        color: AppColors.accentLight,
        borderRadius: BorderRadius.circular(18),
      ),
      child: const Icon(
        Icons.verified_user_outlined,
        color: AppColors.accent,
        size: 28,
      ),
    );
  }

  Widget _buildForm(BuildContext context, AuthProvider auth) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const _FieldLabel('Student ID'),
        const SizedBox(height: 6),
        _NavyTextField(
          controller: _idController,
          icon: Icons.badge_outlined,
          textInputAction: TextInputAction.next,
        ),
        const SizedBox(height: 16),

        const _FieldLabel('Birthdate'),
        const SizedBox(height: 6),
        _BirthdateField(value: _birthdate, onTap: _pickBirthdate),
        const SizedBox(height: 16),

        const _FieldLabel('New Password'),
        const SizedBox(height: 6),
        _NavyTextField(
          controller: _passwordController,
          icon: Icons.lock_outline_rounded,
          obscureText: true,
          textInputAction: TextInputAction.next,
        ),
        const SizedBox(height: 16),

        const _FieldLabel('Confirm New Password'),
        const SizedBox(height: 6),
        _NavyTextField(
          controller: _confirmController,
          icon: Icons.lock_outline_rounded,
          obscureText: true,
          textInputAction: TextInputAction.done,
          onSubmitted: (_) => _handleActivate(),
        ),

        if (auth.errorMessage != null) ...[
          const SizedBox(height: 12),
          _ErrorBanner(auth.errorMessage!),
        ],
        const SizedBox(height: 20),

        SizedBox(
          width: double.infinity,
          child: ElevatedButton(
            onPressed: auth.isLoading ? null : _handleActivate,
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.accent,
              disabledBackgroundColor: const Color(0xFF2E6FD4),
              foregroundColor: AppColors.navyText,
              disabledForegroundColor: AppColors.navyText,
              padding: const EdgeInsets.symmetric(vertical: 13),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(10),
              ),
              elevation: 0,
            ),
            child: auth.isLoading
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: AppColors.navyText,
                    ),
                  )
                : Text(
                    'Activate Account',
                    style: GoogleFonts.inter(
                      fontSize: 14,
                      fontWeight: FontWeight.w500,
                      color: AppColors.navyText,
                    ),
                  ),
          ),
        ),
      ],
    );
  }
}

class _FieldLabel extends StatelessWidget {
  final String text;
  const _FieldLabel(this.text);

  @override
  Widget build(BuildContext context) {
    return Text(
      text,
      style: GoogleFonts.inter(
        fontSize: 12,
        color: AppColors.text2,
      ),
    );
  }
}

class _NavyTextField extends StatelessWidget {
  final TextEditingController controller;
  final IconData icon;
  final bool obscureText;
  final TextInputAction? textInputAction;
  final ValueChanged<String>? onSubmitted;

  const _NavyTextField({
    required this.controller,
    required this.icon,
    this.obscureText = false,
    this.textInputAction,
    this.onSubmitted,
  });

  @override
  Widget build(BuildContext context) {
    const borderSide = BorderSide(color: AppColors.border);
    const defaultBorder = OutlineInputBorder(
      borderRadius: BorderRadius.all(Radius.circular(10)),
      borderSide: borderSide,
    );
    const focusedBorder = OutlineInputBorder(
      borderRadius: BorderRadius.all(Radius.circular(10)),
      borderSide: BorderSide(color: AppColors.accent, width: 1.5),
    );

    return TextField(
      controller: controller,
      obscureText: obscureText,
      textInputAction: textInputAction,
      onSubmitted: onSubmitted,
      cursorColor: AppColors.accent,
      autocorrect: false,
      enableSuggestions: !obscureText,
      style: GoogleFonts.inter(fontSize: 13.5, color: AppColors.text1),
      decoration: InputDecoration(
        filled: true,
        fillColor: AppColors.background,
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 14, vertical: 11),
        prefixIcon: Icon(icon, color: AppColors.text3, size: 18),
        prefixIconConstraints:
            const BoxConstraints(minWidth: 44, minHeight: 44),
        border: defaultBorder,
        enabledBorder: defaultBorder,
        focusedBorder: focusedBorder,
        errorBorder: defaultBorder,
        focusedErrorBorder: focusedBorder,
      ),
    );
  }
}

class _BirthdateField extends StatelessWidget {
  final DateTime? value;
  final VoidCallback onTap;

  const _BirthdateField({required this.value, required this.onTap});

  @override
  Widget build(BuildContext context) {
    const defaultBorder = OutlineInputBorder(
      borderRadius: BorderRadius.all(Radius.circular(10)),
      borderSide: BorderSide(color: AppColors.border),
    );

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(10),
      child: InputDecorator(
        decoration: const InputDecoration(
          filled: true,
          fillColor: AppColors.background,
          contentPadding: EdgeInsets.symmetric(horizontal: 14, vertical: 13),
          prefixIcon: Icon(Icons.cake_outlined,
              color: AppColors.text3, size: 18),
          prefixIconConstraints: BoxConstraints(minWidth: 44, minHeight: 44),
          border: defaultBorder,
          enabledBorder: defaultBorder,
          focusedBorder: defaultBorder,
        ),
        child: Text(
          value == null ? 'Select your birthdate' : DateFormat.yMMMd().format(value!),
          style: GoogleFonts.inter(
            fontSize: 13.5,
            color: value == null ? AppColors.text3 : AppColors.text1,
          ),
        ),
      ),
    );
  }
}

class _ErrorBanner extends StatelessWidget {
  final String message;
  const _ErrorBanner(this.message);

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
          const Icon(
            Icons.error_outline_rounded,
            color: AppColors.redText,
            size: 16,
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              message,
              style: GoogleFonts.inter(
                fontSize: 12,
                color: AppColors.redText,
                height: 1.4,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
