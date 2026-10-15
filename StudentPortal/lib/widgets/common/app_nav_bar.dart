import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../core/constants/app_colors.dart';
import '../premium/motion.dart';

/// Bottom bar: Home, Seminars and Alerts (with an unread badge). A soft
/// highlight slides behind the active tab — same as the Teacher Portal.
class AppNavBar extends StatelessWidget {
  final int currentIndex;
  final ValueChanged<int> onTap;
  final int unreadCount;

  const AppNavBar({
    super.key,
    required this.currentIndex,
    required this.onTap,
    this.unreadCount = 0,
  });

  static const _tabs = [
    (Icons.home_outlined, Icons.home_rounded, 'Home'),
    (Icons.school_outlined, Icons.school_rounded, 'Seminars'),
    (Icons.notifications_none_rounded, Icons.notifications_rounded, 'Alerts'),
  ];

  static const double _pillWidth = 64;

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
                          badge: i == 2 ? unreadCount : 0,
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
  final int badge;
  final Duration duration;
  final VoidCallback onTap;

  const _TabItem({
    required this.icon,
    required this.activeIcon,
    required this.label,
    required this.active,
    required this.badge,
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
        label: badge > 0 ? '$label, $badge unread' : label,
        child: GestureDetector(
          onTap: onTap,
          behavior: HitTestBehavior.opaque,
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Stack(
                clipBehavior: Clip.none,
                children: [
                  TweenAnimationBuilder<Color?>(
                    tween: ColorTween(end: color),
                    duration: duration,
                    builder: (_, c, _) => Icon(active ? activeIcon : icon, size: 23, color: c),
                  ),
                  if (badge > 0)
                    Positioned(
                      right: -9,
                      top: -5,
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 4.5, vertical: 1),
                        constraints: const BoxConstraints(minWidth: 17, minHeight: 17),
                        decoration: BoxDecoration(
                          color: AppColors.red,
                          borderRadius: BorderRadius.circular(9),
                          border: Border.all(color: AppColors.surface, width: 1.5),
                        ),
                        alignment: Alignment.center,
                        child: Text(
                          badge > 9 ? '9+' : '$badge',
                          style: GoogleFonts.inter(fontSize: 9.5, fontWeight: FontWeight.w700, color: Colors.white, height: 1),
                        ),
                      ),
                    ),
                ],
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
