import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../core/constants/app_colors.dart';

/// Small pill badge used for severity / status / escalation markers.
class AppBadge extends StatelessWidget {
  final String label;
  final Color bg;
  final Color fg;
  final IconData? icon;

  const AppBadge({
    super.key,
    required this.label,
    required this.bg,
    required this.fg,
    this.icon,
  });

  /// AI-assessed severity: Low / Medium / High / Critical, or Unassessed when
  /// the ML engine was unreachable at filing time.
  factory AppBadge.severity(String severity) {
    final s = severity.toLowerCase();
    if (s == 'high' || s == 'critical') {
      return AppBadge(
          label: severity, bg: AppColors.redBg, fg: AppColors.redText);
    }
    if (s == 'medium' || s == 'moderate') {
      return AppBadge(
          label: severity, bg: AppColors.amberBg, fg: AppColors.amberText);
    }
    // Never render this green: an ungraded incident must not look like a
    // harmless one. It is a pending state, not a low severity.
    if (s == 'unassessed') {
      return const AppBadge(
        label: 'Pending AI',
        bg: Color(0xFFF1F5F9),
        fg: AppColors.text2,
        icon: Icons.hourglass_top_rounded,
      );
    }
    return AppBadge(
        label: severity.isEmpty ? 'Low' : severity,
        bg: AppColors.greenBg,
        fg: AppColors.greenText);
  }

  /// Guidance workflow status: pending / reviewed / resolved.
  factory AppBadge.status(String status) {
    final s = status.toLowerCase();
    if (s == 'resolved') {
      return AppBadge(
          label: _cap(status), bg: AppColors.greenBg, fg: AppColors.greenText);
    }
    if (s == 'reviewed') {
      return AppBadge(
          label: _cap(status), bg: AppColors.accentLight, fg: AppColors.accentDark);
    }
    return AppBadge(
        label: _cap(status.isEmpty ? 'pending' : status),
        bg: const Color(0xFFF1F5F9),
        fg: AppColors.text2);
  }

  /// Referral priority, derived from the ML risk level: high / moderate / low.
  /// Rendered capitalised, unlike [AppBadge.severity] whose input is already
  /// title-cased by the backend.
  factory AppBadge.priority(String priority) {
    final p = priority.toLowerCase();
    final label = _cap(p.isEmpty ? 'low' : p);
    if (p == 'high') {
      return AppBadge(label: label, bg: AppColors.redBg, fg: AppColors.redText);
    }
    if (p == 'moderate' || p == 'medium') {
      return AppBadge(
          label: label, bg: AppColors.amberBg, fg: AppColors.amberText);
    }
    return AppBadge(label: label, bg: AppColors.greenBg, fg: AppColors.greenText);
  }

  /// A student's current ML risk level: high / moderate / low, or a neutral
  /// "Not assessed" when they have no risk assessment yet.
  factory AppBadge.risk(String level) {
    final l = level.toLowerCase();
    if (l == 'high') {
      return AppBadge(
          label: 'High risk', bg: AppColors.redBg, fg: AppColors.redText, icon: Icons.warning_amber_rounded);
    }
    if (l == 'moderate' || l == 'medium') {
      return AppBadge(
          label: 'Moderate', bg: AppColors.amberBg, fg: AppColors.amberText);
    }
    if (l == 'low') {
      return AppBadge(
          label: 'Low risk', bg: AppColors.greenBg, fg: AppColors.greenText);
    }
    return const AppBadge(
        label: 'Not assessed', bg: Color(0xFFF1F5F9), fg: AppColors.text3);
  }

  /// Shown when a report auto-escalated into a guidance referral.
  factory AppBadge.escalated() => const AppBadge(
        label: 'Escalated',
        bg: AppColors.purpleBg,
        fg: AppColors.purpleText,
        icon: Icons.trending_up_rounded,
      );

  /// Marks a referral that was created automatically from a behavioral report
  /// rather than filed by hand.
  factory AppBadge.auto() => const AppBadge(
        label: 'Auto',
        bg: AppColors.purpleBg,
        fg: AppColors.purpleText,
        icon: Icons.auto_awesome_rounded,
      );

  static String _cap(String s) =>
      s.isEmpty ? s : '${s[0].toUpperCase()}${s.substring(1)}';

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(6),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[
            Icon(icon, size: 11, color: fg),
            const SizedBox(width: 3),
          ],
          Text(
            label,
            style: GoogleFonts.inter(
              fontSize: 10.5,
              fontWeight: FontWeight.w500,
              color: fg,
            ),
          ),
        ],
      ),
    );
  }
}
