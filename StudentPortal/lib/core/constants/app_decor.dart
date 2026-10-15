import 'package:flutter/material.dart';

import 'app_colors.dart';

/// Surfaces for the whole app: one card look (soft layered shadow, hairline
/// edge, 16px corners) instead of flat outlined boxes, so every screen has
/// the same depth.
class AppDecor {
  static const double radius = 16;
  static const double radiusSmall = 12;

  /// A quiet two-layer shadow: a tight one for the edge, a wide one for lift.
  static const List<BoxShadow> shadow = [
    BoxShadow(color: Color(0x0A0F172A), blurRadius: 2, offset: Offset(0, 1)),
    BoxShadow(color: Color(0x0F0F172A), blurRadius: 16, offset: Offset(0, 6)),
  ];

  /// For something that sits above the rest (the overview card on Home).
  static const List<BoxShadow> shadowRaised = [
    BoxShadow(color: Color(0x0F0F172A), blurRadius: 3, offset: Offset(0, 1)),
    BoxShadow(color: Color(0x1A0F172A), blurRadius: 28, offset: Offset(0, 12)),
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
      border: Border.all(color: borderColor ?? const Color(0xFFEDF1F6)),
      boxShadow: shadow,
    );
  }
}
