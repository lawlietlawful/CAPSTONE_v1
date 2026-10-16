import 'package:flutter/material.dart';

/// Shared visual language with the Teacher Portal so both apps read as one
/// system. Keep in sync with TeacherPortal/lib/core/constants/app_colors.dart.
///
/// Every text color here passes 4.5:1 on white (and on its own tint, for the
/// semantic pairs), so labels stay readable in sunlight on a phone.
class AppColors {
  // Brand navy — the summary cards, sign-in band and avatars.
  static const navy = Color(0xFF0F1B2D);
  static const navy2 = Color(0xFF1B2B44);
  static const navy3 = Color(0xFF243D5C);

  // Primary blue — buttons, links, the active tab.
  static const accent = Color(0xFF1F4FD1);
  static const accentLight = Color(0xFFEEF3FE);
  static const accentDark = Color(0xFF1A44B8);

  // Surfaces
  static const surface = Color(0xFFFFFFFF);
  static const background = Color(0xFFF4F6F9);
  static const border = Color(0xFFE2E7EE);

  /// Input and button outlines: a step darker than a card's edge.
  static const borderStrong = Color(0xFFD3DAE4);

  /// Hairline between rows inside one card.
  static const divider = Color(0xFFEDF0F4);

  /// The track behind a segmented control.
  static const segmentTrack = Color(0xFFE8ECF2);

  // Text
  static const text1 = Color(0xFF0E1726);
  static const text2 = Color(0xFF46546A);
  static const text3 = Color(0xFF6B778A);

  /// Chevrons and other quiet glyphs that carry no meaning on their own.
  static const iconMuted = Color(0xFF9AA5B5);

  // Semantic – red
  static const red = Color(0xFFC8321F);
  static const redBg = Color(0xFFFEF0EE);
  static const redText = Color(0xFFA3200F);

  // Semantic – amber
  static const amber = Color(0xFFE8930C);
  static const amberBg = Color(0xFFFEF6E7);
  static const amberText = Color(0xFF8A4E06);

  // Semantic – green
  static const green = Color(0xFF1D9A6C);
  static const greenBg = Color(0xFFEAF6F0);
  static const greenText = Color(0xFF0B6B4B);

  // Semantic – purple
  static const purple = Color(0xFF6E4FD0);
  static const purpleBg = Color(0xFFF3F0FC);
  static const purpleText = Color(0xFF5235B0);

  // Neutral tag (not assessed, reviewed, cancelled)
  static const neutralBg = Color(0xFFEEF1F5);
  static const neutralDot = Color(0xFFC3CAD5);

  // Navy text helpers (for use on dark navy backgrounds)
  static const navyText = Color(0xFFFFFFFF);
  static const navySubText = Color(0xFFAEB9CB);
  static const navyMuted = Color(0x40FFFFFF); // 25% opacity
  static const navyBorder = Color(0x1FFFFFFF); // 12% opacity
  static const navyCard = Color(0xFF1B2B44);
  static const navyAccent = Color(0xFF9DB8F5);
  static const navyInitial = Color(0xFFFFFFFF);

  /// The warning glyph on the navy summary card.
  static const navyWarning = Color(0xFFF5B54A);
}
