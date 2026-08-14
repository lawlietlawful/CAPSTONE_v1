import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../../core/constants/app_colors.dart';
import '../../core/constants/app_text_styles.dart';
import '../../providers/seminar_provider.dart';
import '../../providers/student_provider.dart';
import '../../widgets/common/navy_header.dart';
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
          subtitle: '$requiredCount required · $completedCount completed',
          avatarInitials: sp.student?.initials ?? '?',
          onAvatarTap: () => StudentProfileSheet.show(context),
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
              child: CircularProgressIndicator(color: AppColors.accent),
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
          // ── Section 1: Required to attend ──────────────────
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text('Required to attend', style: AppTextStyles.sectionTitle),
              if (semp.required.isNotEmpty)
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: AppColors.redBg,
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    '${semp.required.length} pending',
                    style: GoogleFonts.inter(
                      fontSize: 10.5,
                      fontWeight: FontWeight.w500,
                      color: AppColors.redText,
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 8),
          if (semp.required.isEmpty)
            const _EmptyState(
              icon: Icons.school_outlined,
              message: 'No required seminars yet',
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
            Text('Completed', style: AppTextStyles.sectionTitle),
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
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.border),
      ),
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
