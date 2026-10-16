import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../core/constants/app_decor.dart';
import '../../core/constants/app_text_styles.dart';
import '../../models/roster_student.dart';
import '../../providers/teacher_provider.dart';
import '../../widgets/common/form_widgets.dart';
import '../../widgets/common/notification_bell.dart';
import '../../widgets/common/page_header.dart';
import '../../widgets/common/status_badges.dart';
import '../../widgets/common/surfaces.dart';
import '../../widgets/premium/motion.dart';
import '../../widgets/premium/skeleton.dart';
import 'student_detail_screen.dart';

/// The teacher's advised students, each with their current ML risk level — the
/// monitoring view that surfaces the predictive analytics to the front-line
/// teacher. At-risk students float to the top so they can't be missed.
class MyStudentsScreen extends StatefulWidget {
  const MyStudentsScreen({super.key});

  @override
  State<MyStudentsScreen> createState() => _MyStudentsScreenState();
}

class _MyStudentsScreenState extends State<MyStudentsScreen> {
  final _searchController = TextEditingController();
  String _search = '';

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) context.read<TeacherProvider>().loadRoster();
    });
  }

  void _openDetail(RosterStudent s) {
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => StudentDetailScreen(studentId: s.id)),
    );
  }

  /// High → moderate → the rest, each block kept alphabetical (the API already
  /// sorts by last name). Puts who-needs-attention first.
  List<RosterStudent> _sorted(List<RosterStudent> roster) {
    int rank(String level) => switch (level) {
      'high' => 0,
      'moderate' => 1,
      'low' => 2,
      _ => 3,
    };
    final list = [...roster];
    list.sort((a, b) => rank(a.riskLevel).compareTo(rank(b.riskLevel)));
    return list;
  }

  /// Every word of the search must match the name or student ID ("cruz ana",
  /// "2026-0"), and the student must fit the risk filter.
  bool _matches(RosterStudent s, String? risk) {
    final riskOk = switch (risk) {
      null => true,
      'at_risk' => s.isAtRisk,
      final level => s.riskLevel == level,
    };
    if (!riskOk) return false;
    final haystack = '${s.name} ${s.schoolId}'.toLowerCase();
    return _search
        .toLowerCase()
        .split(RegExp(r'[\s,]+'))
        .where((w) => w.isNotEmpty)
        .every(haystack.contains);
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<TeacherProvider>();
    final total = provider.roster.length;
    final advisory = provider.dashboard.advisory;

    final String subtitle;
    if (total == 0) {
      subtitle = 'Students in your advisory';
    } else {
      final count = '$total student${total == 1 ? '' : 's'}';
      subtitle = advisory.isEmpty ? '$count in your advisory' : '$count in ${_joinClasses(advisory)}';
    }

    return Column(
      children: [
        PageHeader(
          title: 'My Students',
          subtitle: subtitle,
          trailing: const HeaderActions(),
        ),
        Expanded(child: _buildBody(provider)),
      ],
    );
  }

  /// "BSIT 2-A", "BSIT 2-A and BSIT 2-B", "BSIT 2-A, 2-B and 2-C".
  static String _joinClasses(List<String> classes) {
    if (classes.length == 1) return classes.first;
    if (classes.length > 3) return '${classes.length} classes';
    return '${classes.sublist(0, classes.length - 1).join(', ')} and ${classes.last}';
  }

  Widget _buildBody(TeacherProvider provider) {
    if (provider.loadingRoster && provider.roster.isEmpty) {
      return const SkeletonList(leading: true, padding: EdgeInsets.fromLTRB(16, 4, 16, 16));
    }

    if (provider.rosterError != null && provider.roster.isEmpty) {
      return CenteredMessage(
        icon: Icons.wifi_off_rounded,
        title: 'Could not load students',
        message: provider.rosterError!,
        actionLabel: 'Retry',
        onAction: () => provider.loadRoster(),
      );
    }

    if (provider.roster.isEmpty) {
      return RefreshIndicator(
        onRefresh: () => provider.loadRoster(),
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: const [
            SizedBox(height: 120),
            CenteredMessage(
              icon: Icons.groups_outlined,
              title: 'No students yet',
              message:
                  'Students in the courses/sections you advise will appear here.',
            ),
          ],
        ),
      );
    }

    final risk = provider.rosterRiskFilter;
    final students = _sorted(provider.roster).where((s) => _matches(s, risk)).toList();
    final filtering = _search.isNotEmpty || risk != null;

    return RefreshIndicator(
      onRefresh: () => provider.loadRoster(),
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 2, 16, 96),
        children: [
          _SearchField(
            controller: _searchController,
            onChanged: (v) => setState(() => _search = v.trim()),
          ),
          const SizedBox(height: 12),
          FadeSlideIn(child: _RiskMix(summary: provider.riskSummary, total: provider.roster.length)),
          const SizedBox(height: 14),
          _RiskChips(
            roster: provider.roster,
            selected: risk,
            onRisk: provider.applyRosterRiskFilter,
          ),
          const SizedBox(height: 14),
          if (students.isEmpty && filtering)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 36),
              child: CenteredMessage(
                icon: Icons.search_off_rounded,
                title: 'No students match',
                message: 'Try another name or ID, or a different filter.',
                actionLabel: 'Clear filters',
                onAction: () {
                  _searchController.clear();
                  setState(() => _search = '');
                  provider.applyRosterRiskFilter(null);
                },
              ),
            )
          else ...[
            if (provider.atRiskCount > 0 && risk == null)
              Padding(
                padding: const EdgeInsets.fromLTRB(4, 0, 4, 8),
                child: Text('Students who need attention are listed first', style: AppTextStyles.meta),
              ),
            FadeSlideIn(
              index: 2,
              child: CardGroup(
                children: [
                  for (final s in students)
                    _StudentRow(key: ValueKey(s.id), student: s, onTap: () => _openDetail(s)),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}

/// Risk overview: one bar split by level (it grows into place) and the
/// counts underneath.
class _RiskMix extends StatelessWidget {
  final Map<String, int> summary;
  final int total;
  const _RiskMix({required this.summary, required this.total});

  static const _levels = [
    ('high', 'High', AppColors.red),
    ('moderate', 'Moderate', AppColors.amber),
    ('low', 'Low', AppColors.green),
    ('not_assessed', 'Not assessed', AppColors.neutralDot),
  ];

  @override
  Widget build(BuildContext context) {
    final atRisk = (summary['high'] ?? 0) + (summary['moderate'] ?? 0);
    final described = [
      for (final (key, label, _) in _levels)
        if ((summary[key] ?? 0) > 0) '${summary[key]} $label',
    ].join(', ');

    return Container(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
      decoration: AppDecor.card(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Text('Risk overview', style: AppTextStyles.sectionTitle),
              const Spacer(),
              Text(
                atRisk == 0 ? 'No one flagged' : '$atRisk need attention',
                style: GoogleFonts.publicSans(
                  fontSize: 12.5,
                  fontWeight: FontWeight.w600,
                  color: atRisk == 0 ? AppColors.greenText : AppColors.redText,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Semantics(
            label: 'Risk mix: $described',
            child: TweenAnimationBuilder<double>(
              tween: Tween(begin: Motion.reduced(context) ? 1 : 0, end: 1),
              duration: Motion.slow,
              curve: Motion.enter,
              builder: (context, t, _) => ClipRRect(
                borderRadius: BorderRadius.circular(4),
                child: SizedBox(
                  height: 8,
                  child: Row(
                    children: [
                      for (final (key, _, color) in _levels)
                        if ((summary[key] ?? 0) > 0)
                          Expanded(
                            flex: ((summary[key] ?? 0) * 1000 * t).round().clamp(1, 1 << 30),
                            child: Container(
                              margin: const EdgeInsets.only(right: 2),
                              color: color,
                            ),
                          ),
                      Expanded(
                        flex: (total * 1000 * (1 - t)).round().clamp(0, 1 << 30),
                        child: const SizedBox(),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(height: 10),
          ExcludeSemantics(
            child: Wrap(
              spacing: 14,
              runSpacing: 6,
              children: [
                for (final (key, label, color) in _levels)
                  if ((summary[key] ?? 0) > 0)
                    Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Container(
                          width: 8,
                          height: 8,
                          decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(2)),
                        ),
                        const SizedBox(width: 5),
                        Text('$label ${summary[key]}', style: AppTextStyles.meta.copyWith(color: AppColors.text2)),
                      ],
                    ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _StudentRow extends StatelessWidget {
  final RosterStudent student;
  final VoidCallback onTap;

  const _StudentRow({super.key, required this.student, required this.onTap});

  /// "2 reports · 1 open referral", or "No reports or referrals".
  String get _cases {
    final parts = <String>[
      if (student.reportsCount > 0) '${student.reportsCount} report${student.reportsCount == 1 ? '' : 's'}',
      if (student.openReferrals > 0)
        '${student.openReferrals} open referral${student.openReferrals == 1 ? '' : 's'}',
    ];
    return parts.isEmpty ? 'No reports or referrals' : parts.join(' · ');
  }

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 12, 8, 12),
        child: Row(
          children: [
            Hero(
              tag: 'student-avatar-${student.id}',
              child: InitialsAvatar(initials: InitialsAvatar.of(student.name), size: 40),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    student.name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: AppTextStyles.sectionTitle,
                  ),
                  const SizedBox(height: 1),
                  if (student.schoolId.isNotEmpty)
                    Text(student.schoolId, maxLines: 1, overflow: TextOverflow.ellipsis, style: AppTextStyles.meta),
                  Text(_cases, maxLines: 1, overflow: TextOverflow.ellipsis, style: AppTextStyles.meta),
                ],
              ),
            ),
            const SizedBox(width: 8),
            AppBadge.risk(student.riskLevel),
            const Icon(Icons.chevron_right_rounded, size: 20, color: AppColors.iconMuted),
          ],
        ),
      ),
    );
  }
}

class _SearchField extends StatelessWidget {
  final TextEditingController controller;
  final ValueChanged<String> onChanged;

  const _SearchField({required this.controller, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<TextEditingValue>(
      valueListenable: controller,
      builder: (context, value, _) => TextField(
        key: const Key('roster-search'),
        controller: controller,
        onChanged: onChanged,
        textInputAction: TextInputAction.search,
        style: GoogleFonts.publicSans(fontSize: 15, color: AppColors.text1),
        decoration: InputDecoration(
          hintText: 'Search by name or student ID',
          contentPadding: const EdgeInsets.symmetric(vertical: 13),
          prefixIcon: const Icon(Icons.search_rounded, size: 20, color: AppColors.text3),
          suffixIcon: value.text.isEmpty
              ? null
              : IconButton(
                  tooltip: 'Clear search',
                  icon: const Icon(Icons.close_rounded, size: 18, color: AppColors.text3),
                  onPressed: () {
                    controller.clear();
                    onChanged('');
                  },
                ),
        ),
      ),
    );
  }
}

/// Risk filter chips, each with its count.
class _RiskChips extends StatelessWidget {
  final List<RosterStudent> roster;
  final String? selected;
  final ValueChanged<String?> onRisk;

  const _RiskChips({required this.roster, required this.selected, required this.onRisk});

  @override
  Widget build(BuildContext context) {
    int count(String? key) => switch (key) {
      null => roster.length,
      'at_risk' => roster.where((s) => s.isAtRisk).length,
      final level => roster.where((s) => s.riskLevel == level).length,
    };
    const chips = [
      (null, 'All'),
      ('at_risk', 'At risk'),
      ('high', 'High'),
      ('moderate', 'Moderate'),
      ('low', 'Low'),
      ('not_assessed', 'Not assessed'),
    ];

    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      clipBehavior: Clip.none,
      child: Row(
        children: [
          for (final (key, label) in chips)
            if (key == null || count(key) > 0 || selected == key)
              Padding(
                padding: const EdgeInsets.only(right: 8),
                child: ChoiceChipButton(
                  key: Key('risk-chip-${key ?? 'all'}'),
                  label: '$label ${count(key)}',
                  selected: selected == key,
                  onTap: () => onRisk(key),
                ),
              ),
        ],
      ),
    );
  }
}
