import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../core/constants/app_text_styles.dart';
import '../../models/referral.dart';
import '../../providers/teacher_provider.dart';
import '../../widgets/common/form_widgets.dart';
import '../../widgets/common/list_filter_sheet.dart';
import '../../widgets/common/navy_header.dart';
import '../../widgets/common/notification_bell.dart';
import '../../widgets/common/search_filter_bar.dart';
import '../../widgets/common/status_badges.dart';
import 'referral_detail_screen.dart';

/// Read-only history of the referrals this teacher has filed, plus a shortcut
/// to file a new one. Mirrors resources/views/teacher/referrals/index.blade.php.
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
      backgroundColor: AppColors.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
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
        NavyHeader(
          title: 'My Referrals',
          subtitle: count == 0
              ? 'Students you have referred to guidance'
              : '$count filed · $pending pending',
          showAvatar: false,
          trailing: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const NotificationBell(),
              const SizedBox(width: 10),
              GestureDetector(
                onTap: widget.onFileReferral,
                child: Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    color: AppColors.navy2,
                    shape: BoxShape.circle,
                    border: Border.all(color: AppColors.navyBorder, width: 2),
                  ),
                  child: const Icon(
                    Icons.add_rounded,
                    size: 20,
                    color: AppColors.navyText,
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
        Expanded(
          child: Container(
            color: AppColors.background,
            child: _buildBody(provider),
          ),
        ),
      ],
    );
  }

  Widget _buildBody(TeacherProvider provider) {
    if (provider.loadingReferrals && provider.referrals.isEmpty) {
      return const Center(child: CircularProgressIndicator());
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
            const SizedBox(height: 140),
            CenteredMessage(
              icon: filtered ? Icons.search_off_rounded : Icons.outbox_outlined,
              title: filtered ? 'No matching referrals' : 'No referrals filed',
              message: filtered
                  ? 'Try a different name or status filter.'
                  : 'Students you refer to guidance will appear here. Tap + to '
                        'file one.',
              actionLabel: filtered ? 'Clear filters' : null,
              onAction: filtered
                  ? () => provider.applyReferralFilter(search: '', status: null)
                  : null,
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () => provider.loadReferrals(),
      child: ListView.separated(
        controller: _scrollController,
        physics: const AlwaysScrollableScrollPhysics(),
        // Extra bottom padding so the last card can scroll clear of the FAB.
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 92),
        // One extra slot for the trailing "loading more" spinner.
        itemCount: provider.referrals.length + 1,
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (_, i) {
          if (i >= provider.referrals.length) {
            return ListLoadMoreFooter(visible: provider.loadingMoreReferrals);
          }
          final referral = provider.referrals[i];
          return _ReferralCard(
            referral: referral,
            onTap: () async {
              final prov = context.read<TeacherProvider>();
              await Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => ReferralDetailScreen(referralId: referral.id),
                ),
              );
              // A counselor may have advanced the status while it was open.
              prov.loadReferrals();
            },
          );
        },
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────

class _ReferralCard extends StatelessWidget {
  final Referral referral;
  final VoidCallback onTap;
  const _ReferralCard({required this.referral, required this.onTap});

  String get _dateLabel {
    final raw = referral.createdAt;
    if (raw == null || raw.isEmpty) return '—';
    try {
      return DateFormat('MMM d, yyyy').format(DateTime.parse(raw).toLocal());
    } catch (_) {
      return raw;
    }
  }

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: AppColors.surface,
          border: Border.all(color: AppColors.border),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        referral.studentName ?? 'Unknown student',
                        style: AppTextStyles.sectionTitle,
                      ),
                      const SizedBox(height: 3),
                      Text(
                        referral.referralTypeLabel,
                        style: AppTextStyles.meta,
                      ),
                    ],
                  ),
                ),
                AppBadge.priority(referral.priority),
              ],
            ),
            const SizedBox(height: 10),
            Text(
              referral.reason,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: GoogleFonts.inter(
                fontSize: 12,
                color: AppColors.text2,
                height: 1.45,
              ),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                const Icon(
                  Icons.event_rounded,
                  size: 13,
                  color: AppColors.text3,
                ),
                const SizedBox(width: 4),
                Text(_dateLabel, style: AppTextStyles.meta),
                if (referral.counselorName != null) ...[
                  const SizedBox(width: 10),
                  const Icon(
                    Icons.support_agent_rounded,
                    size: 13,
                    color: AppColors.text3,
                  ),
                  const SizedBox(width: 4),
                  Flexible(
                    child: Text(
                      referral.counselorName!,
                      overflow: TextOverflow.ellipsis,
                      style: AppTextStyles.meta,
                    ),
                  ),
                ],
                const Spacer(),
                const Icon(
                  Icons.chevron_right_rounded,
                  size: 18,
                  color: AppColors.text3,
                ),
              ],
            ),
            const SizedBox(height: 10),
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                AppBadge.status(referral.status),
                if (referral.isAutoEscalated) AppBadge.auto(),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
