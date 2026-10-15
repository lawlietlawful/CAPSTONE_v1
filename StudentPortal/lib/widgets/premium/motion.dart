import 'dart:async';

import 'package:flutter/material.dart';

/// Shared motion: one set of durations and curves, and the small widgets the
/// screens use for it. Everything here turns itself off when the phone's
/// "reduce motion" setting is on (MediaQuery.disableAnimations).
class Motion {
  static const Duration fast = Duration(milliseconds: 160);
  static const Duration medium = Duration(milliseconds: 320);
  static const Duration slow = Duration(milliseconds: 650);

  static const Curve enter = Curves.easeOutCubic;

  /// Delay between items that come in one after another.
  static const Duration stagger = Duration(milliseconds: 55);

  static bool reduced(BuildContext context) =>
      MediaQuery.maybeDisableAnimationsOf(context) ?? false;
}

/// Fades and rises its child into place when first shown. [index] staggers a
/// list: item n starts n × [Motion.stagger] later (capped, so long lists
/// don't keep the bottom waiting).
class FadeSlideIn extends StatefulWidget {
  final Widget child;
  final int index;
  final double offset;

  const FadeSlideIn({super.key, required this.child, this.index = 0, this.offset = 14});

  @override
  State<FadeSlideIn> createState() => _FadeSlideInState();
}

class _FadeSlideInState extends State<FadeSlideIn> with SingleTickerProviderStateMixin {
  late final AnimationController _controller =
      AnimationController(vsync: this, duration: Motion.medium);
  late final Animation<double> _curve = CurvedAnimation(parent: _controller, curve: Motion.enter);
  bool _started = false;
  Timer? _delay;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_started) return;
    _started = true;
    if (Motion.reduced(context)) {
      _controller.value = 1;
      return;
    }
    final steps = widget.index.clamp(0, 8);
    if (steps == 0) {
      _controller.forward();
      return;
    }
    _delay = Timer(Motion.stagger * steps, () {
      if (mounted) _controller.forward();
    });
  }

  @override
  void dispose() {
    _delay?.cancel();
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _curve,
      child: widget.child,
      builder: (context, child) => Opacity(
        opacity: _curve.value,
        child: Transform.translate(
          offset: Offset(0, widget.offset * (1 - _curve.value)),
          child: child,
        ),
      ),
    );
  }
}

/// Wraps a tappable card: it presses in slightly while held, so a tap feels
/// physical. Use instead of a bare InkWell on cards and tiles.
class Pressable extends StatefulWidget {
  final Widget child;
  final VoidCallback? onTap;
  final BorderRadius borderRadius;

  const Pressable({
    super.key,
    required this.child,
    required this.onTap,
    this.borderRadius = const BorderRadius.all(Radius.circular(16)),
  });

  @override
  State<Pressable> createState() => _PressableState();
}

class _PressableState extends State<Pressable> {
  bool _down = false;

  void _set(bool down) {
    if (_down != down && widget.onTap != null) setState(() => _down = down);
  }

  @override
  Widget build(BuildContext context) {
    final scale = _down && !Motion.reduced(context) ? 0.975 : 1.0;
    return AnimatedScale(
      scale: scale,
      duration: Motion.fast,
      curve: Curves.easeOut,
      child: Material(
        type: MaterialType.transparency,
        child: InkWell(
          onTap: widget.onTap,
          onTapDown: (_) => _set(true),
          onTapUp: (_) => _set(false),
          onTapCancel: () => _set(false),
          borderRadius: widget.borderRadius,
          child: widget.child,
        ),
      ),
    );
  }
}

/// A number that counts up to [value] when it first appears, and glides to
/// any new value after a refresh.
class CountUp extends StatelessWidget {
  final int value;
  final TextStyle? style;

  const CountUp({super.key, required this.value, this.style});

  @override
  Widget build(BuildContext context) {
    if (Motion.reduced(context)) return Text('$value', style: style);
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: value.toDouble()),
      duration: Motion.slow,
      curve: Curves.easeOutCubic,
      builder: (context, v, _) => Text(
        '${v.round()}',
        style: style?.copyWith(fontFeatures: const [FontFeature.tabularFigures()]),
      ),
    );
  }
}
