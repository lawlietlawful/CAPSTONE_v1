import 'package:flutter/foundation.dart' show kIsWeb, defaultTargetPlatform, TargetPlatform;

class ApiConstants {
  // Android emulators reach the host machine via 10.0.2.2, not localhost.
  // Web and other platforms (desktop, iOS simulator) talk to the host directly.
  static String get baseUrl => (!kIsWeb && defaultTargetPlatform == TargetPlatform.android)
      ? 'http://10.0.2.2:8000/api'
      : 'http://localhost:8000/api';

  // Auth
  static const String login = '/login';
  static const String logout = '/logout';
  static const String activate = '/activate';

  // Student portal
  static const String profile = '/student/profile';
  static const String seminars = '/student/seminars';
  static const String notifications = '/student/notifications';
  static const String riskLevel = '/student/risk-level';
  static const String referrals = '/student/referrals';

  static String notificationRead(int id) => '/student/notifications/read/$id';
  static const String notificationsReadAll = '/student/notifications/read-all';
  static const String changePassword = '/student/change-password';
}
