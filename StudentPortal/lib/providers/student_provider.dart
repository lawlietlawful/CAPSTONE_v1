import 'package:flutter/material.dart';
import '../core/services/api_service.dart';
import '../core/constants/api_constants.dart';
import '../models/student_model.dart';
import '../models/risk_assessment_model.dart';
import '../models/referral_model.dart';

class StudentProvider extends ChangeNotifier {
  StudentModel? _student;
  RiskAssessment? _riskAssessment;
  List<ReferralModel> _referrals = [];
  bool _isLoading = false;
  String? _error;

  StudentModel? get student => _student;
  RiskAssessment? get riskAssessment => _riskAssessment;
  List<ReferralModel> get referrals => _referrals;
  bool get isLoading => _isLoading;
  String? get error => _error;

  ReferralModel? get activeReferral {
    final active = _referrals.where((r) => r.isActive).toList();
    return active.isNotEmpty ? active.first : null;
  }

  Future<void> fetchAll() async {
    _isLoading = true;
    _error = null;
    notifyListeners();
    try {
      await Future.wait([
        _fetchProfile(),
        _fetchRiskLevel(),
        _fetchReferrals(),
      ]);
    } catch (e) {
      _error = e.toString();
    }
    _isLoading = false;
    notifyListeners();
  }

  Future<void> _fetchProfile() async {
    final data = await ApiService.get(ApiConstants.profile);
    final studentData =
        data['student'] as Map<String, dynamic>? ?? data;
    _student = StudentModel.fromJson(studentData);
  }

  Future<void> _fetchRiskLevel() async {
    final data = await ApiService.get(ApiConstants.riskLevel);
    // Spec returns `has_assessment: false` (with null fields) when the student
    // has never been assessed — in that case hide the banner entirely.
    if (data['has_assessment'] == false) {
      _riskAssessment = null;
    } else {
      _riskAssessment = RiskAssessment.fromJson(data);
    }
  }

  Future<void> _fetchReferrals() async {
    final data = await ApiService.get(ApiConstants.referrals);
    // Spec shape: { has_active_referral, active_referral, all_referrals }.
    // Older/simpler shapes (`referrals` / `data`) kept as fallbacks.
    final all =
        (data['all_referrals'] ?? data['referrals'] ?? data['data'] ?? [])
            as List;
    var list = all
        .map((e) => ReferralModel.fromJson(e as Map<String, dynamic>))
        .toList();
    // If only the single active referral is returned, use it.
    if (list.isEmpty && data['active_referral'] != null) {
      list = [
        ReferralModel.fromJson(data['active_referral'] as Map<String, dynamic>)
      ];
    }
    _referrals = list;
  }
}
