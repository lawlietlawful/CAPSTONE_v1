import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../core/constants/app_colors.dart';
import 'motion.dart';

/// A half-circle gauge for a 0–100 risk score: the arc sweeps up to the
/// score in the level's color, with the number counting up in the middle.
class RiskGauge extends StatelessWidget {
  final double score; // 0–100
  final Color color;
  final String label; // e.g. "Moderate"
  final double size;

  const RiskGauge({
    super.key,
    required this.score,
    required this.color,
    required this.label,
    this.size = 190,
  });

  @override
  Widget build(BuildContext context) {
    final target = (score.clamp(0, 100)) / 100;
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: Motion.reduced(context) ? target : 0, end: target),
      duration: const Duration(milliseconds: 900),
      curve: Curves.easeOutCubic,
      builder: (context, t, _) => SizedBox(
        width: size,
        height: size / 2 + 34,
        child: Stack(
          alignment: Alignment.topCenter,
          children: [
            SizedBox(
              width: size,
              height: size / 2 + 8,
              child: CustomPaint(
                painter: _GaugePainter(progress: t, color: color),
              ),
            ),
            // The number sits inside the arc, the label just below its ends.
            Positioned(
              top: size * 0.25,
              child: Text(
                '${(t * 100).round()}',
                style: GoogleFonts.inter(
                  fontSize: 32,
                  fontWeight: FontWeight.w700,
                  color: AppColors.text1,
                  height: 1.1,
                  fontFeatures: const [FontFeature.tabularFigures()],
                ),
              ),
            ),
            Positioned(
              bottom: 0,
              child: Text(
                '$label · out of 100',
                style: GoogleFonts.inter(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color: color,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _GaugePainter extends CustomPainter {
  final double progress;
  final Color color;

  _GaugePainter({required this.progress, required this.color});

  @override
  void paint(Canvas canvas, Size size) {
    const stroke = 14.0;
    final rect = Rect.fromLTWH(
      stroke / 2,
      stroke / 2,
      size.width - stroke,
      size.width - stroke,
    );
    final track = Paint()
      ..color = const Color(0xFFEEF2F7)
      ..style = PaintingStyle.stroke
      ..strokeWidth = stroke
      ..strokeCap = StrokeCap.round;
    final value = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = stroke
      ..strokeCap = StrokeCap.round;

    canvas.drawArc(rect, math.pi, math.pi, false, track);
    if (progress > 0) {
      canvas.drawArc(rect, math.pi, math.pi * progress, false, value);
    }
  }

  @override
  bool shouldRepaint(_GaugePainter old) =>
      old.progress != progress || old.color != color;
}
