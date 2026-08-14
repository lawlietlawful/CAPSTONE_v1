import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Holds local, per-device user preferences persisted with shared_preferences.
///
/// These are UI/notification preferences the student controls on their own
/// phone. They are stored locally and survive app restarts. Wiring them to
/// backend push/SMS behaviour is future work once the Laravel API is live.
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
    _load();
  }

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
