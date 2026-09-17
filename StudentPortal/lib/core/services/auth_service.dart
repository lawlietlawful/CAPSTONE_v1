import 'package:shared_preferences/shared_preferences.dart';
import '../constants/api_constants.dart';
import 'api_service.dart';

class AuthService {
  static const _keyToken = 'auth_token';
  static const _keyName = 'student_name';
  static const _keyInitials = 'student_initials';
  static const _keyStudentId = 'student_id';
  static const _keyGrade = 'student_grade';
  static const _keySection = 'student_section';

  static Future<Map<String, dynamic>> login(
      String email, String password) async {
    final data = await ApiService.post(
      ApiConstants.login,
      body: {'email': email.trim(), 'password': password.trim()},
    );
    await _persistSession(data);
    return data;
  }

  /// First-time account activation: the Admin has already created the
  /// account with a locked password and a one-time activation code. The
  /// student proves identity with their Student ID + that code, then
  /// chooses their own password. Signs them in immediately on success.
  static Future<Map<String, dynamic>> activate({
    required String studentIdNumber,
    required String activationCode,
    required String newPassword,
  }) async {
    final data = await ApiService.post(
      ApiConstants.activate,
      body: {
        'student_id_number': studentIdNumber.trim(),
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
    final student = data['student'] as Map<String, dynamic>;

    ApiService.setToken(token);

    final firstName = student['first_name'] as String? ?? '';
    final lastName = student['last_name'] as String? ?? '';
    final name = '$firstName $lastName'.trim();
    final initials = _initials(name);

    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_keyToken, token);
    await prefs.setString(_keyName, name);
    await prefs.setString(_keyInitials, initials);
    await prefs.setInt(_keyStudentId, (student['id'] as num).toInt());
    await prefs.setString(_keyGrade, student['grade_level']?.toString() ?? '');
    await prefs.setString(_keySection, student['section']?.toString() ?? '');
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
      // Proceed with local logout even if server call fails
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
      'grade': prefs.getString(_keyGrade),
      'section': prefs.getString(_keySection),
    };
  }

  static String _initials(String name) {
    final parts = name.trim().split(' ');
    if (parts.length >= 2) {
      return '${parts.first[0]}${parts.last[0]}'.toUpperCase();
    }
    return name.isNotEmpty ? name[0].toUpperCase() : '?';
  }
}
