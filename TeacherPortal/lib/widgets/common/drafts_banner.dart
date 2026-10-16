import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../models/report_draft.dart';
import '../../providers/teacher_provider.dart';

/// "N unsent reports" — reports saved on the phone while offline. Send them
/// now, or open the list to review or discard one.
class DraftsBanner extends StatelessWidget {
  final EdgeInsetsGeometry padding;
  const DraftsBanner({super.key, this.padding = const EdgeInsets.fromLTRB(16, 12, 16, 0)});

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<TeacherProvider>();
    final count = provider.drafts.length;
    if (count == 0) return const SizedBox.shrink();

    return Padding(
      padding: padding,
      child: Material(
        color: AppColors.amberBg,
        borderRadius: BorderRadius.circular(12),
        child: InkWell(
          key: const Key('drafts-banner'),
          borderRadius: BorderRadius.circular(12),
          onTap: () => _showDrafts(context),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(14, 12, 12, 12),
            child: Row(
              children: [
                const Icon(Icons.cloud_off_rounded, size: 20, color: AppColors.amberText),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        '$count unsent report${count == 1 ? '' : 's'}',
                        style: GoogleFonts.publicSans(fontSize: 14, fontWeight: FontWeight.w600, color: const Color(0xFF6E3F05)),
                      ),
                      const SizedBox(height: 1),
                      Text(
                        'Saved on this phone. Tap to review.',
                        style: GoogleFonts.publicSans(fontSize: 12.5, color: AppColors.amberText),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                provider.sendingDrafts
                    ? const Padding(
                        padding: EdgeInsets.symmetric(horizontal: 22),
                        child: SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(strokeWidth: 2, color: AppColors.amberText),
                        ),
                      )
                    : OutlinedButton(
                        key: const Key('send-drafts'),
                        onPressed: () => _send(context),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: const Color(0xFF6E3F05),
                          side: const BorderSide(color: Color(0xFFEBC98C)),
                          minimumSize: const Size(44, 40),
                          padding: const EdgeInsets.symmetric(horizontal: 12),
                        ),
                        child: const Text('Send now'),
                      ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  static Future<void> _send(BuildContext context) async {
    final provider = context.read<TeacherProvider>();
    final messenger = ScaffoldMessenger.of(context);
    final before = provider.drafts.length;
    final sent = await provider.sendDrafts();
    final left = provider.drafts.length;
    messenger.showSnackBar(SnackBar(
      content: Text(
        sent == before
            ? (sent == 1 ? 'Report sent.' : 'All $sent reports sent.')
            : sent > 0
                ? '$sent sent, $left still waiting.'
                : provider.draftErrors.isNotEmpty
                    ? 'A report could not be sent. Tap the banner to see why.'
                    : 'Still no connection. Your drafts are safe on this phone.',
      ),
    ));
  }

  static void _showDrafts(BuildContext context) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: AppColors.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (_) => const _DraftsSheet(),
    );
  }
}

class _DraftsSheet extends StatelessWidget {
  const _DraftsSheet();

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<TeacherProvider>();
    final drafts = provider.drafts;

    return SafeArea(
      child: ConstrainedBox(
        constraints: BoxConstraints(maxHeight: MediaQuery.of(context).size.height * 0.7),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 18, 20, 12),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Unsent reports',
                style: GoogleFonts.publicSans(fontSize: 16, fontWeight: FontWeight.w600, color: AppColors.text1),
              ),
              const SizedBox(height: 4),
              Text(
                'These are sent in order when you have a connection. Signing out deletes them.',
                style: GoogleFonts.publicSans(fontSize: 12, color: AppColors.text3, height: 1.4),
              ),
              const SizedBox(height: 14),
              if (drafts.isEmpty)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 24),
                  child: Center(
                    child: Text('All sent.', style: GoogleFonts.publicSans(color: AppColors.text3)),
                  ),
                )
              else
                Flexible(
                  child: ListView.separated(
                    shrinkWrap: true,
                    itemCount: drafts.length,
                    separatorBuilder: (_, _) => const SizedBox(height: 8),
                    itemBuilder: (_, i) => _DraftTile(
                      draft: drafts[i],
                      error: provider.draftErrors[drafts[i].id],
                    ),
                  ),
                ),
              const SizedBox(height: 14),
              if (drafts.isNotEmpty)
                SizedBox(
                  width: double.infinity,
                  child: FilledButton.icon(
                    onPressed: provider.sendingDrafts ? null : () => DraftsBanner._send(context),
                    style: FilledButton.styleFrom(
                      backgroundColor: AppColors.accent,
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                    icon: const Icon(Icons.send_rounded, size: 16),
                    label: Text(
                      provider.sendingDrafts ? 'Sending…' : 'Send all now',
                      style: GoogleFonts.publicSans(fontWeight: FontWeight.w600),
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _DraftTile extends StatelessWidget {
  final ReportDraft draft;
  final String? error;
  const _DraftTile({required this.draft, this.error});

  @override
  Widget build(BuildContext context) {
    final date = DateTime.tryParse(draft.incidentDate);
    return Container(
      padding: const EdgeInsets.fromLTRB(12, 10, 4, 10),
      decoration: BoxDecoration(
        color: AppColors.background,
        border: Border.all(color: error != null ? const Color(0x33EF4444) : AppColors.border),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  draft.studentName,
                  style: GoogleFonts.publicSans(fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.text1),
                ),
                const SizedBox(height: 2),
                Text(
                  [draft.incidentType, if (date != null) DateFormat('MMM d, yyyy').format(date)].join(' · '),
                  style: GoogleFonts.publicSans(fontSize: 12, color: AppColors.text3),
                ),
                const SizedBox(height: 4),
                Text(
                  draft.description,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: GoogleFonts.publicSans(fontSize: 12, color: AppColors.text2, height: 1.4),
                ),
                if (error != null) ...[
                  const SizedBox(height: 6),
                  Text(
                    error!,
                    style: GoogleFonts.publicSans(fontSize: 12, color: AppColors.redText),
                  ),
                ],
              ],
            ),
          ),
          IconButton(
            tooltip: 'Discard draft',
            icon: const Icon(Icons.delete_outline_rounded, size: 18, color: AppColors.text3),
            onPressed: () async {
              final ok = await showDialog<bool>(
                context: context,
                builder: (ctx) => AlertDialog(
                  title: const Text('Discard this draft?'),
                  content: Text(
                    'The report on ${draft.studentName} will be deleted from this phone.',
                    style: GoogleFonts.publicSans(fontSize: 13, color: AppColors.text2),
                  ),
                  actions: [
                    TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Keep')),
                    TextButton(
                      onPressed: () => Navigator.pop(ctx, true),
                      child: Text('Discard', style: GoogleFonts.publicSans(color: AppColors.red)),
                    ),
                  ],
                ),
              );
              if (ok == true && context.mounted) {
                context.read<TeacherProvider>().discardDraft(draft.id);
              }
            },
          ),
        ],
      ),
    );
  }
}
