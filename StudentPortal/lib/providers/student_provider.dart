import 'package:flutter/material.dart';
import '../core/services/api_service.dart';
import '../core/constants/api_constants.dart';
import '../models/student_model.dart';
import '../models/referral_model.dart';

/// The signed-in student's profile and referrals. Deliberately no risk level
/// or score: students don't see the label staff use to prioritise them.
class StudentProvider extends ChangeNotifier {
  StudentModel? _student;
  List<ReferralModel> _referrals = [];
  bool _isLoading = false;
  String? _error;

  StudentModel? get student => _student;
  List<ReferralModel> get referrals => _referrals;
  bool get isLoading => _isLoading;
  String? get error => _error;

  ReferralModel? get activeReferral {
    final active = _referrals.where((r) => r.isActive).toList();
    return active.isNotEmpty ? active.first : null;
  }

  /// Forgets the signed-in student (sign-out / new session), so nothing of
  /// theirs is left on screen for whoever signs in next.
  void reset() {
    _student = null;
    _referrals = [];
    _isLoading = false;
    _error = null;
    notifyListeners();
  }

  Future<void> fetchAll() async {
    _isLoading = true;
    _error = null;
    notifyListeners();
    try {
      await Future.wait([
        _fetchProfile(),
        _fetchReferrals(),
      ]);
    } catch (e) {
      _error = e is ApiException ? e.message : 'Something went wrong. Pull down to try again.';
    }
    _isLoading = false;
    notifyListeners();
  }

  Future<void> _fetchProfile() async {
    final data = await ApiService.get(ApiConstants.profile);
    final studentData = data['student'] as Map<String, dynamic>? ?? data;
    _student = StudentModel.fromJson(studentData);
  }

  Future<void> _fetchReferrals() async {
    final data = await ApiService.get(ApiConstants.referrals);
    final all = (data['all_referrals'] as List? ?? []);
    _referrals = all
        .map((e) => ReferralModel.fromJson(e as Map<String, dynamic>))
        .toList();
  }
}
