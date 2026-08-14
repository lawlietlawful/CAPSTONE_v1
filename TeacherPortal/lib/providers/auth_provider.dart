import 'package:flutter/material.dart';
import '../core/services/auth_service.dart';
import '../core/services/api_service.dart';

enum AuthStatus { initial, loading, authenticated, unauthenticated, error }

class AuthProvider extends ChangeNotifier {
  AuthStatus _status = AuthStatus.initial;
  String? _errorMessage;

  String _name = '';
  String _initials = '?';
  String _email = '';

  AuthStatus get status => _status;
  String? get errorMessage => _errorMessage;
  bool get isLoading => _status == AuthStatus.loading;

  String get name => _name;
  String get initials => _initials;
  String get email => _email;

  /// First name only, for the friendly header greeting.
  String get firstName => _name.trim().isEmpty ? 'Teacher' : _name.trim().split(RegExp(r'\s+')).first;

  Future<bool> login(String email, String password) async {
    _status = AuthStatus.loading;
    _errorMessage = null;
    notifyListeners();
    try {
      await AuthService.login(email, password);
      await loadProfile();
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

  /// First-time activation: Employee ID + one-time code -> set a password and
  /// sign in. Mirrors [login]'s state handling.
  Future<bool> activate({
    required String schoolId,
    required String activationCode,
    required String newPassword,
  }) async {
    _status = AuthStatus.loading;
    _errorMessage = null;
    notifyListeners();
    try {
      await AuthService.activate(
        schoolId: schoolId,
        activationCode: activationCode,
        newPassword: newPassword,
      );
      await loadProfile();
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

  /// Reads the cached teacher profile persisted at login. Called on app start
  /// (restored session) and after a fresh login.
  Future<void> loadProfile() async {
    final profile = await AuthService.getSavedProfile();
    _name = profile['name'] ?? '';
    _initials = profile['initials'] ?? '?';
    _email = profile['email'] ?? '';
    notifyListeners();
  }

  /// Surfaces a client-side validation message through the same error banner
  /// used for server errors, without touching the authenticated/loading state.
  void setLocalError(String message) {
    _errorMessage = message;
    _status = AuthStatus.error;
    notifyListeners();
  }

  /// Clears the server token and all local session state. Navigation is left
  /// to the caller, which keeps this safe to call from a widget (e.g. a bottom
  /// sheet) that is being dismissed at the same time.
  Future<void> logout() async {
    await AuthService.logout();
    _name = '';
    _initials = '?';
    _email = '';
    _status = AuthStatus.unauthenticated;
    notifyListeners();
  }
}
