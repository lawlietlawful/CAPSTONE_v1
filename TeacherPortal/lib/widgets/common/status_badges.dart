import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../core/constants/app_colors.dart';

/// Small rectangular tag for severity / status / escalation markers. A
/// colored dot (or an icon) sits before the word, so the meaning never rests
/// on color alone.
class AppBadge extends StatelessWidget {
  final String label;
  final Color bg;
  final Color fg;
  final IconData? icon;

  /// The dot before the label; none when null (or when [icon] is set).
  final Color? dot;

  const AppBadge({
    super.key,
    required this.label,
    required this.bg,
    required this.fg,
    this.icon,
    this.dot,
  });

  /// AI-assessed severity: Low / Medium / High / Critical, or Unassessed when
  /// the ML engine was unreachable at filing time.
  factory AppBadge.severity(String severity) {
    final s = severity.toLowerCase();
    if (s == 'high' || s == 'critical') {
      return AppBadge(
          label: severity, bg: AppColors.redBg, fg: AppColors.redText, dot: AppColors.red);
    }
    if (s == 'medium' || s == 'moderate') {
      return AppBadge(
          label: severity, bg: AppColors.amberBg, fg: AppColors.amberText, dot: AppColors.amber);
    }
    // Never render this green: an ungraded incident must not look like a
    // harmless one. It is a pending state, not a low severity.
    if (s == 'unassessed') {
      return const AppBadge(
        label: 'Pending AI',
        bg: AppColors.neutralBg,
        fg: AppColors.text2,
        icon: Icons.hourglass_top_rounded,
      );
    }
    return AppBadge(
        label: severity.isEmpty ? 'Low' : severity,
        bg: AppColors.greenBg,
        fg: AppColors.greenText,
        dot: AppColors.green);
  }

  /// Guidance workflow status: pending / reviewed / resolved.
  factory AppBadge.status(String status) {
    final s = status.toLowerCase();
    if (s == 'resolved') {
      return AppBadge(
          label: _cap(status), bg: AppColors.greenBg, fg: AppColors.greenText, dot: AppColors.green);
    }
    if (s == 'reviewed') {
      return AppBadge(label: _cap(status), bg: AppColors.neutralBg, fg: AppColors.text2);
    }
    if (s == 'in_progress' || s == 'in progress' || s == 'ongoing') {
      return AppBadge(
          label: _cap(status), bg: AppColors.accentLight, fg: AppColors.accentDark, dot: AppColors.accent);
    }
    if (s == 'cancelled' || s == 'closed') {
      return AppBadge(label: _cap(status), bg: AppColors.neutralBg, fg: AppColors.text2);
    }
    return AppBadge(
        label: _cap(status.isEmpty ? 'pending' : status),
        bg: AppColors.amberBg,
        fg: AppColors.amberText,
        dot: AppColors.amber);
  }

  /// Referral priority, derived from the ML risk level: high / moderate / low.
  /// Rendered capitalised, unlike [AppBadge.severity] whose input is already
  /// title-cased by the backend.
  factory AppBadge.priority(String priority) {
    final p = priority.toLowerCase();
    final label = _cap(p.isEmpty ? 'low' : p);
    if (p == 'high') {
      return AppBadge(label: label, bg: AppColors.redBg, fg: AppColors.redText, dot: AppColors.red);
    }
    if (p == 'moderate' || p == 'medium') {
      return AppBadge(
          label: label, bg: AppColors.amberBg, fg: AppColors.amberText, dot: AppColors.amber);
    }
    return AppBadge(label: label, bg: AppColors.greenBg, fg: AppColors.greenText, dot: AppColors.green);
  }

  /// A student's current ML risk level: high / moderate / low, or a neutral
  /// "Not assessed" when they have no risk assessment yet.
  factory AppBadge.risk(String level) {
    final l = level.toLowerCase();
    if (l == 'high') {
      return AppBadge(
          label: 'High risk', bg: AppColors.redBg, fg: AppColors.redText, dot: AppColors.red);
    }
    if (l == 'moderate' || l == 'medium') {
      return AppBadge(
          label: 'Moderate', bg: AppColors.amberBg, fg: AppColors.amberText, dot: AppColors.amber);
    }
    if (l == 'low') {
      return AppBadge(
          label: 'Low risk', bg: AppColors.greenBg, fg: AppColors.greenText, dot: AppColors.green);
    }
    return const AppBadge(
        label: 'Not assessed', bg: AppColors.neutralBg, fg: AppColors.text2, dot: AppColors.neutralDot);
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
        icon: Icons.bolt_rounded,
      );

  /// Title-cases a raw enum value, splitting on underscores so e.g.
  /// "in_progress" reads as "In Progress" rather than "In_progress".
  static String _cap(String s) {
    if (s.isEmpty) return s;
    return s
        .split('_')
        .map((w) => w.isEmpty ? w : '${w[0].toUpperCase()}${w.substring(1)}')
        .join(' ');
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 24,
      padding: const EdgeInsets.symmetric(horizontal: 8),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(6),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[
            Icon(icon, size: 13, color: fg),
            const SizedBox(width: 4),
          ] else if (dot != null) ...[
            Container(
              width: 6,
              height: 6,
              decoration: BoxDecoration(color: dot, shape: BoxShape.circle),
            ),
            const SizedBox(width: 6),
          ],
          Text(
            label,
            style: GoogleFonts.publicSans(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: fg,
              height: 1.1,
            ),
          ),
        ],
      ),
    );
  }
}
