import 'package:flutter/widgets.dart';
import 'package:provider/provider.dart';

import 'notification_provider.dart';
import 'seminar_provider.dart';
import 'settings_provider.dart';
import 'student_provider.dart';

/// Forgets everything loaded for the signed-in student — on sign-out, when a
/// session expires, and when a new one starts — so the next person on this
/// phone never sees any of it.
void resetStudentData(BuildContext context) {
  context.read<StudentProvider>().reset();
  context.read<SeminarProvider>().reset();
  context.read<NotificationProvider>().reset();
  context.read<SettingsProvider>().reload();
}
