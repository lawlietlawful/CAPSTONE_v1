import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'app_colors.dart';

class AppTextStyles {
  // ── Light background styles ──────────────────────────────

  static TextStyle get pageTitle => GoogleFonts.inter(
        fontSize: 17, fontWeight: FontWeight.w500, color: AppColors.text1);

  static TextStyle get sectionTitle => GoogleFonts.inter(
        fontSize: 13, fontWeight: FontWeight.w500, color: AppColors.text1);

  static TextStyle get body => GoogleFonts.inter(
        fontSize: 12.5, fontWeight: FontWeight.w400, color: AppColors.text1);

  static TextStyle get meta => GoogleFonts.inter(
        fontSize: 11, fontWeight: FontWeight.w400, color: AppColors.text3);

  static TextStyle get badge => GoogleFonts.inter(
        fontSize: 10.5, fontWeight: FontWeight.w500, color: AppColors.text1);

  static TextStyle get statValue => GoogleFonts.inter(
        fontSize: 22, fontWeight: FontWeight.w500, color: AppColors.text1);

  static TextStyle get label => GoogleFonts.inter(
        fontSize: 11, fontWeight: FontWeight.w400, color: AppColors.text3);

  // ── Navy (dark) background styles ───────────────────────

  static TextStyle get heroGreeting => GoogleFonts.inter(
        fontSize: 19, fontWeight: FontWeight.w500, color: AppColors.navyText);

  static TextStyle get navyPageTitle => GoogleFonts.inter(
        fontSize: 17, fontWeight: FontWeight.w500, color: AppColors.navyText);

  static TextStyle get navySubtitle => GoogleFonts.inter(
        fontSize: 12, fontWeight: FontWeight.w400, color: AppColors.navySubText);

  static TextStyle get navyMeta => GoogleFonts.inter(
        fontSize: 11, fontWeight: FontWeight.w400, color: AppColors.navySubText);

  static TextStyle get navyLabel => GoogleFonts.inter(
        fontSize: 12, fontWeight: FontWeight.w400,
        color: const Color(0x8CFFFFFF)); // 55% opacity

  // ── Tab bar ──────────────────────────────────────────────

  static TextStyle get tabLabel => GoogleFonts.inter(
        fontSize: 10, fontWeight: FontWeight.w400, color: AppColors.text3);

  static TextStyle get tabLabelActive => GoogleFonts.inter(
        fontSize: 10, fontWeight: FontWeight.w500, color: AppColors.accent);
}
