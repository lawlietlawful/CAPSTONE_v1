/// The password rule the server enforces (Laravel Password::defaults():
/// at least 8 characters, with letters and numbers), checked here first so
/// the teacher gets the message before a round trip.
class PasswordRules {
  static const String summary =
      'At least 8 characters, with both letters and numbers.';

  /// What's wrong with [password], or null if it's acceptable.
  static String? problem(String password) {
    if (password.length < 8) {
      return 'Password must be at least 8 characters.';
    }
    if (!RegExp(r'[A-Za-z]').hasMatch(password)) {
      return 'Password must contain at least one letter.';
    }
    if (!RegExp(r'[0-9]').hasMatch(password)) {
      return 'Password must contain at least one number.';
    }
    return null;
  }
}
