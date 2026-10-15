import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../core/constants/app_colors.dart';
import '../premium/motion.dart';

/// Bottom bar: Home (dashboard), Students (monitoring), Referrals, Reports,
/// and Profile. Logging an incident is a floating action, not a tab, because
/// it's an action rather than a destination. A soft highlight slides behind
/// the active tab.
class AppNavBar extends StatelessWidget {
  final int currentIndex;
  final ValueChanged<int> onTap;

  const AppNavBar({super.key, required this.currentIndex, required this.onTap});

  static const _tabs = [
    (Icons.home_outlined, Icons.home_rounded, 'Home'),
    (Icons.groups_outlined, Icons.groups_rounded, 'Students'),
    (Icons.outbox_outlined, Icons.outbox_rounded, 'Referrals'),
    (Icons.receipt_long_outlined, Icons.receipt_long_rounded, 'Reports'),
    (Icons.person_outline_rounded, Icons.person_rounded, 'Profile'),
  ];

  static const double _pillWidth = 58;

  @override
  Widget build(BuildContext context) {
    final duration = Motion.reduced(context) ? Duration.zero : Motion.medium;

    return Container(
      decoration: const BoxDecoration(
        color: AppColors.surface,
        boxShadow: [BoxShadow(color: Color(0x120F172A), blurRadius: 18, offset: Offset(0, -4))],
      ),
      child: SafeArea(
        top: false,
        child: SizedBox(
          height: 72,
          child: LayoutBuilder(
            builder: (context, constraints) {
              final tabWidth = constraints.maxWidth / _tabs.length;
              return Stack(
                children: [
                  AnimatedPositioned(
                    duration: duration,
                    curve: Curves.easeOutBack,
                    left: tabWidth * currentIndex + (tabWidth - _pillWidth) / 2,
                    top: 11,
                    width: _pillWidth,
                    height: 30,
                    child: Container(
                      decoration: BoxDecoration(
                        color: AppColors.accentLight,
                        borderRadius: BorderRadius.circular(15),
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
    final color = active ? AppColors.accentDark : AppColors.text3;

    return Expanded(
      child: Semantics(
        button: true,
        selected: active,
        label: label,
        child: GestureDetector(
          onTap: onTap,
          behavior: HitTestBehavior.opaque,
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              TweenAnimationBuilder<Color?>(
                tween: ColorTween(end: color),
                duration: duration,
                builder: (_, c, _) => Icon(active ? activeIcon : icon, size: 23, color: c),
              ),
              const SizedBox(height: 7),
              AnimatedDefaultTextStyle(
                duration: duration,
                style: GoogleFonts.inter(
                  fontSize: 10.5,
                  fontWeight: active ? FontWeight.w600 : FontWeight.w500,
                  color: color,
                ),
                child: Text(label),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
