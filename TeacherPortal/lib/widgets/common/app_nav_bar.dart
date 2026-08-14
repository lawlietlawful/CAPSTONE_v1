import 'package:flutter/material.dart';
import '../../core/constants/app_colors.dart';

/// Bottom bar: Home (dashboard), Students (monitoring), Referrals, Reports,
/// and Profile. Logging an incident is a floating action, not a tab, because
/// it's an action rather than a destination.
class AppNavBar extends StatelessWidget {
  final int currentIndex;
  final ValueChanged<int> onTap;

  const AppNavBar({super.key, required this.currentIndex, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: AppColors.surface,
        border: Border(top: BorderSide(color: AppColors.border)),
      ),
      child: SafeArea(
        top: false,
        child: SizedBox(
          height: 72,
          child: Row(
            children: [
              _TabItem(
                index: 0,
                currentIndex: currentIndex,
                icon: Icons.home_rounded,
                label: 'Home',
                onTap: onTap,
              ),
              _TabItem(
                index: 1,
                currentIndex: currentIndex,
                icon: Icons.groups_rounded,
                label: 'Students',
                onTap: onTap,
              ),
              _TabItem(
                index: 2,
                currentIndex: currentIndex,
                icon: Icons.outbox_rounded,
                label: 'Referrals',
                onTap: onTap,
              ),
              _TabItem(
                index: 3,
                currentIndex: currentIndex,
                icon: Icons.receipt_long_rounded,
                label: 'Reports',
                onTap: onTap,
              ),
              _TabItem(
                index: 4,
                currentIndex: currentIndex,
                icon: Icons.person_rounded,
                label: 'Profile',
                onTap: onTap,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _TabItem extends StatelessWidget {
  final int index;
  final int currentIndex;
  final IconData icon;
  final String label;
  final ValueChanged<int> onTap;

  const _TabItem({
    required this.index,
    required this.currentIndex,
    required this.icon,
    required this.label,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final isActive = currentIndex == index;
    final color = isActive ? AppColors.accent : AppColors.text3;

    return Expanded(
      child: GestureDetector(
        onTap: () => onTap(index),
        behavior: HitTestBehavior.opaque,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 24, color: color),
            const SizedBox(height: 4),
            Text(
              label,
              style: TextStyle(
                fontSize: 10,
                fontWeight: isActive ? FontWeight.w500 : FontWeight.w400,
                color: color,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
