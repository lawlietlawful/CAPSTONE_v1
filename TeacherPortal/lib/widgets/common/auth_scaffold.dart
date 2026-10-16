import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../core/constants/app_colors.dart';

/// The frame of the sign-in and activation screens, shared by the Teacher
/// and Student Portals (identical copy in each app): a navy band with the
/// school mark and the portal's name, and a white sheet that slides up to
/// hold the form. Plain and institutional, like the website's sign-in.
class AuthScaffold extends StatefulWidget {
  final String portalName; // "Teacher Portal" / "Student Portal"
  final IconData portalIcon;
  final Widget child;
  final bool showBack;

  const AuthScaffold({
    super.key,
    required this.portalName,
    required this.portalIcon,
    required this.child,
    this.showBack = false,
  });

  @override
  State<AuthScaffold> createState() => _AuthScaffoldState();
}

class _AuthScaffoldState extends State<AuthScaffold> with SingleTickerProviderStateMixin {
  late final AnimationController _enter =
      AnimationController(vsync: this, duration: const Duration(milliseconds: 520));

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final reduce = MediaQuery.maybeDisableAnimationsOf(context) ?? false;
    if (reduce) {
      _enter.value = 1;
    } else if (_enter.value == 0) {
      _enter.forward();
    }
  }

  @override
  void dispose() {
    _enter.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final curve = CurvedAnimation(parent: _enter, curve: Curves.easeOutCubic);

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
      child: Scaffold(
        backgroundColor: AppColors.navy,
        resizeToAvoidBottomInset: true,
        body: Column(
          children: [
            // ── Navy band ──
            SafeArea(
              bottom: false,
              child: Padding(
                padding: EdgeInsets.fromLTRB(24, widget.showBack ? 4 : 28, 24, 26),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (widget.showBack)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: IconButton(
                          tooltip: 'Back',
                          padding: EdgeInsets.zero,
                          alignment: Alignment.centerLeft,
                          onPressed: () => Navigator.of(context).maybePop(),
                          icon: const Icon(Icons.arrow_back_rounded, color: AppColors.navyText),
                        ),
                      ),
                    Row(
                      children: [
                        Container(
                          width: 44,
                          height: 44,
                          decoration: BoxDecoration(
                            color: AppColors.navy2,
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: AppColors.navyBorder),
                          ),
                          child: Icon(widget.portalIcon, color: Colors.white, size: 22),
                        ),
                        const SizedBox(width: 12),
                        Text(
                          'Misamis University',
                          style: GoogleFonts.publicSans(fontSize: 14, fontWeight: FontWeight.w600, color: AppColors.navyText),
                        ),
                      ],
                    ),
                    const SizedBox(height: 22),
                    Text(
                      widget.portalName,
                      style: GoogleFonts.publicSans(fontSize: 26, fontWeight: FontWeight.w700, color: AppColors.navyText),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Guidance and Counseling Office',
                      style: GoogleFonts.publicSans(fontSize: 13.5, color: AppColors.navySubText),
                    ),
                  ],
                ),
              ),
            ),

            // ── White sheet with the form ──
            Expanded(
              child: AnimatedBuilder(
                animation: curve,
                builder: (context, child) => Opacity(
                  opacity: curve.value,
                  child: Transform.translate(offset: Offset(0, 40 * (1 - curve.value)), child: child),
                ),
                child: Container(
                  width: double.infinity,
                  decoration: const BoxDecoration(
                    color: AppColors.surface,
                    borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
                  ),
                  child: SafeArea(
                    top: false,
                    child: LayoutBuilder(
                      builder: (context, constraints) => SingleChildScrollView(
                        padding: const EdgeInsets.fromLTRB(24, 28, 24, 16),
                        child: ConstrainedBox(
                          constraints: BoxConstraints(minHeight: math.max(0, constraints.maxHeight - 44)),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              widget.child,
                              const SizedBox(height: 24),
                              Center(
                                child: Text(
                                  'Student Referral System v1.0 · MU',
                                  style: GoogleFonts.publicSans(fontSize: 12, color: AppColors.text3),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
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

/// The sheet's heading: "Sign in" plus one line of help.
class AuthHeading extends StatelessWidget {
  final String title;
  final String subtitle;
  const AuthHeading({super.key, required this.title, required this.subtitle});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(title, style: GoogleFonts.publicSans(fontSize: 21, fontWeight: FontWeight.w700, color: AppColors.text1)),
        const SizedBox(height: 4),
        Text(subtitle, style: GoogleFonts.publicSans(fontSize: 14, color: AppColors.text2, height: 1.45)),
      ],
    );
  }
}

/// Shakes its child sideways once — the "that didn't work" signal after a
/// wrong password. Call [ShakeState.shake] through a GlobalKey.
class Shake extends StatefulWidget {
  final Widget child;
  const Shake({super.key, required this.child});

  @override
  State<Shake> createState() => ShakeState();
}

class ShakeState extends State<Shake> with SingleTickerProviderStateMixin {
  late final AnimationController _controller =
      AnimationController(vsync: this, duration: const Duration(milliseconds: 420));

  void shake() {
    if (MediaQuery.maybeDisableAnimationsOf(context) ?? false) return;
    HapticFeedback.mediumImpact();
    _controller.forward(from: 0);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _controller,
      child: widget.child,
      builder: (context, child) {
        final t = _controller.value;
        final dx = math.sin(t * math.pi * 4) * 9 * (1 - t);
        return Transform.translate(offset: Offset(dx, 0), child: child);
      },
    );
  }
}

/// The main button on the sign-in sheet: loading spinner, and — while sign-in
/// is paused — a live "Try again in 0:58" that unlocks itself.
class AuthPrimaryButton extends StatelessWidget {
  final String label;
  final bool loading;
  final int lockedSeconds;
  final VoidCallback? onPressed;

  const AuthPrimaryButton({
    super.key,
    required this.label,
    required this.loading,
    this.lockedSeconds = 0,
    required this.onPressed,
  });

  @override
  Widget build(BuildContext context) {
    final locked = lockedSeconds > 0;
    final clock = '${lockedSeconds ~/ 60}:${(lockedSeconds % 60).toString().padLeft(2, '0')}';
    return SizedBox(
      width: double.infinity,
      height: 50,
      child: FilledButton(
        onPressed: loading || locked ? null : onPressed,
        style: FilledButton.styleFrom(
          backgroundColor: AppColors.accent,
          disabledBackgroundColor: locked ? const Color(0xFF9DB9EA) : AppColors.accent,
          foregroundColor: Colors.white,
          disabledForegroundColor: Colors.white,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
          elevation: 0,
        ),
        // A plain swap, not an AnimatedSwitcher: when the server can't be
        // reached the request fails instantly, and flipping ready -> loading
        // -> ready inside the fade left two "ready" children with the same
        // key ("Duplicate keys found", then a red error screen).
        child: loading
            ? const SizedBox(
                width: 20,
                height: 20,
                child: CircularProgressIndicator(strokeWidth: 2.2, color: Colors.white),
              )
            : Text(
                locked ? 'Try again in $clock' : label,
                style: GoogleFonts.publicSans(
                  fontSize: 15,
                  fontWeight: FontWeight.w600,
                  fontFeatures: const [FontFeature.tabularFigures()],
                ),
              ),
      ),
    );
  }
}

/// "Forgot password?" — passwords are reset in person, not by email.
void showForgotPasswordNotice(BuildContext context) {
  showDialog<void>(
    context: context,
    builder: (ctx) => AlertDialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      title: const Text('Forgot your password?'),
      content: Text(
        'Passwords are reset in person. Visit the Guidance Office with your '
        'school ID for a new activation code, then activate your account '
        'again here and choose a new password.',
        style: GoogleFonts.publicSans(fontSize: 13, color: AppColors.text2, height: 1.45),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(ctx),
          child: Text('Got it', style: GoogleFonts.publicSans(color: AppColors.accent, fontWeight: FontWeight.w600)),
        ),
      ],
    ),
  );
}
