import 'package:flutter/material.dart';

import '../../core/constants/app_colors.dart';
import '../../core/constants/app_decor.dart';
import 'motion.dart';

/// Grey placeholders in the shape of the content while it loads. The blocks
/// pulse gently together, so the screen never looks empty or stuck; only
/// the blocks move, the cards around them stay white.
class Shimmer extends StatefulWidget {
  final Widget child;
  const Shimmer({super.key, required this.child});

  @override
  State<Shimmer> createState() => _ShimmerState();
}

class _ShimmerState extends State<Shimmer> with SingleTickerProviderStateMixin {
  late final AnimationController _controller =
      AnimationController(vsync: this, duration: const Duration(milliseconds: 900));

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (Motion.reduced(context)) {
      _controller.stop();
    } else if (!_controller.isAnimating) {
      _controller.repeat(reverse: true);
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) =>
      _ShimmerScope(animation: _controller, child: widget.child);
}

class _ShimmerScope extends InheritedWidget {
  final Animation<double> animation;
  const _ShimmerScope({required this.animation, required super.child});

  @override
  bool updateShouldNotify(_ShimmerScope old) => old.animation != animation;
}

/// One grey block; pulses when inside a [Shimmer].
class SkeletonBox extends StatelessWidget {
  final double? width;
  final double height;
  final double radius;

  const SkeletonBox({super.key, this.width, this.height = 12, this.radius = 6});

  static const _from = Color(0xFFE6EBF2);
  static const _to = Color(0xFFF2F5F9);

  @override
  Widget build(BuildContext context) {
    final animation = context.dependOnInheritedWidgetOfExactType<_ShimmerScope>()?.animation;
    Widget box(Color color) => Container(
          width: width,
          height: height,
          decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(radius)),
        );
    if (animation == null) return box(_from);
    return AnimatedBuilder(
      animation: animation,
      builder: (_, _) => box(Color.lerp(_from, _to, Curves.easeInOut.transform(animation.value))!),
    );
  }
}

/// A card-shaped placeholder: avatar block plus two or three text lines.
class SkeletonCard extends StatelessWidget {
  final double height;
  final bool leading;

  const SkeletonCard({super.key, this.height = 86, this.leading = false});

  @override
  Widget build(BuildContext context) {
    return Container(
      height: height,
      padding: const EdgeInsets.all(16),
      decoration: AppDecor.card(shadow: const []),
      child: Row(
        children: [
          if (leading) ...[
            const SkeletonBox(width: 42, height: 42, radius: 12),
            const SizedBox(width: 14),
          ],
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                SkeletonBox(width: 150, height: 13),
                SizedBox(height: 10),
                SkeletonBox(width: 220, height: 10),
                SizedBox(height: 8),
                SkeletonBox(width: 90, height: 10),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// A list's worth of card placeholders.
class SkeletonList extends StatelessWidget {
  final int count;
  final bool leading;
  final EdgeInsetsGeometry padding;

  const SkeletonList({
    super.key,
    this.count = 5,
    this.leading = false,
    this.padding = const EdgeInsets.fromLTRB(16, 16, 16, 16),
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      color: AppColors.background,
      child: Shimmer(
        child: ListView.separated(
          physics: const NeverScrollableScrollPhysics(),
          padding: padding,
          itemCount: count,
          separatorBuilder: (_, _) => const SizedBox(height: 10),
          itemBuilder: (_, _) => SkeletonCard(leading: leading),
        ),
      ),
    );
  }
}
