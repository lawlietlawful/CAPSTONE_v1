class RiskAssessment {
  final String riskLevel; // "low" | "moderate" | "high"
  final double riskScore;
  final String assessedAt;

  const RiskAssessment({
    required this.riskLevel,
    required this.riskScore,
    required this.assessedAt,
  });

  factory RiskAssessment.fromJson(Map<String, dynamic> json) {
    return RiskAssessment(
      riskLevel: json['risk_level'] as String? ?? 'low',
      riskScore: (json['risk_score'] as num?)?.toDouble() ?? 0.0,
      assessedAt: json['assessed_at'] as String? ?? '',
    );
  }
}
