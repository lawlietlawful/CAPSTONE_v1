import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../core/utils/password_rules.dart';
import '../../providers/auth_provider.dart';
import '../../providers/teacher_provider.dart';
import '../../widgets/common/auth_widgets.dart';

/// First-time account setup for teachers. The admin has already created the
/// account with a locked password and issued a one-time activation code. Here
/// the teacher proves identity with their Employee ID + that code, and picks
/// their own password. Mirrors the student activation flow.
class ActivateAccountScreen extends StatefulWidget {
  const ActivateAccountScreen({super.key});

  @override
  State<ActivateAccountScreen> createState() => _ActivateAccountScreenState();
}

class _ActivateAccountScreenState extends State<ActivateAccountScreen> {
  final _schoolIdController = TextEditingController();
  final _codeController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmController = TextEditingController();

  @override
  void dispose() {
    _schoolIdController.dispose();
    _codeController.dispose();
    _passwordController.dispose();
    _confirmController.dispose();
    super.dispose();
  }

  Future<void> _handleActivate() async {
    final schoolId = _schoolIdController.text.trim();
    final code = _codeController.text.trim();
    final password = _passwordController.text;
    final confirm = _confirmController.text;
    final auth = context.read<AuthProvider>();

    if (schoolId.isEmpty || code.isEmpty || password.isEmpty || confirm.isEmpty) {
      _showError('Please fill in all fields.');
      return;
    }
    final weakness = PasswordRules.problem(password);
    if (weakness != null) {
      _showError(weakness);
      return;
    }
    if (password != confirm) {
      _showError('Password and confirmation do not match.');
      return;
    }

    final success = await auth.activate(
      schoolId: schoolId,
      activationCode: code,
      newPassword: password,
    );
    if (success && mounted) {
      context.read<TeacherProvider>().reset();
      Navigator.pushReplacementNamed(context, '/home');
    }
  }

  void _showError(String message) {
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
                  'Enter the one-time code from your administrator to set your password',
                  textAlign: TextAlign.center,
                  style: GoogleFonts.inter(
                    fontSize: 13,
                    color: AppColors.text3,
                    height: 1.4,
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
        const AuthFieldLabel('Employee ID'),
        const SizedBox(height: 6),
        AuthTextField(
          controller: _schoolIdController,
          icon: Icons.badge_outlined,
          textInputAction: TextInputAction.next,
        ),
        const SizedBox(height: 16),

        const AuthFieldLabel('Activation Code'),
        const SizedBox(height: 6),
        AuthTextField(
          controller: _codeController,
          icon: Icons.vpn_key_outlined,
          textCapitalization: TextCapitalization.characters,
          textInputAction: TextInputAction.next,
        ),
        const SizedBox(height: 16),

        const AuthFieldLabel('New Password'),
        const SizedBox(height: 6),
        AuthTextField(
          controller: _passwordController,
          icon: Icons.lock_outline_rounded,
          obscureText: true,
          textInputAction: TextInputAction.next,
        ),
        const SizedBox(height: 6),
        Text(
          PasswordRules.summary,
          style: GoogleFonts.inter(fontSize: 11, color: AppColors.text3),
        ),
        const SizedBox(height: 16),

        const AuthFieldLabel('Confirm New Password'),
        const SizedBox(height: 6),
        AuthTextField(
          controller: _confirmController,
          icon: Icons.lock_outline_rounded,
          obscureText: true,
          textInputAction: TextInputAction.done,
          onSubmitted: (_) => _handleActivate(),
        ),

        if (auth.errorMessage != null) ...[
          const SizedBox(height: 12),
          AuthErrorBanner(auth.errorMessage!),
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
