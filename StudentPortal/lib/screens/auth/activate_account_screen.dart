import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../core/utils/password_rules.dart';
import '../../providers/auth_provider.dart';
import '../../providers/session_reset.dart';
import '../../widgets/common/auth_scaffold.dart';
import '../../widgets/common/auth_widgets.dart';

/// First-time account setup for students. The Guidance Office created the
/// account with a locked password and issued a one-time activation code. Here
/// the student proves identity with their Student ID + that code, and picks
/// their own password. Mirrors the teacher activation flow.
class ActivateAccountScreen extends StatefulWidget {
  const ActivateAccountScreen({super.key});

  @override
  State<ActivateAccountScreen> createState() => _ActivateAccountScreenState();
}

class _ActivateAccountScreenState extends State<ActivateAccountScreen> {
  final _idController = TextEditingController();
  final _codeController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmController = TextEditingController();
  final _shake = GlobalKey<ShakeState>();

  @override
  void dispose() {
    _idController.dispose();
    _codeController.dispose();
    _passwordController.dispose();
    _confirmController.dispose();
    super.dispose();
  }

  Future<void> _handleActivate() async {
    final id = _idController.text.trim();
    final code = _codeController.text.trim();
    final password = _passwordController.text;
    final confirm = _confirmController.text;
    final auth = context.read<AuthProvider>();

    if (id.isEmpty || code.isEmpty || password.isEmpty || confirm.isEmpty) {
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
      studentIdNumber: id,
      activationCode: code,
      newPassword: password,
    );
    if (!mounted) return;
    if (success) {
      resetStudentData(context);
      Navigator.pushReplacementNamed(context, '/home');
    } else {
      _shake.currentState?.shake();
    }
  }

  void _showError(String message) {
    context.read<AuthProvider>().setLocalError(message);
    _shake.currentState?.shake();
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();

    return AuthScaffold(
      portalName: 'Student Portal',
      portalIcon: Icons.school_rounded,
      showBack: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const AuthHeading(
            title: 'Activate your account',
            subtitle: 'Enter your Student ID and the one-time code from the Guidance Office, then choose your password.',
          ),
          const SizedBox(height: 24),
          Shake(key: _shake, child: _buildForm(context, auth)),
        ],
      ),
    );
  }

  Widget _buildForm(BuildContext context, AuthProvider auth) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        AuthTextField(
          controller: _idController,
          icon: Icons.badge_outlined,
          label: 'Enter your Student ID',
          textInputAction: TextInputAction.next,
        ),
        const SizedBox(height: 18),

        AuthTextField(
          controller: _codeController,
          icon: Icons.vpn_key_outlined,
          label: 'Enter your activation code',
          textCapitalization: TextCapitalization.characters,
          textInputAction: TextInputAction.next,
        ),
        const SizedBox(height: 18),

        AuthTextField(
          controller: _passwordController,
          icon: Icons.lock_outline_rounded,
          label: 'Create a new password',
          obscureText: true,
          textInputAction: TextInputAction.next,
        ),
        const SizedBox(height: 6),
        Text(
          PasswordRules.summary,
          style: GoogleFonts.publicSans(fontSize: 12, color: AppColors.text3),
        ),
        const SizedBox(height: 18),

        AuthTextField(
          controller: _confirmController,
          icon: Icons.lock_outline_rounded,
          label: 'Re-enter your new password',
          obscureText: true,
          textInputAction: TextInputAction.done,
          onSubmitted: (_) => _handleActivate(),
        ),

        if (auth.errorMessage != null) ...[
          const SizedBox(height: 12),
          AuthErrorBanner(auth.errorMessage!),
        ],
        const SizedBox(height: 20),

        AuthPrimaryButton(
          label: 'Activate account',
          loading: auth.isLoading,
          onPressed: _handleActivate,
        ),
      ],
    );
  }
}
