import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';

import '../constants/app_colors.dart';

/// The Material theme both portals share, so stock widgets (app bars, text
/// fields, buttons, dialogs, sheets, snack bars) match the custom ones
/// without per-screen styling. Kept in sync with the other portal's copy.
class AppTheme {
  static ThemeData light() {
    final base = ThemeData.light(useMaterial3: true);
    final text = GoogleFonts.publicSansTextTheme(base.textTheme).apply(
      bodyColor: AppColors.text1,
      displayColor: AppColors.text1,
    );

    final controlShape = RoundedRectangleBorder(borderRadius: BorderRadius.circular(10));
    OutlineInputBorder inputBorder(Color color, [double width = 1]) => OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: BorderSide(color: color, width: width),
        );

    return base.copyWith(
      colorScheme: ColorScheme.fromSeed(
        seedColor: AppColors.accent,
        primary: AppColors.accent,
        onPrimary: Colors.white,
        surface: AppColors.surface,
        onSurface: AppColors.text1,
        error: AppColors.redText,
      ),
      textTheme: text,
      scaffoldBackgroundColor: AppColors.background,
      // One calm transition everywhere: the new page fades and slides
      // forward over the old one.
      pageTransitionsTheme: const PageTransitionsTheme(builders: {
        TargetPlatform.android: FadeForwardsPageTransitionsBuilder(),
        TargetPlatform.iOS: FadeForwardsPageTransitionsBuilder(),
        TargetPlatform.windows: FadeForwardsPageTransitionsBuilder(),
        TargetPlatform.macOS: FadeForwardsPageTransitionsBuilder(),
        TargetPlatform.linux: FadeForwardsPageTransitionsBuilder(),
        TargetPlatform.fuchsia: FadeForwardsPageTransitionsBuilder(),
      }),
      appBarTheme: AppBarTheme(
        backgroundColor: AppColors.background,
        foregroundColor: AppColors.text1,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        titleSpacing: 4,
        titleTextStyle: GoogleFonts.publicSans(fontSize: 17, fontWeight: FontWeight.w600, color: AppColors.text1),
        iconTheme: const IconThemeData(color: AppColors.text1, size: 22),
        systemOverlayStyle: SystemUiOverlayStyle.dark,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: AppColors.surface,
        hoverColor: Colors.transparent,
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
        hintStyle: GoogleFonts.publicSans(fontSize: 15, color: AppColors.text3),
        border: inputBorder(AppColors.borderStrong),
        enabledBorder: inputBorder(AppColors.borderStrong),
        focusedBorder: inputBorder(AppColors.accent, 1.5),
        errorBorder: inputBorder(AppColors.redText),
        focusedErrorBorder: inputBorder(AppColors.redText, 1.5),
      ),
      textSelectionTheme: const TextSelectionThemeData(cursorColor: AppColors.accent),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: AppColors.accent,
          foregroundColor: Colors.white,
          minimumSize: const Size(64, 48),
          shape: controlShape,
          textStyle: GoogleFonts.publicSans(fontSize: 15, fontWeight: FontWeight.w600),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: AppColors.accent,
          foregroundColor: Colors.white,
          elevation: 0,
          minimumSize: const Size(64, 48),
          shape: controlShape,
          textStyle: GoogleFonts.publicSans(fontSize: 15, fontWeight: FontWeight.w600),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: AppColors.text1,
          backgroundColor: AppColors.surface,
          side: const BorderSide(color: AppColors.borderStrong),
          minimumSize: const Size(64, 44),
          shape: controlShape,
          textStyle: GoogleFonts.publicSans(fontSize: 14, fontWeight: FontWeight.w600),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: AppColors.accent,
          minimumSize: const Size(44, 44),
          shape: controlShape,
          textStyle: GoogleFonts.publicSans(fontSize: 14, fontWeight: FontWeight.w600),
        ),
      ),
      switchTheme: SwitchThemeData(
        thumbColor: const WidgetStatePropertyAll(Colors.white),
        trackColor: WidgetStateProperty.resolveWith(
          (s) => s.contains(WidgetState.selected) ? AppColors.accent : AppColors.neutralDot,
        ),
        trackOutlineColor: const WidgetStatePropertyAll(Colors.transparent),
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: AppColors.surface,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        titleTextStyle: GoogleFonts.publicSans(fontSize: 17, fontWeight: FontWeight.w700, color: AppColors.text1),
        contentTextStyle: GoogleFonts.publicSans(fontSize: 14, height: 1.5, color: AppColors.text2),
      ),
      bottomSheetTheme: const BottomSheetThemeData(
        backgroundColor: AppColors.surface,
        surfaceTintColor: Colors.transparent,
        showDragHandle: false,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      ),
      snackBarTheme: SnackBarThemeData(
        backgroundColor: AppColors.navy,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        contentTextStyle: GoogleFonts.publicSans(fontSize: 14, color: Colors.white),
        actionTextColor: AppColors.navyAccent,
      ),
      dividerTheme: const DividerThemeData(color: AppColors.divider, thickness: 1, space: 1),
      progressIndicatorTheme: const ProgressIndicatorThemeData(color: AppColors.accent),
      datePickerTheme: const DatePickerThemeData(
        backgroundColor: AppColors.surface,
        surfaceTintColor: Colors.transparent,
      ),
    );
  }
}
