import 'package:flutter/material.dart';
import '../core/services/auth_service.dart';
import '../core/services/api_service.dart';

enum AuthStatus { initial, loading, authenticated, unauthenticated, error }

class AuthProvider extends ChangeNotifier {
  AuthStatus _status = AuthStatus.initial;
  String? _errorMessage;

  AuthStatus get status => _status;
  String? get errorMessage => _errorMessage;
  bool get isLoading => _status == AuthStatus.loading;

  Future<bool> login(String email, String password) async {
    _status = AuthStatus.loading;
    _errorMessage = null;
    notifyListeners();
    try {
      await AuthService.login(email, password);
      _status = AuthStatus.authenticated;
      notifyListeners();
      return true;
    } on ApiException catch (e) {
      _errorMessage = e.message;
      _status = AuthStatus.error;
      notifyListeners();
      return false;
    } catch (_) {
      _errorMessage = 'Unable to connect. Please check your network.';
      _status = AuthStatus.error;
      notifyListeners();
      return false;
    }
  }

  Future<bool> activate({
    required String studentIdNumber,
    required String activationCode,
    required String newPassword,
  }) async {
    _status = AuthStatus.loading;
    _errorMessage = null;
    notifyListeners();
    try {
      await AuthService.activate(
        studentIdNumber: studentIdNumber,
        activationCode: activationCode,
        newPassword: newPassword,
      );
      _status = AuthStatus.authenticated;
      notifyListeners();
      return true;
    } on ApiException catch (e) {
      _errorMessage = e.message;
      _status = AuthStatus.error;
      notifyListeners();
      return false;
    } catch (_) {
      _errorMessage = 'Unable to connect. Please check your network.';
      _status = AuthStatus.error;
      notifyListeners();
      return false;
    }
  }

  /// Surfaces a client-side validation message through the same error banner
  /// used for server errors, without touching the authenticated/loading state.
  void setLocalError(String message) {
    _errorMessage = message;
    _status = AuthStatus.error;
    notifyListeners();
  }

  Future<void> logout(BuildContext context) async {
    await AuthService.logout();
    _status = AuthStatus.unauthenticated;
    notifyListeners();
    if (context.mounted) {
      Navigator.pushReplacementNamed(context, '/login');
    }
  }

  Future<bool> checkSavedToken() async {
    final token = await AuthService.getSavedToken();
    if (token != null && token.isNotEmpty) {
      ApiService.setToken(token);
      _status = AuthStatus.authenticated;
      notifyListeners();
      return true;
    }
    _status = AuthStatus.unauthenticated;
    notifyListeners();
    return false;
  }
}
