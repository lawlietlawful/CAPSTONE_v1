import 'package:shared_preferences/shared_preferences.dart';
import '../constants/api_constants.dart';
import 'api_service.dart';

class AuthService {
  static const _keyToken = 'auth_token';
  static const _keyName = 'teacher_name';
  static const _keyInitials = 'teacher_initials';
  static const _keyEmail = 'teacher_email';

  /// Teacher login with Employee ID (or email) + password. Persists the Sanctum
  /// token and a little profile info for the header, then returns the raw
  /// response payload.
  static Future<Map<String, dynamic>> login(
    String email,
    String password,
  ) async {
    final data = await ApiService.post(
      ApiConstants.login,
      body: {'email': email.trim(), 'password': password.trim()},
    );
    await _persistSession(data);
    return data;
  }

  /// First-time activation: prove identity with Employee ID + the one-time code
  /// the admin issued, and set a chosen password. Signs the teacher in (same
  /// token + profile persistence as login) on success.
  static Future<Map<String, dynamic>> activate({
    required String schoolId,
    required String activationCode,
    required String newPassword,
  }) async {
    final data = await ApiService.post(
      ApiConstants.activate,
      body: {
        'school_id': schoolId.trim(),
        'activation_code': activationCode.trim(),
        'new_password': newPassword,
        'new_password_confirmation': newPassword,
      },
    );
    await _persistSession(data);
    return data;
  }

  static Future<void> _persistSession(Map<String, dynamic> data) async {
    final token = data['token'] as String;
    final teacher = data['teacher'] as Map<String, dynamic>;

    ApiService.setToken(token);

    final name = (teacher['name'] as String?)?.trim() ?? '';
    final email = teacher['email']?.toString() ?? '';

    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_keyToken, token);
    await prefs.setString(_keyName, name);
    await prefs.setString(_keyInitials, _initials(name));
    await prefs.setString(_keyEmail, email);
  }

  static Future<void> changePassword({
    required String currentPassword,
    required String newPassword,
  }) async {
    await ApiService.post(
      ApiConstants.changePassword,
      body: {
        'current_password': currentPassword,
        'new_password': newPassword,
        'new_password_confirmation': newPassword,
      },
    );
  }

  static Future<void> logout() async {
    try {
      await ApiService.post(ApiConstants.logout);
    } catch (_) {
      // Proceed with local logout even if the server call fails.
    }
    ApiService.clearToken();
    final prefs = await SharedPreferences.getInstance();
    await prefs.clear();
  }

  static Future<String?> getSavedToken() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_keyToken);
  }

  static Future<Map<String, String?>> getSavedProfile() async {
    final prefs = await SharedPreferences.getInstance();
    return {
      'name': prefs.getString(_keyName),
      'initials': prefs.getString(_keyInitials),
      'email': prefs.getString(_keyEmail),
    };
  }

  static String _initials(String name) {
    final parts = name.trim().split(RegExp(r'\s+'));
    if (parts.length >= 2 && parts.first.isNotEmpty && parts.last.isNotEmpty) {
      return '${parts.first[0]}${parts.last[0]}'.toUpperCase();
    }
    return name.isNotEmpty ? name[0].toUpperCase() : '?';
  }
}
