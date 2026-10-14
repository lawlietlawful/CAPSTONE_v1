import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../providers/auth_provider.dart';
import '../../providers/teacher_provider.dart';
import '../../widgets/common/auth_widgets.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _handleSignIn() async {
    final email = _emailController.text.trim();
    final password = _passwordController.text;

    final auth = context.read<AuthProvider>();

    if (email.isEmpty || password.isEmpty) {
      auth.setLocalError('Please enter your Employee ID and password.');
      return;
    }

    final success = await auth.login(email, password);
    if (success && mounted) {
      // A new session starts clean, whoever used this phone before.
      context.read<TeacherProvider>().reset();
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
                        'Teacher Portal',
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
        Icons.cast_for_education_rounded,
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
          controller: _emailController,
          icon: Icons.badge_outlined,
          keyboardType: TextInputType.text,
          textInputAction: TextInputAction.next,
          autofillHints: const [AutofillHints.username],
          onSubmitted: (_) => FocusScope.of(context).nextFocus(),
        ),
        const SizedBox(height: 16),

        const AuthFieldLabel('Password'),
        const SizedBox(height: 6),
        AuthTextField(
          controller: _passwordController,
          icon: Icons.lock_outline_rounded,
          obscureText: true,
          textInputAction: TextInputAction.done,
          autofillHints: const [AutofillHints.password],
          onSubmitted: (_) => _handleSignIn(),
        ),
        const SizedBox(height: 8),
        Align(
          alignment: Alignment.centerRight,
          child: GestureDetector(
            onTap: () => _showForgotPasswordNotice(context),
            behavior: HitTestBehavior.opaque,
            child: Text(
              'Forgot password?',
              style: GoogleFonts.inter(
                fontSize: 11.5,
                fontWeight: FontWeight.w500,
                color: AppColors.text3,
              ),
            ),
          ),
        ),

        if (auth.errorMessage != null) ...[
          const SizedBox(height: 12),
          AuthErrorBanner(auth.errorMessage!),
        ],
        const SizedBox(height: 20),

        SizedBox(
          width: double.infinity,
          child: _SignInButton(
            isLoading: auth.isLoading,
            onPressed: auth.isLoading ? null : _handleSignIn,
          ),
        ),
        const SizedBox(height: 12),

        // First-time users activate with the one-time code the admin gave them.
        Center(
          child: GestureDetector(
            onTap: () => Navigator.pushNamed(context, '/activate'),
            behavior: HitTestBehavior.opaque,
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 4),
              child: RichText(
                textAlign: TextAlign.center,
                text: TextSpan(
                  style: GoogleFonts.inter(
                    fontSize: 12,
                    color: AppColors.text3,
                  ),
                  children: [
                    const TextSpan(text: 'First time here? '),
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
        ),
      ],
    );
  }
}

/// There is no self-service password reset yet — the account was set up by
/// an administrator in the first place, so that's also who can issue a new
/// one-time code. This just makes that path discoverable instead of leaving
/// a locked-out teacher with no visible next step.
void _showForgotPasswordNotice(BuildContext context) {
  showDialog<void>(
    context: context,
    builder: (ctx) => AlertDialog(
      title: Text(
        'Forgot your password?',
        style: GoogleFonts.inter(fontSize: 15, fontWeight: FontWeight.w600),
      ),
      content: Text(
        'Passwords are reset in person. Visit the Guidance Office with your '
        'school ID for a new activation code, then activate your account '
        'again here and choose a new password.',
        style: GoogleFonts.inter(fontSize: 13, color: AppColors.text2, height: 1.45),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(ctx),
          child: Text('Got it', style: GoogleFonts.inter(color: AppColors.accent)),
        ),
      ],
    ),
  );
}

// ─────────────────────────────────────────────────────────
// Private helper widgets
// ─────────────────────────────────────────────────────────

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
