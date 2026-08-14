import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../../core/constants/app_colors.dart';
import '../../core/constants/app_text_styles.dart';
import '../../core/utils/date_utils.dart';
import '../../models/referral_model.dart';
import '../../models/seminar_model.dart';
import '../../providers/notification_provider.dart';
import '../../providers/seminar_provider.dart';
import '../../providers/student_provider.dart';
import '../../widgets/common/risk_banner.dart';
import '../../widgets/common/stat_card.dart';
import '../../widgets/common/student_profile_sheet.dart';
import '../../widgets/seminars/ai_assigned_badge.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _fetchAll());
  }

  // FIX 1 — also fetch notifications so the stat card and badge are accurate
  Future<void> _fetchAll() async {
    if (!mounted) return;
    await Future.wait([
      context.read<StudentProvider>().fetchAll(),
      context.read<SeminarProvider>().fetch(),
      context.read<NotificationProvider>().fetch(),
    ]);
  }

  String _greeting() {
    final h = DateTime.now().hour;
    if (h >= 5 && h < 12) return 'Good morning';
    if (h >= 12 && h < 18) return 'Good afternoon';
    return 'Good evening';
  }

  // PROFILE — show bottom sheet when avatar is tapped
  void _showProfileSheet() => StudentProfileSheet.show(context);

  @override
  Widget build(BuildContext context) {
    final sp = context.watch<StudentProvider>();
    final np = context.watch<NotificationProvider>();
    final semp = context.watch<SeminarProvider>();

    return Column(
      children: [
        _HomeHeroHeader(
          greeting: _greeting(),
          firstName: sp.student?.firstName ?? 'Student',
          gradeLevel: sp.student?.gradeLevelDisplay ?? '—',
          section: sp.student?.section ?? '—',
          initials: sp.student?.initials ?? '—',
          onAvatarTap: _showProfileSheet,
        ),
        Expanded(
          child: ColoredBox(
            color: AppColors.background,
            child: RefreshIndicator(
              onRefresh: _fetchAll,
              color: AppColors.accent,
              child: _buildBody(sp, np, semp),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildBody(
    StudentProvider sp,
    NotificationProvider np,
    SeminarProvider semp,
  ) {
    if ((sp.isLoading || semp.isLoading) && sp.student == null) {
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

    if (sp.error != null && sp.student == null) {
      return CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          SliverFillRemaining(
            hasScrollBody: false,
            child: Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.wifi_off_rounded, size: 40, color: AppColors.text3),
                  const SizedBox(height: 12),
                  Text('Could not load data', style: AppTextStyles.sectionTitle),
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
          // 1 · Referral alert banner
          if (sp.activeReferral != null) ...[
            _ReferralAlertBanner(referral: sp.activeReferral!),
            const SizedBox(height: 12),
          ],

          // 2 · Stat cards — 2-column grid
          Row(
            children: [
              Expanded(
                child: StatCard(
                  iconBg: AppColors.accentLight,
                  iconColor: AppColors.accent,
                  iconData: Icons.notifications_outlined,
                  value: '${np.unreadCount}',
                  label: 'Unread notifications',
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: StatCard(
                  iconBg: AppColors.purpleBg,
                  iconColor: AppColors.purple,
                  iconData: Icons.school_rounded,
                  value: '${semp.required.length}',
                  label: 'Seminars to attend',
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),

          // 3 · Risk level banner
          if (sp.riskAssessment != null) ...[
            RiskBanner(riskLevel: sp.riskAssessment!.riskLevel),
            const SizedBox(height: 12),
          ],

          // 4 · Next seminar preview
          if (semp.nextSeminar != null)
            _NextSeminarSection(seminar: semp.nextSeminar!),
        ],
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────
// Hero header
// ─────────────────────────────────────────────────────────────

class _HomeHeroHeader extends StatelessWidget {
  final String greeting;
  final String firstName;
  final String gradeLevel;
  final String section;
  final String initials;
  final VoidCallback onAvatarTap;

  const _HomeHeroHeader({
    required this.greeting,
    required this.firstName,
    required this.gradeLevel,
    required this.section,
    required this.initials,
    required this.onAvatarTap,
  });

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: AppColors.navy,
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 22),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('$greeting, $firstName!', style: AppTextStyles.heroGreeting),
                    const SizedBox(height: 3),
                    Text(
                      '$section · $gradeLevel · S.Y. 2025–2026',
                      style: AppTextStyles.navySubtitle,
                    ),
                  ],
                ),
              ),
              GestureDetector(
                onTap: onAvatarTap,
                child: _AvatarCircle(initials: initials),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _AvatarCircle extends StatelessWidget {
  final String initials;
  const _AvatarCircle({required this.initials});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 40,
      height: 40,
      decoration: const BoxDecoration(
        color: AppColors.navy2,
        shape: BoxShape.circle,
        border: Border.fromBorderSide(
          BorderSide(color: Color(0x26FFFFFF), width: 2),
        ),
      ),
      alignment: Alignment.center,
      child: Text(
        initials,
        style: GoogleFonts.inter(
          fontSize: 13,
          fontWeight: FontWeight.w500,
          color: AppColors.navyInitial,
        ),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────
// Referral alert banner
// ─────────────────────────────────────────────────────────────

class _ReferralAlertBanner extends StatelessWidget {
  final ReferralModel referral;
  const _ReferralAlertBanner({required this.referral});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF1D2D44), Color(0xFF0F1B2D)],
          begin: Alignment.centerLeft,
          end: Alignment.centerRight,
        ),
        borderRadius: BorderRadius.circular(14),
      ),
      padding: const EdgeInsets.fromLTRB(14, 14, 16, 14),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Padding(
            padding: EdgeInsets.only(top: 1),
            child: Icon(Icons.warning_amber_rounded, color: AppColors.navyAccent, size: 18),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'You have a pending referral',
                  style: GoogleFonts.inter(
                    fontSize: 13,
                    fontWeight: FontWeight.w500,
                    color: AppColors.navyText,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  '${referral.concernType} · Please see the Guidance Office',
                  style: GoogleFonts.inter(fontSize: 11, color: const Color(0x8CFFFFFF)),
                ),
                const SizedBox(height: 8),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: const Color(0x33EF4444),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    '● Pending review',
                    style: GoogleFonts.inter(fontSize: 10, color: const Color(0xFFFCA5A5)),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────
// Next seminar section + preview card
// ─────────────────────────────────────────────────────────────

class _NextSeminarSection extends StatelessWidget {
  final SeminarModel seminar;
  const _NextSeminarSection({required this.seminar});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text('Next seminar', style: AppTextStyles.sectionTitle),
            Text(
              'See all →',
              style: GoogleFonts.inter(
                fontSize: 12,
                fontWeight: FontWeight.w500,
                color: AppColors.accent,
              ),
            ),
          ],
        ),
        const SizedBox(height: 10),
        _SeminarPreviewCard(seminar: seminar),
      ],
    );
  }
}

class _SeminarPreviewCard extends StatelessWidget {
  final SeminarModel seminar;
  const _SeminarPreviewCard({required this.seminar});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Text(
                  seminar.title,
                  style: AppTextStyles.sectionTitle,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              if (seminar.isAiAssigned) ...[
                const SizedBox(width: 8),
                const AiAssignedBadge(),
              ],
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              const Icon(Icons.calendar_today_rounded, size: 12, color: AppColors.text3),
              const SizedBox(width: 4),
              Text(AppDateUtils.formatSeminarDate(seminar.date), style: AppTextStyles.meta),
              const SizedBox(width: 14),
              const Icon(Icons.access_time_rounded, size: 12, color: AppColors.text3),
              const SizedBox(width: 4),
              Text(AppDateUtils.formatTime(seminar.time), style: AppTextStyles.meta),
            ],
          ),
          const SizedBox(height: 5),
          Row(
            children: [
              const Icon(Icons.location_on_outlined, size: 12, color: AppColors.text3),
              const SizedBox(width: 4),
              Text(seminar.venue, style: AppTextStyles.meta),
            ],
          ),
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
            decoration: BoxDecoration(
              color: AppColors.accentLight,
              borderRadius: BorderRadius.circular(6),
            ),
            child: Text(
              'Required',
              style: GoogleFonts.inter(
                fontSize: 10.5,
                fontWeight: FontWeight.w500,
                color: AppColors.accent,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
