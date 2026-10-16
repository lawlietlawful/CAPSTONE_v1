import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'app_colors.dart';

/// The type scale, in Public Sans. Kept in sync with
/// StudentPortal/lib/core/constants/app_text_styles.dart.
class AppTextStyles {
  // ── Light background styles ──────────────────────────────

  /// A tab's title: "My Students", "Good morning, Maria".
  static TextStyle get screenTitle => GoogleFonts.publicSans(
      fontSize: 23, height: 30 / 23, fontWeight: FontWeight.w700, letterSpacing: -0.2, color: AppColors.text1);

  /// A detail screen's subject (a student's or seminar's name), sheet titles.
  static TextStyle get pageTitle => GoogleFonts.publicSans(
      fontSize: 20, height: 26 / 20, fontWeight: FontWeight.w700, color: AppColors.text1);

  /// A section above a group of cards: "Recent referrals".
  static TextStyle get sectionHeading => GoogleFonts.publicSans(
      fontSize: 16, height: 22 / 16, fontWeight: FontWeight.w600, color: AppColors.text1);

  /// A row's or card's title, and form labels.
  static TextStyle get sectionTitle => GoogleFonts.publicSans(
      fontSize: 14, height: 20 / 14, fontWeight: FontWeight.w600, color: AppColors.text1);

  /// Small upper-case group label: "TODAY", "ACCOUNT".
  static TextStyle get overline => GoogleFonts.publicSans(
      fontSize: 12, fontWeight: FontWeight.w700, letterSpacing: 0.7, color: AppColors.text3);

  static TextStyle get body => GoogleFonts.publicSans(
      fontSize: 14, height: 21 / 14, fontWeight: FontWeight.w400, color: AppColors.text1);

  /// Longer secondary copy (a reason, a description) in a card.
  static TextStyle get bodySecondary => GoogleFonts.publicSans(
      fontSize: 13.5, height: 20 / 13.5, fontWeight: FontWeight.w400, color: AppColors.text2);

  static TextStyle get meta => GoogleFonts.publicSans(
      fontSize: 12.5, height: 17 / 12.5, fontWeight: FontWeight.w400, color: AppColors.text3);

  static TextStyle get badge => GoogleFonts.publicSans(
      fontSize: 12, fontWeight: FontWeight.w600, color: AppColors.text1);

  static TextStyle get statValue => GoogleFonts.publicSans(
      fontSize: 28,
      height: 34 / 28,
      fontWeight: FontWeight.w700,
      color: AppColors.text1,
      fontFeatures: const [FontFeature.tabularFigures()]);

  static TextStyle get label => GoogleFonts.publicSans(
      fontSize: 12.5, fontWeight: FontWeight.w400, color: AppColors.text3);

  /// A text button or inline link: "View all", "Mark all read".
  static TextStyle get link => GoogleFonts.publicSans(
      fontSize: 13.5, fontWeight: FontWeight.w600, color: AppColors.accent);

  // ── Navy (dark) background styles ───────────────────────

  static TextStyle get heroGreeting => GoogleFonts.publicSans(
      fontSize: 19, fontWeight: FontWeight.w600, color: AppColors.navyText);

  static TextStyle get navyPageTitle => GoogleFonts.publicSans(
      fontSize: 17, fontWeight: FontWeight.w600, color: AppColors.navyText);

  static TextStyle get navySubtitle => GoogleFonts.publicSans(
      fontSize: 12.5, fontWeight: FontWeight.w400, color: AppColors.navySubText);

  static TextStyle get navyMeta => GoogleFonts.publicSans(
      fontSize: 12, fontWeight: FontWeight.w400, color: AppColors.navySubText);

  static TextStyle get navyLabel => GoogleFonts.publicSans(
      fontSize: 12.5, fontWeight: FontWeight.w400, color: AppColors.navySubText);

  // ── Tab bar ──────────────────────────────────────────────

  static TextStyle get tabLabel => GoogleFonts.publicSans(
      fontSize: 11.5, fontWeight: FontWeight.w500, color: AppColors.text3);

  static TextStyle get tabLabelActive => GoogleFonts.publicSans(
      fontSize: 11.5, fontWeight: FontWeight.w600, color: AppColors.accent);
}
