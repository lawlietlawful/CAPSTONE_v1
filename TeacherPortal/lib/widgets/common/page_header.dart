import 'package:flutter/material.dart';

import '../../core/constants/app_colors.dart';
import '../../core/constants/app_text_styles.dart';

/// The top of every tab: a large title on the light ground, an optional line
/// below it (Home's date, a count), and actions on the right.
/// Kept in sync with the Student Portal's copy.
class PageHeader extends StatelessWidget {
  final String title;
  final String? subtitle;
  final Widget? trailing;

  const PageHeader({
    super.key,
    required this.title,
    this.subtitle,
    this.trailing,
  });

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: AppColors.background,
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 16, 16, 14),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Semantics(
                      header: true,
                      child: Text(title, maxLines: 2, overflow: TextOverflow.ellipsis, style: AppTextStyles.screenTitle),
                    ),
                    if (subtitle != null) ...[
                      const SizedBox(height: 2),
                      Text(subtitle!, style: AppTextStyles.meta.copyWith(fontSize: 13)),
                    ],
                  ],
                ),
              ),
              if (trailing != null) ...[
                const SizedBox(width: 12),
                trailing!,
              ],
            ],
          ),
        ),
      ),
    );
  }
}

/// A section label above a group of cards, with an optional text action
/// ("View all") on the right.
class SectionHeading extends StatelessWidget {
  final String title;
  final String? subtitle;
  final String? action;
  final VoidCallback? onAction;
  final int? count;

  const SectionHeading({
    super.key,
    required this.title,
    this.subtitle,
    this.action,
    this.onAction,
    this.count,
  });

  @override
  Widget build(BuildContext context) {
    return ConstrainedBox(
      constraints: const BoxConstraints(minHeight: 44),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Expanded(
            child: Padding(
              padding: const EdgeInsets.only(left: 4, bottom: 6),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.baseline,
                    textBaseline: TextBaseline.alphabetic,
                    children: [
                      Flexible(child: Text(title, style: AppTextStyles.sectionHeading)),
                      if (count != null) ...[
                        const SizedBox(width: 8),
                        Text('$count', style: AppTextStyles.meta.copyWith(fontSize: 13)),
                      ],
                    ],
                  ),
                  if (subtitle != null) ...[
                    const SizedBox(height: 2),
                    Text(subtitle!, style: AppTextStyles.meta),
                  ],
                ],
              ),
            ),
          ),
          if (action != null)
            TextButton(
              onPressed: onAction,
              style: TextButton.styleFrom(padding: const EdgeInsets.symmetric(horizontal: 8)),
              child: Text(action!, style: AppTextStyles.link.copyWith(fontSize: 13)),
            ),
        ],
      ),
    );
  }
}

/// Small upper-case label above a grouped list ("TODAY", "OCTOBER").
class GroupLabel extends StatelessWidget {
  final String text;
  final EdgeInsetsGeometry padding;
  const GroupLabel(this.text, {super.key, this.padding = const EdgeInsets.fromLTRB(4, 0, 4, 8)});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: padding,
      child: Text(text.toUpperCase(), style: AppTextStyles.overline),
    );
  }
}
