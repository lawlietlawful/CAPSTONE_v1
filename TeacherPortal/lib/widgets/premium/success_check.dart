import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/constants/app_colors.dart';
import 'motion.dart';

/// The "done" moment after a submission: a green circle pops in and the tick
/// draws itself, with a light tap of haptic feedback on phones.
class SuccessCheck extends StatefulWidget {
  final double size;
  const SuccessCheck({super.key, this.size = 40});

  @override
  State<SuccessCheck> createState() => _SuccessCheckState();
}

class _SuccessCheckState extends State<SuccessCheck> with SingleTickerProviderStateMixin {
  late final AnimationController _controller =
      AnimationController(vsync: this, duration: const Duration(milliseconds: 700));

  @override
  void initState() {
    super.initState();
    HapticFeedback.lightImpact();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (Motion.reduced(context)) {
      _controller.value = 1;
    } else if (_controller.value == 0) {
      _controller.forward();
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final pop = CurvedAnimation(parent: _controller, curve: const Interval(0, 0.55, curve: Curves.easeOutBack));
    final draw = CurvedAnimation(parent: _controller, curve: const Interval(0.35, 1, curve: Curves.easeOutCubic));
    return AnimatedBuilder(
      animation: _controller,
      builder: (_, _) => Transform.scale(
        scale: pop.value,
        child: Container(
          width: widget.size,
          height: widget.size,
          decoration: const BoxDecoration(color: AppColors.green, shape: BoxShape.circle),
          child: CustomPaint(painter: _TickPainter(progress: draw.value)),
        ),
      ),
    );
  }
}

class _TickPainter extends CustomPainter {
  final double progress;
  _TickPainter({required this.progress});

  @override
  void paint(Canvas canvas, Size size) {
    if (progress <= 0) return;
    final w = size.width;
    final path = Path()
      ..moveTo(w * 0.28, w * 0.52)
      ..lineTo(w * 0.44, w * 0.67)
      ..lineTo(w * 0.73, w * 0.36);
    final paint = Paint()
      ..color = Colors.white
      ..style = PaintingStyle.stroke
      ..strokeWidth = w * 0.09
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round;
    final metric = path.computeMetrics().first;
    canvas.drawPath(metric.extractPath(0, metric.length * progress), paint);
  }

  @override
  bool shouldRepaint(_TickPainter old) => old.progress != progress;
}
