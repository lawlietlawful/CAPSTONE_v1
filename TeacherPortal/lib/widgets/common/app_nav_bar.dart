import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../core/constants/app_colors.dart';
import '../premium/motion.dart';

/// Bottom bar: Home (dashboard), Students (monitoring), Referrals, Reports,
/// and Profile. Reporting a concern is a floating action, not a tab, because
/// it's an action rather than a destination. A soft highlight slides behind
/// the active tab's icon.
class AppNavBar extends StatelessWidget {
  final int currentIndex;
  final ValueChanged<int> onTap;

  const AppNavBar({super.key, required this.currentIndex, required this.onTap});

  static const _tabs = [
    (Icons.home_outlined, Icons.home_rounded, 'Home'),
    (Icons.groups_outlined, Icons.groups_rounded, 'Students'),
    (Icons.outbox_outlined, Icons.outbox_rounded, 'Referrals'),
    (Icons.description_outlined, Icons.description_rounded, 'Reports'),
    (Icons.person_outline_rounded, Icons.person_rounded, 'Profile'),
  ];

  static const double _indicatorWidth = 56;

  @override
  Widget build(BuildContext context) {
    final duration = Motion.reduced(context) ? Duration.zero : Motion.medium;

    return DecoratedBox(
      decoration: const BoxDecoration(
        color: AppColors.surface,
        border: Border(top: BorderSide(color: AppColors.border)),
      ),
      child: SafeArea(
        top: false,
        child: SizedBox(
          height: 66,
          child: LayoutBuilder(
            builder: (context, constraints) {
              final tabWidth = constraints.maxWidth / _tabs.length;
              return Stack(
                children: [
                  AnimatedPositioned(
                    duration: duration,
                    curve: Motion.enter,
                    left: tabWidth * currentIndex + (tabWidth - _indicatorWidth) / 2,
                    top: 9,
                    width: _indicatorWidth,
                    height: 30,
                    child: const DecoratedBox(
                      decoration: BoxDecoration(
                        color: AppColors.accentLight,
                        borderRadius: BorderRadius.all(Radius.circular(8)),
                      ),
                    ),
                  ),
                  Row(
                    children: [
                      for (var i = 0; i < _tabs.length; i++)
                        _TabItem(
                          icon: _tabs[i].$1,
                          activeIcon: _tabs[i].$2,
                          label: _tabs[i].$3,
                          active: i == currentIndex,
                          duration: duration,
                          onTap: () => onTap(i),
                        ),
                    ],
                  ),
                ],
              );
            },
          ),
        ),
      ),
    );
  }
}

class _TabItem extends StatelessWidget {
  final IconData icon;
  final IconData activeIcon;
  final String label;
  final bool active;
  final Duration duration;
  final VoidCallback onTap;

  const _TabItem({
    required this.icon,
    required this.activeIcon,
    required this.label,
    required this.active,
    required this.duration,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final color = active ? AppColors.accent : AppColors.text3;

    return Expanded(
      child: Semantics(
        button: true,
        selected: active,
        label: label,
        excludeSemantics: true,
        child: InkResponse(
          onTap: onTap,
          highlightShape: BoxShape.rectangle,
          containedInkWell: true,
          child: Column(
            mainAxisAlignment: MainAxisAlignment.start,
            children: [
              const SizedBox(height: 9),
              SizedBox(
                height: 30,
                child: Center(
                  child: TweenAnimationBuilder<Color?>(
                    tween: ColorTween(end: color),
                    duration: duration,
                    builder: (_, c, _) => Icon(active ? activeIcon : icon, size: 22, color: c),
                  ),
                ),
              ),
              const SizedBox(height: 3),
              AnimatedDefaultTextStyle(
                duration: duration,
                style: GoogleFonts.publicSans(
                  fontSize: 11.5,
                  fontWeight: active ? FontWeight.w600 : FontWeight.w500,
                  color: color,
                ),
                child: Text(label, maxLines: 1),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
