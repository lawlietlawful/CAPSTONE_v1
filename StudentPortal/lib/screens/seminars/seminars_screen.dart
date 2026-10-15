import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../../core/constants/app_colors.dart';
import '../../widgets/premium/skeleton.dart';
import '../../core/constants/app_decor.dart';
import '../../core/constants/app_text_styles.dart';
import '../../providers/seminar_provider.dart';
import '../../providers/student_provider.dart';
import '../../widgets/common/navy_header.dart';
import '../../widgets/common/messages_button.dart';
import '../../widgets/common/student_profile_sheet.dart';
import '../../widgets/seminars/seminar_card.dart';
import 'seminar_detail_screen.dart';

class SeminarsScreen extends StatelessWidget {
  const SeminarsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final sp = context.watch<StudentProvider>();
    final semp = context.watch<SeminarProvider>();

    final requiredCount = semp.required.length;
    final completedCount = semp.completed.length;

    return Column(
      children: [
        NavyHeader(
          title: 'My seminars',
          subtitle: '$requiredCount upcoming · $completedCount past',
          avatarInitials: sp.student?.initials ?? '?',
          onAvatarTap: () => StudentProfileSheet.show(context),
          trailing: const MessagesButton(),
        ),
        Expanded(
          child: ColoredBox(
            color: AppColors.background,
            child: RefreshIndicator(
              onRefresh: () => context.read<SeminarProvider>().fetch(),
              color: AppColors.accent,
              child: _buildBody(context, semp),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildBody(BuildContext context, SeminarProvider semp) {
    if (semp.isLoading && semp.required.isEmpty && semp.completed.isEmpty) {
      return const CustomScrollView(
        physics: AlwaysScrollableScrollPhysics(),
        slivers: [
          SliverFillRemaining(
            hasScrollBody: false,
            child: Center(
              child: SizedBox(height: 520, child: SkeletonList(count: 4, leading: true)),
            ),
          ),
        ],
      );
    }

    if (semp.error != null && semp.required.isEmpty && semp.completed.isEmpty) {
      return CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          SliverFillRemaining(
            hasScrollBody: false,
            child: Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.wifi_off_rounded,
                      size: 40, color: AppColors.text3),
                  const SizedBox(height: 12),
                  Text('Could not load seminars',
                      style: AppTextStyles.sectionTitle),
                  const SizedBox(height: 4),
                  Text('Pull down to retry', style: AppTextStyles.meta),
                ],
              ),
            ),
          ),
        ],
      );
    }

    return SingleChildScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // ── Section 1: Upcoming ──────────────────
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text('Upcoming', style: AppTextStyles.sectionTitle),
              if (semp.required.isNotEmpty)
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: AppColors.accentLight,
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    '${semp.required.length} to attend',
                    style: GoogleFonts.inter(
                      fontSize: 10.5,
                      fontWeight: FontWeight.w500,
                      color: AppColors.accentDark,
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 8),
          if (semp.required.isEmpty)
            const _EmptyState(
              icon: Icons.school_outlined,
              message: 'No upcoming seminars',
            )
          else
            ...semp.required.map(
              (seminar) => Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: SeminarCard(
                  seminar: seminar,
                  onViewDetails: () => Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => SeminarDetailScreen(seminar: seminar),
                    ),
                  ),
                ),
              ),
            ),

          // ── Section 2: Completed ───────────────────────────
          if (semp.completed.isNotEmpty) ...[
            const SizedBox(height: 8),
            // Attended, missed, excused, cancelled — not only completed ones.
            Text('Past & Cancelled', style: AppTextStyles.sectionTitle),
            const SizedBox(height: 8),
            ...semp.completed.map(
              (seminar) => Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: SeminarCard(seminar: seminar, isCompleted: true),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _EmptyState extends StatelessWidget {
  final IconData icon;
  final String message;
  const _EmptyState({required this.icon, required this.message});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 32),
      decoration: AppDecor.card(),
      child: Column(
        children: [
          Icon(icon, size: 36, color: AppColors.text3),
          const SizedBox(height: 10),
          Text(message, style: AppTextStyles.meta),
        ],
      ),
    );
  }
}
