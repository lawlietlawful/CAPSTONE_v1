import 'package:flutter/material.dart';

import 'app_colors.dart';

/// Surfaces for the whole app: flat white cards with a hairline edge and
/// 12px corners. Depth comes from spacing and grouping, not heavy shadows.
class AppDecor {
  static const double radius = 12;
  static const double radiusSmall = 10;

  /// Barely there: just enough to lift a white card off the grey ground.
  static const List<BoxShadow> shadow = [
    BoxShadow(color: Color(0x0A0F172A), blurRadius: 2, offset: Offset(0, 1)),
  ];

  /// For a floating action (the Report a concern button).
  static const List<BoxShadow> shadowRaised = [
    BoxShadow(color: Color(0x2E0F1B2D), blurRadius: 12, offset: Offset(0, 4)),
  ];

  static BoxDecoration card({
    Color color = AppColors.surface,
    double radius = AppDecor.radius,
    List<BoxShadow> shadow = AppDecor.shadow,
    Color? borderColor,
  }) {
    return BoxDecoration(
      color: color,
      borderRadius: BorderRadius.circular(radius),
      border: Border.all(color: borderColor ?? AppColors.border),
      boxShadow: shadow,
    );
  }

  /// The navy summary card on each Home tab.
  static BoxDecoration navyCard() => BoxDecoration(
        color: AppColors.navy,
        borderRadius: BorderRadius.circular(14),
      );
}
