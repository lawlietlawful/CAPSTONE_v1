import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../core/constants/app_text_styles.dart';
import '../../models/student_detail.dart';
import '../../providers/teacher_provider.dart';
import '../../widgets/common/form_widgets.dart';
import 'seminar_detail_screen.dart';

/// Upcoming/ongoing seminars matching a recommended-intervention tag, for a
/// student who hasn't been enrolled in one yet. Opened by tapping the
/// "Recommended intervention" line on the student detail screen.
class MatchingSeminarsScreen extends StatefulWidget {
  final String tag;
  final String tagLabel;
  const MatchingSeminarsScreen({
    super.key,
    required this.tag,
    required this.tagLabel,
  });

  @override
  State<MatchingSeminarsScreen> createState() => _MatchingSeminarsScreenState();
}

class _MatchingSeminarsScreenState extends State<MatchingSeminarsScreen> {
  late Future<List<SeminarItem>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<SeminarItem>> _load() =>
      context.read<TeacherProvider>().fetchMatchingSeminars(widget.tag);

  void _retry() => setState(() => _future = _load());

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text(
          'Matching Seminars',
          style: GoogleFonts.inter(fontSize: 16, fontWeight: FontWeight.w500),
        ),
      ),
      body: FutureBuilder<List<SeminarItem>>(
        future: _future,
        builder: (context, snap) {
          if (snap.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snap.hasError) {
            return CenteredMessage(
              icon: Icons.wifi_off_rounded,
              title: 'Could not load seminars',
              message: 'Please check your connection and try again.',
              actionLabel: 'Retry',
              onAction: _retry,
            );
          }
          final seminars = snap.data ?? [];
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Padding(
                padding: const EdgeInsets.only(bottom: 14),
                child: Text(
                  'Sessions tagged "${widget.tagLabel}"',
                  style: GoogleFonts.inter(
                    fontSize: 12.5,
                    color: AppColors.text3,
                  ),
                ),
              ),
              if (seminars.isEmpty)
                const CenteredMessage(
                  icon: Icons.event_busy_rounded,
                  title: 'No session scheduled yet',
                  message:
                      'No upcoming seminar is currently tagged for this recommendation. '
                      'Ask Guidance if one is being planned.',
                )
              else
                ...seminars.map(
                  (s) => Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: _SeminarRow(seminar: s),
                  ),
                ),
            ],
          );
        },
      ),
    );
  }
}

class _SeminarRow extends StatelessWidget {
  final SeminarItem seminar;
  const _SeminarRow({required this.seminar});

  String get _dateLabel {
    final raw = seminar.date;
    if (raw == null || raw.isEmpty) return 'Not scheduled';
    try {
      return DateFormat('MMM d, yyyy').format(DateTime.parse(raw));
    } catch (_) {
      return raw;
    }
  }

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(12),
      onTap: () => Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => SeminarDetailScreen(seminar: seminar),
        ),
      ),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: AppColors.surface,
          border: Border.all(color: AppColors.border),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(seminar.title, style: AppTextStyles.sectionTitle),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      const Icon(
                        Icons.event_rounded,
                        size: 13,
                        color: AppColors.text3,
                      ),
                      const SizedBox(width: 4),
                      Text(_dateLabel, style: AppTextStyles.meta),
                      if (seminar.venue != null &&
                          seminar.venue!.isNotEmpty) ...[
                        const SizedBox(width: 10),
                        const Icon(
                          Icons.location_on_outlined,
                          size: 13,
                          color: AppColors.text3,
                        ),
                        const SizedBox(width: 4),
                        Flexible(
                          child: Text(
                            seminar.venue!,
                            overflow: TextOverflow.ellipsis,
                            style: AppTextStyles.meta,
                          ),
                        ),
                      ],
                    ],
                  ),
                ],
              ),
            ),
            const Icon(
              Icons.chevron_right_rounded,
              size: 18,
              color: AppColors.text3,
            ),
          ],
        ),
      ),
    );
  }
}
