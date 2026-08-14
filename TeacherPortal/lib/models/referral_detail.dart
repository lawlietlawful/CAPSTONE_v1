import 'referral.dart';

/// One step in a referral's status journey, marked done / current / upcoming.
class JourneyStep {
  final String key; // filed | in_progress | resolved | cancelled
  final String label;
  final String state; // done | current | upcoming
  final String? date; // ISO8601, null when no timestamp exists for this step

  const JourneyStep({
    required this.key,
    required this.label,
    required this.state,
    this.date,
  });

  bool get isDone => state == 'done';
  bool get isCurrent => state == 'current';
  bool get isCancelled => key == 'cancelled';

  factory JourneyStep.fromJson(Map<String, dynamic> json) {
    return JourneyStep(
      key: json['key']?.toString() ?? '',
      label: json['label']?.toString() ?? '',
      state: json['state']?.toString() ?? 'upcoming',
      date: json['date']?.toString(),
    );
  }
}

/// A referral plus its status journey and the counselor's notes — what
/// Guidance did after the teacher filed it.
class ReferralDetail {
  final Referral referral;
  final String? counselorNotes;
  final List<JourneyStep> journey;

  const ReferralDetail({
    required this.referral,
    required this.counselorNotes,
    required this.journey,
  });

  factory ReferralDetail.fromJson(Map<String, dynamic> json) {
    final r = json['referral'] as Map<String, dynamic>;
    return ReferralDetail(
      referral: Referral.fromJson(r),
      counselorNotes: r['counselor_notes']?.toString(),
      journey: (json['journey'] as List? ?? [])
          .map((e) => JourneyStep.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }
}
