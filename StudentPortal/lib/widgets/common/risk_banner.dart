import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../core/constants/app_colors.dart';
import '../../core/constants/app_text_styles.dart';

class RiskBanner extends StatelessWidget {
  final String riskLevel; // "low" | "moderate" | "high"

  const RiskBanner({super.key, required this.riskLevel});

  @override
  Widget build(BuildContext context) {
    final config = _config();

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.border),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Row(
            children: [
              Icon(config.icon, color: config.iconColor, size: 16),
              const SizedBox(width: 8),
              Text('Current risk level', style: AppTextStyles.sectionTitle),
            ],
          ),
          _RiskChip(
            label: config.label,
            bg: config.chipBg,
            textColor: config.chipText,
          ),
        ],
      ),
    );
  }

  _RiskConfig _config() {
    switch (riskLevel.toLowerCase()) {
      case 'high':
        return const _RiskConfig(
          icon: Icons.dangerous_rounded,
          iconColor: AppColors.red,
          label: 'High risk',
          chipBg: AppColors.redBg,
          chipText: AppColors.redText,
        );
      case 'moderate':
        return const _RiskConfig(
          icon: Icons.warning_rounded,
          iconColor: AppColors.amber,
          label: 'Moderate',
          chipBg: AppColors.amberBg,
          chipText: AppColors.amberText,
        );
      default:
        return const _RiskConfig(
          icon: Icons.shield_rounded,
          iconColor: AppColors.green,
          label: 'Low risk',
          chipBg: AppColors.greenBg,
          chipText: AppColors.greenText,
        );
    }
  }
}

class _RiskConfig {
  final IconData icon;
  final Color iconColor;
  final String label;
  final Color chipBg;
  final Color chipText;

  const _RiskConfig({
    required this.icon,
    required this.iconColor,
    required this.label,
    required this.chipBg,
    required this.chipText,
  });
}

class _RiskChip extends StatelessWidget {
  final String label;
  final Color bg;
  final Color textColor;

  const _RiskChip({
    required this.label,
    required this.bg,
    required this.textColor,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        label,
        style: GoogleFonts.inter(
          fontSize: 11,
          fontWeight: FontWeight.w500,
          color: textColor,
        ),
      ),
    );
  }
}
