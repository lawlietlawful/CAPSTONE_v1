import 'package:flutter_test/flutter_test.dart';
import 'package:teacher_portal/core/utils/password_rules.dart';

void main() {
  test('matches the server rule: 8+ characters with letters and numbers', () {
    expect(PasswordRules.problem('short1'), contains('at least 8 characters'));
    expect(PasswordRules.problem('12345678'), contains('letter'));
    expect(PasswordRules.problem('lettersonly'), contains('number'));
    expect(PasswordRules.problem('Teacher2026'), isNull);
    expect(PasswordRules.problem('with space 1'), isNull);
  });
}
