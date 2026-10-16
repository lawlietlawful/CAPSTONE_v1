import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../core/constants/app_text_styles.dart';
import '../../models/referral.dart';
import '../../providers/teacher_provider.dart';
import '../../widgets/common/form_widgets.dart';
import '../../widgets/common/list_filter_sheet.dart';
import '../../widgets/common/notification_bell.dart';
import '../../widgets/common/page_header.dart';
import '../../widgets/common/search_filter_bar.dart';
import '../../widgets/common/status_badges.dart';
import '../../widgets/common/surfaces.dart';
import '../../widgets/premium/motion.dart';
import '../../widgets/premium/skeleton.dart';
import 'referral_detail_screen.dart';

/// Read-only history of the referrals this teacher has filed, grouped by
/// month, plus a shortcut to file a new one. Mirrors
/// resources/views/teacher/referrals/index.blade.php.
class ReferralsScreen extends StatefulWidget {
  final VoidCallback onFileReferral;

  const ReferralsScreen({super.key, required this.onFileReferral});

  @override
  State<ReferralsScreen> createState() => _ReferralsScreenState();
}

class _ReferralsScreenState extends State<ReferralsScreen> {
  final _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) context.read<TeacherProvider>().loadReferrals();
    });
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  /// Fetch the next page once the user scrolls within 400px of the bottom.
  void _onScroll() {
    if (_scrollController.position.pixels >=
        _scrollController.position.maxScrollExtent - 400) {
      context.read<TeacherProvider>().loadMoreReferrals();
    }
  }

  void _openFilterSheet(TeacherProvider provider) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (_) => ListFilterSheet(
        typeLabel: 'Referral Type',
        typeOptions: provider.referralTypes,
        selectedType: provider.referralType,
        secondaryLabel: 'Priority',
        secondaryOptions: const [
          (label: 'High', value: 'high'),
          (label: 'Moderate', value: 'moderate'),
          (label: 'Low', value: 'low'),
        ],
        selectedSecondary: provider.referralPriority,
        dateFrom: provider.referralDateFrom,
        dateTo: provider.referralDateTo,
        onApply: (type, priority, from, to) => provider.applyReferralFilter(
          search: provider.referralSearch,
          status: provider.referralStatus,
          referralType: type,
          priority: priority,
          dateFrom: from,
          dateTo: to,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<TeacherProvider>();

    final count = provider.totalReferrals;
    final pending = provider.pendingReferralCount;

    return Column(
      children: [
        PageHeader(
          title: 'My Referrals',
          subtitle: count == 0
              ? 'Students you sent to Guidance'
              : '$count filed · $pending pending',
          trailing: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const HeaderActions(),
              const SizedBox(width: 8),
              Tooltip(
                message: 'File a referral',
                child: Material(
                  color: AppColors.navy,
                  borderRadius: BorderRadius.circular(10),
                  child: InkWell(
                    key: const Key('new-referral'),
                    onTap: widget.onFileReferral,
                    borderRadius: BorderRadius.circular(10),
                    child: const SizedBox(
                      width: 44,
                      height: 44,
                      child: Icon(Icons.add_rounded, size: 24, color: Colors.white),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
        if (provider.referrals.isNotEmpty || provider.referralFilterActive)
          SearchFilterBar(
            hint: 'Search by student name',
            initialSearch: provider.referralSearch,
            selectedStatus: provider.referralStatus,
            statuses: const [
              (label: 'All', value: null),
              (label: 'Pending', value: 'pending'),
              (label: 'In progress', value: 'in_progress'),
              (label: 'Resolved', value: 'resolved'),
            ],
            onSearchChanged: (s) => provider.applyReferralFilter(
              search: s,
              status: provider.referralStatus,
              referralType: provider.referralType,
              priority: provider.referralPriority,
              dateFrom: provider.referralDateFrom,
              dateTo: provider.referralDateTo,
            ),
            onStatusChanged: (st) => provider.applyReferralFilter(
              search: provider.referralSearch,
              status: st,
              referralType: provider.referralType,
              priority: provider.referralPriority,
              dateFrom: provider.referralDateFrom,
              dateTo: provider.referralDateTo,
            ),
            onMoreFilters: () => _openFilterSheet(provider),
            moreFiltersActive:
                provider.referralType != null ||
                provider.referralPriority != null ||
                provider.referralDateFrom != null ||
                provider.referralDateTo != null,
          ),
        Expanded(child: _buildBody(provider)),
      ],
    );
  }

  Widget _buildBody(TeacherProvider provider) {
    if (provider.loadingReferrals && provider.referrals.isEmpty) {
      return const SkeletonList();
    }

    if (provider.referralsError != null && provider.referrals.isEmpty) {
      return CenteredMessage(
        icon: Icons.wifi_off_rounded,
        title: 'Could not load referrals',
        message: provider.referralsError!,
        actionLabel: 'Retry',
        onAction: () => provider.loadReferrals(),
      );
    }

    if (provider.referrals.isEmpty) {
      final filtered = provider.referralFilterActive;
      return RefreshIndicator(
        onRefresh: () => provider.loadReferrals(),
        child: ListView(
          // Needs a scrollable child so pull-to-refresh works when empty.
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            const SizedBox(height: 120),
            CenteredMessage(
              icon: filtered ? Icons.search_off_rounded : Icons.outbox_outlined,
              title: filtered ? 'No matching referrals' : 'No referrals filed',
              message: filtered
                  ? 'Try a different name or status filter.'
                  : 'Students you refer to Guidance will appear here. Tap + '
                        'to file one.',
              actionLabel: filtered ? 'Clear filters' : null,
              onAction: filtered
                  ? () => provider.applyReferralFilter(search: '', status: null)
                  : null,
            ),
          ],
        ),
      );
    }

    final groups = _groupByMonth(provider.referrals);

    return RefreshIndicator(
      onRefresh: () => provider.loadReferrals(),
      child: ListView(
        controller: _scrollController,
        physics: const AlwaysScrollableScrollPhysics(),
        // Extra bottom padding so the last card can scroll clear of the FAB.
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 96),
        children: [
          for (var g = 0; g < groups.length; g++)
            FadeSlideIn(
              key: ValueKey(groups[g].label),
              index: g,
              child: Padding(
                padding: EdgeInsets.only(top: g == 0 ? 0 : 18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    GroupLabel(groups[g].label),
                    CardGroup(
                      children: [
                        for (final r in groups[g].items)
                          _ReferralRow(
                            key: ValueKey(r.id),
                            referral: r,
                            onTap: () async {
                              final prov = context.read<TeacherProvider>();
                              await Navigator.of(context).push(
                                MaterialPageRoute(
                                  builder: (_) => ReferralDetailScreen(referralId: r.id),
                                ),
                              );
                              // A counselor may have advanced the status while it was open.
                              prov.loadReferrals();
                            },
                          ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ListLoadMoreFooter(visible: provider.loadingMoreReferrals),
        ],
      ),
    );
  }

  /// Newest-first referrals in consecutive "October" / "September 2025"
  /// buckets (the year only when it isn't this year).
  static List<_MonthGroup> _groupByMonth(List<Referral> referrals) {
    final groups = <_MonthGroup>[];
    final thisYear = DateTime.now().year;
    for (final r in referrals) {
      final date = DateTime.tryParse(r.createdAt ?? '')?.toLocal();
      final label = date == null
          ? 'Earlier'
          : DateFormat(date.year == thisYear ? 'MMMM' : 'MMMM yyyy').format(date);
      if (groups.isEmpty || groups.last.label != label) groups.add(_MonthGroup(label));
      groups.last.items.add(r);
    }
    return groups;
  }
}

class _MonthGroup {
  final String label;
  final List<Referral> items = [];
  _MonthGroup(this.label);
}

// ─────────────────────────────────────────────────────────

class _ReferralRow extends StatelessWidget {
  final Referral referral;
  final VoidCallback onTap;
  const _ReferralRow({super.key, required this.referral, required this.onTap});

  String get _dateLabel {
    final raw = referral.createdAt;
    if (raw == null || raw.isEmpty) return '—';
    try {
      return DateFormat('MMM d').format(DateTime.parse(raw).toLocal());
    } catch (_) {
      return raw;
    }
  }

  @override
  Widget build(BuildContext context) {
    final priority = referral.priority.toLowerCase();
    final meta = [
      referral.referralTypeLabel,
      _dateLabel,
      if (priority == 'high') 'High priority',
    ].join(' · ');

    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 12, 12, 12),
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    referral.studentName ?? 'Unknown student',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: AppTextStyles.sectionTitle,
                  ),
                  const SizedBox(height: 1),
                  Text(meta, maxLines: 1, overflow: TextOverflow.ellipsis, style: AppTextStyles.meta),
                  if (referral.counselorName != null || referral.isAutoEscalated) ...[
                    const SizedBox(height: 6),
                    Row(
                      children: [
                        if (referral.isAutoEscalated) ...[
                          AppBadge.auto(),
                          const SizedBox(width: 8),
                        ],
                        if (referral.counselorName != null)
                          Flexible(
                            child: Text(
                              referral.counselorName!,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: AppTextStyles.meta.copyWith(color: AppColors.text2),
                            ),
                          ),
                      ],
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(width: 10),
            AppBadge.status(referral.status),
          ],
        ),
      ),
    );
  }
}
