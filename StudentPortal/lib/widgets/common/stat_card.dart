import 'package:flutter/material.dart';
import '../../core/constants/app_colors.dart';
import '../../core/constants/app_text_styles.dart';

class StatCard extends StatelessWidget {
  final Color iconBg;
  final Color iconColor;
  final IconData iconData;
  final String value;
  final String label;
  final Color? valueColor;

  const StatCard({
    super.key,
    required this.iconBg,
    required this.iconColor,
    required this.iconData,
    required this.value,
    required this.label,
    this.valueColor,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Icon chip
          Container(
            width: 34,
            height: 34,
            decoration: BoxDecoration(
              color: iconBg,
              borderRadius: BorderRadius.circular(9),
            ),
            child: Icon(iconData, color: iconColor, size: 16),
          ),
          const SizedBox(height: 10),
          // Stat value
          Text(
            value,
            style: AppTextStyles.statValue.copyWith(
              color: valueColor ?? AppColors.text1,
            ),
          ),
          const SizedBox(height: 2),
          // Label
          Text(label, style: AppTextStyles.label),
        ],
      ),
    );
  }
}
