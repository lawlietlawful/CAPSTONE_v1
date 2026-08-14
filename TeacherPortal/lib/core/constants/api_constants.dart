import 'package:flutter/foundation.dart'
    show kIsWeb, defaultTargetPlatform, TargetPlatform;

class ApiConstants {
  /// Compile-time override, for running on a real phone over Wi-Fi where
  /// neither `localhost` nor the emulator alias resolves to your PC:
  ///
  ///   flutter run --dart-define=API_BASE_URL=http://192.168.1.5:8000/api
  ///
  /// The Laravel server must then be started with `php artisan serve
  /// --host=0.0.0.0` so it accepts connections from outside the machine.
  static const String _override = String.fromEnvironment('API_BASE_URL');

  /// Android emulators reach the host machine via 10.0.2.2, not localhost.
  /// Web and other platforms (desktop, iOS simulator) talk to the host directly.
  static String get baseUrl {
    if (_override.isNotEmpty) return _override;
    return (!kIsWeb && defaultTargetPlatform == TargetPlatform.android)
        ? 'http://10.0.2.2:8000/api'
        : 'http://localhost:8000/api';
  }

  // Auth
  static const String login = '/teacher/login';
  static const String activate = '/teacher/activate';
  static const String logout = '/logout';

  // Teacher portal
  static const String dashboard = '/teacher/dashboard';
  static const String students = '/teacher/students';
  static const String roster = '/teacher/roster';
  static const String reports = '/teacher/behavioral-reports';
  static const String referrals = '/teacher/referrals';

  static const String notifications = '/teacher/notifications';
  static const String notificationsReadAll = '/teacher/notifications/read-all';
  static const String changePassword = '/teacher/change-password';

  static String report(int id) => '/teacher/behavioral-reports/$id';
  static String referral(int id) => '/teacher/referrals/$id';
  static String studentDetail(int id) => '/teacher/students/$id';
  static String notificationRead(int id) => '/teacher/notifications/$id/read';
  static String matchingSeminars(String tag) =>
      '/teacher/seminars/matching?tag=${Uri.encodeQueryComponent(tag)}';
}

/// Fallback incident types, used only until GET /teacher/behavioral-reports
/// returns the authoritative list. The server validates against
/// BehavioralReport::INCIDENT_TYPES and rejects anything else with a 422, and
/// the escalation rules key off these exact strings — so this list must never
/// be the source of truth. See TeacherProvider.incidentTypes.
class IncidentTypes {
  static const List<String> all = [
    'Academic Failure',
    'Truancy',
    'Disciplinary Incident',
    'Other',
  ];
}
