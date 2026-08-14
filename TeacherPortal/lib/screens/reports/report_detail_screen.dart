import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../core/constants/app_text_styles.dart';
import '../../models/behavioral_report.dart';
import '../../models/referral.dart';
import '../../providers/teacher_provider.dart';
import '../../widgets/common/form_widgets.dart';
import '../../widgets/common/status_badges.dart';

/// Full detail of one behavioral report, including the guidance referral it
/// escalated into. Mirrors resources/views/teacher/behavioral-reports/show.blade.php.
///
/// Always re-fetched from the server (never read from the cached list) so the
/// guidance status shown is current.
class ReportDetailScreen extends StatefulWidget {
  final int reportId;

  /// Shown while the fresh copy loads, so the screen isn't blank on open.
  final BehavioralReport? initial;

  const ReportDetailScreen({
    super.key,
    required this.reportId,
    this.initial,
  });

  @override
  State<ReportDetailScreen> createState() => _ReportDetailScreenState();
}

class _ReportDetailScreenState extends State<ReportDetailScreen> {
  BehavioralReport? _report;
  String? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _report = widget.initial;
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final fresh =
          await context.read<TeacherProvider>().fetchReport(widget.reportId);
      if (!mounted) return;
      setState(() {
        _report = fresh;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = 'Unable to load this report. Check your connection.';
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text(
          'Report Details',
          style: GoogleFonts.inter(fontSize: 16, fontWeight: FontWeight.w500),
        ),
      ),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    final report = _report;

    // Nothing cached to fall back on.
    if (report == null) {
      if (_loading) return const Center(child: CircularProgressIndicator());
      return CenteredMessage(
        icon: Icons.wifi_off_rounded,
        title: 'Could not load report',
        message: _error ?? 'Something went wrong.',
        actionLabel: 'Retry',
        onAction: _load,
      );
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
        children: [
          if (_error != null) ...[
            ErrorBanner(_error!),
            const SizedBox(height: 12),
          ],
          _Card(
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(report.studentName ?? 'Unknown student',
                            style: AppTextStyles.pageTitle),
                        const SizedBox(height: 3),
                        Text(report.incidentType, style: AppTextStyles.meta),
                      ],
                    ),
                  ),
                  AppBadge.severity(report.severity),
                ],
              ),
              const SizedBox(height: 14),
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
          const SizedBox(height: 12),

          _Card(
            children: [
              Text('Incident', style: AppTextStyles.pageTitle),
              const SizedBox(height: 12),
              _DetailRow(
                icon: Icons.event_rounded,
                label: 'Date',
                value: _formatDate(report.incidentDate),
              ),
              _DetailRow(
                icon: Icons.place_outlined,
                label: 'Location',
                value: (report.location?.isNotEmpty ?? false)
                    ? report.location!
                    : 'Not specified',
              ),
              _DetailRow(
                icon: Icons.auto_awesome_rounded,
                label: 'AI severity',
                value: report.severity,
              ),
              const SizedBox(height: 6),
              Text('Description', style: AppTextStyles.label),
              const SizedBox(height: 5),
              Text(
                report.description,
                style: GoogleFonts.inter(
                  fontSize: 12.5,
                  color: AppColors.text1,
                  height: 1.5,
                ),
              ),
            ],
          ),

          if (report.escalatedReferral != null) ...[
            const SizedBox(height: 12),
            _EscalatedReferralCard(referral: report.escalatedReferral!),
          ] else if (report.escalated) ...[
            // `escalated: true` with no referral attached means the detail
            // payload is stale — surface it rather than silently showing nothing.
            const SizedBox(height: 12),
            const _Card(
              children: [
                Text('This report was escalated, but the referral could not be '
                    'loaded. Pull down to refresh.'),
              ],
            ),
          ],
        ],
      ),
    );
  }

  static String _formatDate(String? raw) {
    if (raw == null || raw.isEmpty) return '—';
    try {
      return DateFormat('MMM d, yyyy').format(DateTime.parse(raw));
    } catch (_) {
      return raw;
    }
  }
}

// ─────────────────────────────────────────────────────────

class _EscalatedReferralCard extends StatelessWidget {
  final Referral referral;
  const _EscalatedReferralCard({required this.referral});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.purpleBg,
        border: Border.all(color: const Color(0x338B5CF6)),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.trending_up_rounded,
                  size: 18, color: AppColors.purpleText),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  'Escalated to Guidance',
                  style: GoogleFonts.inter(
                    fontSize: 13,
                    fontWeight: FontWeight.w500,
                    color: AppColors.purpleText,
                  ),
                ),
              ),
              AppBadge.priority(referral.priority),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            'This incident was serious enough to be referred to the guidance '
            'office automatically. The parent was notified by SMS.',
            style: GoogleFonts.inter(
              fontSize: 12,
              color: AppColors.purpleText,
              height: 1.45,
            ),
          ),
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(10),
            ),
            child: Column(
              children: [
                _DetailRow(
                  icon: Icons.label_outline_rounded,
                  label: 'Type',
                  value: referral.referralTypeLabel,
                ),
                _DetailRow(
                  icon: Icons.flag_outlined,
                  label: 'Status',
                  value: _cap(referral.status),
                ),
                _DetailRow(
                  icon: Icons.support_agent_rounded,
                  label: 'Counselor',
                  value: referral.counselorName ?? 'Not yet assigned',
                ),
                if (referral.riskLevel != null)
                  _DetailRow(
                    icon: Icons.insights_rounded,
                    label: 'Risk level',
                    value: _cap(referral.riskLevel!),
                    isLast: true,
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  static String _cap(String s) =>
      s.isEmpty ? s : '${s[0].toUpperCase()}${s.substring(1)}';
}

class _Card extends StatelessWidget {
  final List<Widget> children;
  const _Card({required this.children});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surface,
        border: Border.all(color: AppColors.border),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: children,
      ),
    );
  }
}

class _DetailRow extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;
  final bool isLast;

  const _DetailRow({
    required this.icon,
    required this.label,
    required this.value,
    this.isLast = false,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: isLast ? 0 : 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 14, color: AppColors.text3),
          const SizedBox(width: 8),
          Text(label, style: AppTextStyles.label),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: AppTextStyles.body,
            ),
          ),
        ],
      ),
    );
  }
}
