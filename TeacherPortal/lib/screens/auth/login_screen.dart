import 'dart:async';

import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../providers/auth_provider.dart';
import '../../providers/teacher_provider.dart';
import '../../widgets/common/auth_scaffold.dart';
import '../../widgets/common/auth_widgets.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _shake = GlobalKey<ShakeState>();

  /// Seconds left on a sign-in pause (after too many wrong passwords).
  int _locked = 0;
  Timer? _lockTimer;

  @override
  void dispose() {
    _lockTimer?.cancel();
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  void _startLock(int seconds) {
    _lockTimer?.cancel();
    setState(() => _locked = seconds);
    _lockTimer = Timer.periodic(const Duration(seconds: 1), (t) {
      if (!mounted) return t.cancel();
      setState(() => _locked = _locked > 0 ? _locked - 1 : 0);
      if (_locked == 0) t.cancel();
    });
  }

  Future<void> _handleSignIn() async {
    final email = _emailController.text.trim();
    final password = _passwordController.text;

    final auth = context.read<AuthProvider>();

    if (email.isEmpty || password.isEmpty) {
      auth.setLocalError('Please enter your Employee ID and password.');
      _shake.currentState?.shake();
      return;
    }

    final success = await auth.login(email, password);
    if (!mounted) return;
    if (success) {
      // A new session starts clean, whoever used this phone before.
      context.read<TeacherProvider>().reset();
      Navigator.pushReplacementNamed(context, '/home');
      return;
    }
    if (auth.retryAfter != null) _startLock(auth.retryAfter!);
    _shake.currentState?.shake();
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();

    return AuthScaffold(
      portalName: 'Teacher Portal',
      portalIcon: Icons.co_present_rounded,
      child: AutofillGroup(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const AuthHeading(
              title: 'Sign in to your account',
              subtitle: 'Use your Employee ID and the password you chose when you activated.',
            ),
            const SizedBox(height: 24),
            Shake(
              key: _shake,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  AuthTextField(
                    controller: _emailController,
                    icon: Icons.badge_outlined,
                    keyboardType: TextInputType.text,
                    textInputAction: TextInputAction.next,
                    autofillHints: const [AutofillHints.username],
                    label: 'Enter your Employee ID',
                    onSubmitted: (_) => FocusScope.of(context).nextFocus(),
                  ),
                  const SizedBox(height: 18),
                  AuthTextField(
                    controller: _passwordController,
                    icon: Icons.lock_outline_rounded,
                    label: 'Enter your password',
                    obscureText: true,
                    textInputAction: TextInputAction.done,
                    autofillHints: const [AutofillHints.password],
                    onSubmitted: (_) => _handleSignIn(),
                  ),
                ],
              ),
            ),
            Align(
              alignment: Alignment.centerRight,
              child: TextButton(
                onPressed: () => showForgotPasswordNotice(context),
                style: TextButton.styleFrom(padding: const EdgeInsets.symmetric(horizontal: 4)),
                child: Text(
                  'Forgot password?',
                  style: GoogleFonts.publicSans(fontSize: 12.5, fontWeight: FontWeight.w500, color: AppColors.accent),
                ),
              ),
            ),
            AnimatedSize(
              duration: const Duration(milliseconds: 200),
              child: auth.errorMessage == null
                  ? const SizedBox(width: double.infinity)
                  : Padding(
                      padding: const EdgeInsets.only(bottom: 14),
                      child: AuthErrorBanner(
                        _locked > 0
                            ? 'Too many sign-in attempts. Please wait for the timer, then try again.'
                            : auth.errorMessage!,
                      ),
                    ),
            ),
            AuthPrimaryButton(
              label: 'Sign in',
              loading: auth.isLoading,
              lockedSeconds: _locked,
              onPressed: _handleSignIn,
            ),
            const SizedBox(height: 18),
            // First-time users activate with the one-time code the admin gave them.
            Center(
              child: GestureDetector(
                onTap: () => Navigator.pushNamed(context, '/activate'),
                behavior: HitTestBehavior.opaque,
                child: Padding(
                  padding: const EdgeInsets.symmetric(vertical: 6),
                  child: RichText(
                    textAlign: TextAlign.center,
                    text: TextSpan(
                      style: GoogleFonts.publicSans(fontSize: 13, color: AppColors.text3),
                      children: [
                        const TextSpan(text: 'First time here? '),
                        TextSpan(
                          text: 'Activate your account',
                          style: GoogleFonts.publicSans(fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.accent),
                        ),
                      ],
                    ),
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
