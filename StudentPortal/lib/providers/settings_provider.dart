import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// The student's two preferences, kept on this phone:
///  - Seminar reminders: a reminder the day before and on the day of each
///    upcoming seminar when the app is opened (MainShell).
///  - Alert notifications: new notices and Guidance messages pop up while
///    the app is open (MainShell's live alerts).
/// Signing out clears them, so the next student starts from the defaults.
class SettingsProvider extends ChangeNotifier {
  static const _keySeminarReminders = 'pref_seminar_reminders';
  static const _keyAlertNotifications = 'pref_alert_notifications';

  bool _seminarReminders = true;
  bool _alertNotifications = true;
  bool _loaded = false;

  bool get seminarReminders => _seminarReminders;
  bool get alertNotifications => _alertNotifications;
  bool get isLoaded => _loaded;

  SettingsProvider() {
    _ready = _load();
  }

  late Future<void> _ready;

  /// Completes once the stored preferences are in — read the switches after
  /// this, or a cold start sees the defaults instead of the student's choice.
  Future<void> get ready => _ready;

  /// Re-reads the stored preferences (after a sign-out cleared them).
  Future<void> reload() => _ready = _load();

  Future<void> _load() async {
    final prefs = await SharedPreferences.getInstance();
    _seminarReminders = prefs.getBool(_keySeminarReminders) ?? true;
    _alertNotifications = prefs.getBool(_keyAlertNotifications) ?? true;
    _loaded = true;
    notifyListeners();
  }

  Future<void> setSeminarReminders(bool value) async {
    _seminarReminders = value;
    notifyListeners();
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_keySeminarReminders, value);
  }

  Future<void> setAlertNotifications(bool value) async {
    _alertNotifications = value;
    notifyListeners();
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_keyAlertNotifications, value);
  }
}
