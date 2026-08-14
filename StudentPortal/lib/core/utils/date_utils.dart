import 'package:intl/intl.dart';

class AppDateUtils {
  /// "2026-06-05" → "Wednesday, June 5"
  static String formatAttendanceDate(String isoDate) {
    final date = DateTime.tryParse(isoDate);
    if (date == null) return isoDate;
    return DateFormat('EEEE, MMMM d').format(date);
  }

  /// "2026-07-04" → "Friday, July 4, 2026"
  static String formatFullDate(String isoDate) {
    final date = DateTime.tryParse(isoDate);
    if (date == null) return isoDate;
    return DateFormat('EEEE, MMMM d, yyyy').format(date);
  }

  /// "2026-07-04" → "Jul 4, 2026"
  static String formatSeminarDate(String isoDate) {
    final date = DateTime.tryParse(isoDate);
    if (date == null) return isoDate;
    return DateFormat('MMM d, yyyy').format(date);
  }

  /// ISO datetime → "9:00 AM" / "Yesterday" / "June 25"
  static String formatNotificationTime(String isoDate) {
    final date = DateTime.tryParse(isoDate);
    if (date == null) return isoDate;
    final now = DateTime.now();
    final diff = now.difference(date);
    if (diff.inDays == 0) return DateFormat('h:mm a').format(date);
    if (diff.inDays == 1) return 'Yesterday';
    if (diff.inDays < 7) return DateFormat('EEEE').format(date);
    return DateFormat('MMM d').format(date);
  }

  /// "08:00:00" → "8:00 AM"
  static String formatTime(String timeStr) {
    try {
      final parts = timeStr.split(':');
      final hour = int.parse(parts[0]);
      final minute = int.parse(parts[1]);
      final dt = DateTime(2000, 1, 1, hour, minute);
      return DateFormat('h:mm a').format(dt);
    } catch (_) {
      return timeStr;
    }
  }

  /// Returns e.g. "July 2026"
  static String currentMonthYear() =>
      DateFormat('MMMM yyyy').format(DateTime.now());

  /// ISO date → "Today" / "Yesterday" / "Jun 30"
  static String relativeDay(String isoDate) {
    final date = DateTime.tryParse(isoDate);
    if (date == null) return isoDate;
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    final target = DateTime(date.year, date.month, date.day);
    final diff = today.difference(target).inDays;
    if (diff == 0) return 'Today';
    if (diff == 1) return 'Yesterday';
    return DateFormat('MMM d').format(date);
  }
}
