import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../core/constants/app_decor.dart';
import '../../core/constants/app_text_styles.dart';
import '../../models/behavioral_report.dart';
import '../../providers/teacher_provider.dart';
import '../../widgets/common/form_widgets.dart';
import '../../widgets/common/list_filter_sheet.dart';
import '../../widgets/common/drafts_banner.dart';
import '../../widgets/common/page_header.dart';
import '../../widgets/common/notification_bell.dart';
import '../../widgets/common/search_filter_bar.dart';
import '../../widgets/common/status_badges.dart';
import '../../widgets/premium/motion.dart';
import '../../widgets/premium/skeleton.dart';
import 'report_detail_screen.dart';

/// History of the behavioral reports this teacher has filed. Tapping a card
/// opens its full detail, including the referral it escalated into.
class MyReportsScreen extends StatefulWidget {
  const MyReportsScreen({super.key});

  @override
  State<MyReportsScreen> createState() => _MyReportsScreenState();
}

class _MyReportsScreenState extends State<MyReportsScreen> {
  final _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) context.read<TeacherProvider>().loadReports();
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
      context.read<TeacherProvider>().loadMoreReports();
    }
  }

  void _openDetail(BehavioralReport report) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) =>
            ReportDetailScreen(reportId: report.id, initial: report),
      ),
    );
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
        typeLabel: 'Concern type',
        typeOptions: provider.incidentTypes,
        selectedType: provider.reportIncidentType,
        secondaryLabel: 'Severity',
        secondaryOptions: const [
          (label: 'High', value: 'High'),
          (label: 'Medium', value: 'Medium'),
          (label: 'Low', value: 'Low'),
          (label: 'Unassessed', value: 'Unassessed'),
        ],
        selectedSecondary: provider.reportSeverity,
        dateFrom: provider.reportDateFrom,
        dateTo: provider.reportDateTo,
        onApply: (type, severity, from, to) => provider.applyReportFilter(
          search: provider.reportSearch,
          status: provider.reportStatus,
          incidentType: type,
          severity: severity,
          dateFrom: from,
          dateTo: to,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<TeacherProvider>();

    return Column(
      children: [
        PageHeader(
          title: 'My Reports',
          subtitle: provider.totalReports == 0
              ? 'Concerns you have reported'
              : '${provider.totalReports} report${provider.totalReports == 1 ? '' : 's'} filed',
          trailing: const HeaderActions(),
        ),
        const DraftsBanner(padding: EdgeInsets.fromLTRB(16, 0, 16, 12)),
        if (provider.reports.isNotEmpty || provider.reportFilterActive)
          SearchFilterBar(
            hint: 'Search by student name',
            initialSearch: provider.reportSearch,
            selectedStatus: provider.reportStatus,
            statuses: const [
              (label: 'All', value: null),
              (label: 'Pending', value: 'pending'),
              (label: 'Reviewed', value: 'reviewed'),
              (label: 'Resolved', value: 'resolved'),
            ],
            onSearchChanged: (s) => provider.applyReportFilter(
              search: s,
              status: provider.reportStatus,
              incidentType: provider.reportIncidentType,
              severity: provider.reportSeverity,
              dateFrom: provider.reportDateFrom,
              dateTo: provider.reportDateTo,
            ),
            onStatusChanged: (st) => provider.applyReportFilter(
              search: provider.reportSearch,
              status: st,
              incidentType: provider.reportIncidentType,
              severity: provider.reportSeverity,
              dateFrom: provider.reportDateFrom,
              dateTo: provider.reportDateTo,
            ),
            onMoreFilters: () => _openFilterSheet(provider),
            moreFiltersActive:
                provider.reportIncidentType != null ||
                provider.reportSeverity != null ||
                provider.reportDateFrom != null ||
                provider.reportDateTo != null,
          ),
        Expanded(child: _buildBody(provider)),
      ],
    );
  }

  Widget _buildBody(TeacherProvider provider) {
    if (provider.loadingReports && provider.reports.isEmpty) {
      return const SkeletonList();
    }

    if (provider.reportsError != null && provider.reports.isEmpty) {
      return CenteredMessage(
        icon: Icons.wifi_off_rounded,
        title: 'Could not load reports',
        message: provider.reportsError!,
        actionLabel: 'Retry',
        onAction: () => provider.loadReports(),
      );
    }

    if (provider.reports.isEmpty) {
      final filtered = provider.reportFilterActive;
      return RefreshIndicator(
        onRefresh: () => provider.loadReports(),
        child: ListView(
          // Needs a scrollable child so pull-to-refresh works when empty.
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            const SizedBox(height: 120),
            CenteredMessage(
              icon: filtered ? Icons.search_off_rounded : Icons.inbox_outlined,
              title: filtered ? 'No matching reports' : 'No reports yet',
              message: filtered
                  ? 'Try a different name or status filter.'
                  : 'Concerns you report will appear here. Pull down to refresh.',
              actionLabel: filtered ? 'Clear filters' : null,
              onAction: filtered
                  ? () => provider.applyReportFilter(search: '', status: null)
                  : null,
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () => provider.loadReports(),
      child: ListView.separated(
        controller: _scrollController,
        physics: const AlwaysScrollableScrollPhysics(),
        // Extra bottom padding so the last card can scroll clear of the FAB.
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 96),
        // One extra slot for the trailing "loading more" spinner.
        itemCount: provider.reports.length + 1,
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (_, i) {
          if (i >= provider.reports.length) {
            return ListLoadMoreFooter(visible: provider.loadingMoreReports);
          }
          final report = provider.reports[i];
          return FadeSlideIn(
            key: ValueKey(report.id),
            index: i % 20,
            child: _ReportCard(report: report, onTap: () => _openDetail(report)),
          );
        },
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────

class _ReportCard extends StatelessWidget {
  final BehavioralReport report;
  final VoidCallback onTap;

  const _ReportCard({required this.report, required this.onTap});

  /// `incident_date` arrives as "yyyy-MM-dd"; fall back to the raw string if
  /// it's ever missing or unparseable.
  String get _dateLabel {
    final raw = report.incidentDate;
    if (raw == null || raw.isEmpty) return '—';
    try {
      return DateFormat('MMM d, yyyy').format(DateTime.parse(raw));
    } catch (_) {
      return raw;
    }
  }

  @override
  Widget build(BuildContext context) {
    final meta = [
      report.incidentType,
      _dateLabel,
      if (report.location != null && report.location!.isNotEmpty) report.location!,
    ].join(' · ');

    return Pressable(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.fromLTRB(16, 14, 12, 14),
        decoration: AppDecor.card(),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          report.studentName ?? 'Unknown student',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: AppTextStyles.sectionTitle,
                        ),
                      ),
                      const SizedBox(width: 8),
                      AppBadge.severity(report.severity),
                    ],
                  ),
                  const SizedBox(height: 1),
                  Text(meta, maxLines: 1, overflow: TextOverflow.ellipsis, style: AppTextStyles.meta),
                  const SizedBox(height: 8),
                  Text(
                    report.description,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: AppTextStyles.bodySecondary.copyWith(fontSize: 13),
                  ),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 6,
                    runSpacing: 6,
                    children: [
                      AppBadge.status(report.status),
                      if (report.escalated) AppBadge.escalated(),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(width: 4),
            const Icon(Icons.chevron_right_rounded, size: 20, color: AppColors.iconMuted),
          ],
        ),
      ),
    );
  }
}
