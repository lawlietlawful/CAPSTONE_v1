import 'package:flutter/material.dart';
import '../core/services/api_service.dart';
import '../core/constants/api_constants.dart';
import '../models/notification_model.dart';

class NotificationProvider extends ChangeNotifier {
  List<NotificationModel> _notifications = [];
  int _unreadCount = 0;
  bool _isLoading = false;
  String? _error;

  List<NotificationModel> get notifications => _notifications;
  int get unreadCount => _unreadCount;
  bool get isLoading => _isLoading;
  String? get error => _error;

  Future<void> fetch() async {
    _isLoading = true;
    _error = null;
    notifyListeners();
    try {
      final data = await ApiService.get(ApiConstants.notifications);
      _unreadCount = (data['unread_count'] as num?)?.toInt() ?? 0;
      final list = data['notifications'] as List? ?? [];
      _notifications = list
          .map((e) => NotificationModel.fromJson(e as Map<String, dynamic>))
          .toList()
        // Newest first, so date grouping headers are always in order.
        ..sort((a, b) => b.createdAt.compareTo(a.createdAt));
    } catch (e) {
      _error = e.toString();
    }
    _isLoading = false;
    notifyListeners();
  }

  Future<void> markRead(int id) async {
    final idx = _notifications.indexWhere((n) => n.id == id);
    if (idx == -1 || _notifications[idx].isRead) return;

    // Optimistic: flip the dot immediately, then sync with the server.
    _notifications[idx] = _notifications[idx].copyWith(isRead: true);
    if (_unreadCount > 0) _unreadCount--;
    notifyListeners();

    try {
      await ApiService.post(ApiConstants.notificationRead(id));
    } catch (_) {
      // Server refresh on next fetch will reconcile if this failed.
    }
  }

  Future<void> markAllRead() async {
    if (_unreadCount == 0 && _notifications.every((n) => n.isRead)) return;

    _notifications =
        _notifications.map((n) => n.copyWith(isRead: true)).toList();
    _unreadCount = 0;
    notifyListeners();

    try {
      await ApiService.post(ApiConstants.notificationsReadAll);
    } catch (_) {
      // Server refresh on next fetch will reconcile if this failed.
    }
  }
}
