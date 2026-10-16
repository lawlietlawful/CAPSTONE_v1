import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/constants/app_colors.dart';
import '../../core/constants/app_decor.dart';
import '../../core/constants/app_text_styles.dart';
import '../../models/seminar_model.dart';
import '../../providers/seminar_provider.dart';
import '../../widgets/common/messages_button.dart';
import '../../widgets/common/page_header.dart';
import '../../widgets/common/surfaces.dart';
import '../../widgets/premium/motion.dart';
import '../../widgets/premium/skeleton.dart';
import '../../widgets/seminars/seminar_card.dart';
import 'seminar_detail_screen.dart';

/// The student's seminars: what's coming up, and how past ones went
/// (attended, missed, excused, cancelled).
class SeminarsScreen extends StatefulWidget {
  const SeminarsScreen({super.key});

  @override
  State<SeminarsScreen> createState() => _SeminarsScreenState();
}

class _SeminarsScreenState extends State<SeminarsScreen> {
  bool _showPast = false;

  void _open(SeminarModel seminar) => Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => SeminarDetailScreen(seminar: seminar)),
      );

  @override
  Widget build(BuildContext context) {
    final semp = context.watch<SeminarProvider>();

    return Column(
      children: [
        const PageHeader(
          title: 'My seminars',
          subtitle: 'From the Guidance Office',
          trailing: HeaderActions(),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 4),
          child: SegmentedTabs<bool>(
            options: [
              (label: 'Upcoming ${semp.required.length}', value: false),
              // Attended, missed, excused, cancelled — not only completed ones.
              (label: 'Past ${semp.completed.length}', value: true),
            ],
            selected: _showPast,
            onChanged: (v) => setState(() => _showPast = v),
          ),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: () => context.read<SeminarProvider>().fetch(),
            color: AppColors.accent,
            child: _buildBody(context, semp),
          ),
        ),
      ],
    );
  }

  Widget _buildBody(BuildContext context, SeminarProvider semp) {
    if (semp.isLoading && semp.required.isEmpty && semp.completed.isEmpty) {
      return const SkeletonList(count: 3, leading: true, padding: EdgeInsets.fromLTRB(16, 12, 16, 16));
    }

    if (semp.error != null && semp.required.isEmpty && semp.completed.isEmpty) {
      return const _Message(
        icon: Icons.wifi_off_rounded,
        title: 'Could not load seminars',
        message: 'Pull down to try again.',
      );
    }

    if (!_showPast) {
      if (semp.required.isEmpty) {
        return const _Message(
          icon: Icons.event_available_outlined,
          title: 'No upcoming seminars',
          message: 'Seminars the Guidance Office signs you up for will appear here.',
        );
      }
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
        children: [
          for (var i = 0; i < semp.required.length; i++)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: SeminarCard(
                seminar: semp.required[i],
                emphasised: i == 0,
                onViewDetails: () => _open(semp.required[i]),
              ),
            ),
          const InfoNote(
            icon: Icons.notifications_none_rounded,
            text: "You'll get a reminder the day before and on the day. "
                'You can turn reminders off in Settings.',
            background: AppColors.surface,
          ),
        ],
      );
    }

    if (semp.completed.isEmpty) {
      return const _Message(
        icon: Icons.history_rounded,
        title: 'No past seminars',
        message: 'Seminars you have attended will be listed here.',
      );
    }
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
      children: [
        FadeSlideIn(
          child: CardGroup(
            children: [
              for (final s in semp.completed) PastSeminarRow(seminar: s, onTap: () => _open(s)),
            ],
          ),
        ),
      ],
    );
  }
}

class _Message extends StatelessWidget {
  final IconData icon;
  final String title;
  final String message;
  const _Message({required this.icon, required this.title, required this.message});

  @override
  Widget build(BuildContext context) {
    return CustomScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      slivers: [
        SliverFillRemaining(
          hasScrollBody: false,
          child: Center(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 36),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 56,
                    height: 56,
                    decoration: AppDecor.card(radius: 28),
                    child: Icon(icon, size: 26, color: AppColors.text3),
                  ),
                  const SizedBox(height: 14),
                  Text(title, textAlign: TextAlign.center, style: AppTextStyles.sectionHeading),
                  const SizedBox(height: 4),
                  Text(message, textAlign: TextAlign.center, style: AppTextStyles.meta.copyWith(fontSize: 13.5)),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }
}
