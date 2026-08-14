import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../providers/auth_provider.dart';
import 'activate_account_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _idController = TextEditingController();
  final _passwordController = TextEditingController();

  @override
  void dispose() {
    _idController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _handleSignIn() async {
    final id = _idController.text.trim();
    final password = _passwordController.text;
    if (id.isEmpty || password.isEmpty) return;

    final auth = context.read<AuthProvider>();
    final success = await auth.login(id, password);
    if (success && mounted) {
      Navigator.pushReplacementNamed(context, '/home');
    }
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
        body: SafeArea(
          child: Column(
            children: [
              Expanded(
                child: SingleChildScrollView(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.center,
                    children: [
                      const SizedBox(height: 60),
                      _buildLogoChip(),
                      const SizedBox(height: 20),
                      Text(
                        'Student Portal',
                        style: GoogleFonts.inter(
                          fontSize: 22,
                          fontWeight: FontWeight.w600,
                          color: AppColors.text1,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        'Misamis University',
                        style: GoogleFonts.inter(
                          fontSize: 13,
                          color: AppColors.text3,
                        ),
                      ),
                      const SizedBox(height: 32),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 28),
                        child: _buildForm(context, auth),
                      ),
                    ],
                  ),
                ),
              ),
              // Footer – always pinned to bottom above keyboard
              Padding(
                padding: const EdgeInsets.only(top: 16, bottom: 28),
                child: Text(
                  'Student Referral System v1.0 · MU',
                  style: GoogleFonts.inter(
                    fontSize: 11,
                    color: AppColors.text3,
                  ),
                ),
              ),
            ],
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
        Icons.school_rounded,
        color: AppColors.accent,
        size: 28,
      ),
    );
  }

  Widget _buildForm(BuildContext context, AuthProvider auth) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // ── Student ID field ─────────────────────────────
        const _FieldLabel('Student ID'),
        const SizedBox(height: 6),
        _NavyTextField(
          controller: _idController,
          icon: Icons.badge_outlined,
          keyboardType: TextInputType.text,
          textInputAction: TextInputAction.next,
          onSubmitted: (_) => FocusScope.of(context).nextFocus(),
        ),
        const SizedBox(height: 16),

        // ── Password field ───────────────────────────────
        const _FieldLabel('Password'),
        const SizedBox(height: 6),
        _NavyTextField(
          controller: _passwordController,
          icon: Icons.lock_outline_rounded,
          obscureText: true,
          textInputAction: TextInputAction.done,
          onSubmitted: (_) => _handleSignIn(),
        ),

        // ── Error banner (shown only on failure) ─────────
        if (auth.errorMessage != null) ...[
          const SizedBox(height: 12),
          _ErrorBanner(auth.errorMessage!),
        ],
        const SizedBox(height: 20),

        // ── Sign in button ───────────────────────────────
        SizedBox(
          width: double.infinity,
          child: _SignInButton(
            isLoading: auth.isLoading,
            onPressed: auth.isLoading ? null : _handleSignIn,
          ),
        ),
        const SizedBox(height: 12),

        // ── First-time activation ────────────────────────
        Center(
          child: GestureDetector(
            onTap: () => Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) => const ActivateAccountScreen(),
              ),
            ),
            child: RichText(
              textAlign: TextAlign.center,
              text: TextSpan(
                style: GoogleFonts.inter(
                  fontSize: 12,
                  color: AppColors.text3,
                ),
                children: [
                  const TextSpan(text: 'First time? '),
                  TextSpan(
                    text: 'Activate your account',
                    style: GoogleFonts.inter(
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                      color: AppColors.accent,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
        const SizedBox(height: 8),

        // ── Forgot password ──────────────────────────────
        Center(
          child: RichText(
            textAlign: TextAlign.center,
            text: TextSpan(
              style: GoogleFonts.inter(
                fontSize: 12,
                color: AppColors.text3,
              ),
              children: [
                const TextSpan(text: 'Forgot password? '),
                TextSpan(
                  text: 'Contact admin',
                  style: GoogleFonts.inter(
                    fontSize: 12,
                    color: AppColors.navyAccent,
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

// ─────────────────────────────────────────────────────────
// Private helper widgets
// ─────────────────────────────────────────────────────────

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
  final TextInputType? keyboardType;
  final TextInputAction? textInputAction;
  final ValueChanged<String>? onSubmitted;

  const _NavyTextField({
    required this.controller,
    required this.icon,
    this.obscureText = false,
    this.keyboardType,
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
      keyboardType: keyboardType,
      textInputAction: textInputAction,
      onSubmitted: onSubmitted,
      cursorColor: AppColors.accent,
      autocorrect: false,
      enableSuggestions: !obscureText,
      style: GoogleFonts.inter(
        fontSize: 13.5,
        color: AppColors.text1,
      ),
      decoration: InputDecoration(
        filled: true,
        fillColor: AppColors.background,
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 14, vertical: 11),
        prefixIcon: Icon(
          icon,
          color: AppColors.text3,
          size: 18,
        ),
        prefixIconConstraints:
            const BoxConstraints(minWidth: 44, minHeight: 44),
        border: defaultBorder,
        enabledBorder: defaultBorder,
        focusedBorder: focusedBorder,
        // Remove default error/disabled borders
        errorBorder: defaultBorder,
        focusedErrorBorder: focusedBorder,
      ),
    );
  }
}

class _SignInButton extends StatelessWidget {
  final bool isLoading;
  final VoidCallback? onPressed;

  const _SignInButton({required this.isLoading, this.onPressed});

  @override
  Widget build(BuildContext context) {
    return ElevatedButton(
      onPressed: onPressed,
      style: ElevatedButton.styleFrom(
        backgroundColor: AppColors.accent,
        // Keep accent color even when disabled (loading state)
        disabledBackgroundColor: const Color(0xFF2E6FD4),
        foregroundColor: AppColors.navyText,
        disabledForegroundColor: AppColors.navyText,
        padding: const EdgeInsets.symmetric(vertical: 13),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(10),
        ),
        elevation: 0,
      ),
      child: isLoading
          ? const SizedBox(
              width: 20,
              height: 20,
              child: CircularProgressIndicator(
                strokeWidth: 2,
                color: AppColors.navyText,
              ),
            )
          : Text(
              'Sign in',
              style: GoogleFonts.inter(
                fontSize: 14,
                fontWeight: FontWeight.w500,
                color: AppColors.navyText,
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
