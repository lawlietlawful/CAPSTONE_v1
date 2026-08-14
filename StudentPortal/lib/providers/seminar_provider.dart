import 'package:flutter/material.dart';
import '../core/services/api_service.dart';
import '../core/constants/api_constants.dart';
import '../models/seminar_model.dart';

class SeminarProvider extends ChangeNotifier {
  List<SeminarModel> _required = [];
  List<SeminarModel> _completed = [];
  bool _isLoading = false;
  String? _error;

  List<SeminarModel> get required => _required;
  List<SeminarModel> get completed => _completed;
  bool get isLoading => _isLoading;
  String? get error => _error;

  SeminarModel? get nextSeminar =>
      _required.isNotEmpty ? _required.first : null;

  Future<void> fetch() async {
    _isLoading = true;
    _error = null;
    notifyListeners();
    try {
      final data = await ApiService.get(ApiConstants.seminars);
      final req = data['required'] as List? ?? [];
      final comp = data['completed'] as List? ?? [];
      _required = req
          .map((e) => SeminarModel.fromJson(e as Map<String, dynamic>))
          .toList();
      _completed = comp
          .map((e) => SeminarModel.fromJson(e as Map<String, dynamic>))
          .toList();
    } catch (e) {
      _error = e.toString();
    }
    _isLoading = false;
    notifyListeners();
  }
}
