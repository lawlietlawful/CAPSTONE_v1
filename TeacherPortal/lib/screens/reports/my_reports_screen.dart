import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../core/constants/app_text_styles.dart';
import '../../models/behavioral_report.dart';
import '../../providers/teacher_provider.dart';
import '../../widgets/common/form_widgets.dart';
import '../../widgets/common/list_filter_sheet.dart';
import '../../widgets/common/navy_header.dart';
import '../../widgets/common/notification_bell.dart';
import '../../widgets/common/search_filter_bar.dart';
import '../../widgets/common/status_badges.dart';
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
        typeLabel: 'Incident Type',
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
        NavyHeader(
          title: 'My Reports',
          subtitle: provider.totalReports == 0
              ? 'Incidents you have filed'
              : '${provider.totalReports} incident${provider.totalReports == 1 ? '' : 's'} filed',
          showAvatar: false,
          trailing: const NotificationBell(),
        ),
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
    if (provider.loadingReports && provider.reports.isEmpty) {
      return const Center(child: CircularProgressIndicator());
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
            const SizedBox(height: 140),
            CenteredMessage(
              icon: filtered ? Icons.search_off_rounded : Icons.inbox_outlined,
              title: filtered ? 'No matching reports' : 'No reports yet',
              message: filtered
                  ? 'Try a different name or status filter.'
                  : 'Incidents you log will appear here. Pull down to refresh.',
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
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 92),
        // One extra slot for the trailing "loading more" spinner.
        itemCount: provider.reports.length + 1,
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (_, i) {
          if (i >= provider.reports.length) {
            return ListLoadMoreFooter(visible: provider.loadingMoreReports);
          }
          final report = provider.reports[i];
          return _ReportCard(report: report, onTap: () => _openDetail(report));
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
                        report.studentName ?? 'Unknown student',
                        style: AppTextStyles.sectionTitle,
                      ),
                      const SizedBox(height: 3),
                      Text(report.incidentType, style: AppTextStyles.meta),
                    ],
                  ),
                ),
                AppBadge.severity(report.severity),
              ],
            ),
            const SizedBox(height: 10),
            Text(
              report.description,
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
                if (report.location != null && report.location!.isNotEmpty) ...[
                  const SizedBox(width: 10),
                  const Icon(
                    Icons.place_outlined,
                    size: 13,
                    color: AppColors.text3,
                  ),
                  const SizedBox(width: 4),
                  Flexible(
                    child: Text(
                      report.location!,
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
                AppBadge.status(report.status),
                if (report.escalated) AppBadge.escalated(),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
