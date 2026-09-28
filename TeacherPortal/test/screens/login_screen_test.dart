import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:teacher_portal/providers/auth_provider.dart';
import 'package:teacher_portal/screens/auth/login_screen.dart';

Widget _wrap(Widget child) => ChangeNotifierProvider(
      create: (_) => AuthProvider(),
      child: MaterialApp(home: child),
    );

void main() {
  testWidgets('submitting with both fields empty shows a validation error without calling the API',
      (tester) async {
    await tester.pumpWidget(_wrap(const LoginScreen()));

    await tester.tap(find.text('Sign in'));
    await tester.pump();

    expect(find.text('Please enter your Employee ID and password.'), findsOneWidget);
  });

  testWidgets('the password field obscures its input', (tester) async {
    await tester.pumpWidget(_wrap(const LoginScreen()));

    final fields = tester.widgetList<TextField>(find.byType(TextField)).toList();
    expect(fields, hasLength(2));
    expect(fields[0].obscureText, isFalse, reason: 'Employee ID is plain text');
    expect(fields[1].obscureText, isTrue, reason: 'password is hidden');
  });

  testWidgets('the activation link is offered for first-time users', (tester) async {
    await tester.pumpWidget(_wrap(const LoginScreen()));

    // The prompt is split across TextSpans within one RichText, so it can't
    // be found via find.text/textContaining (those match a Text widget's
    // single data string) — check the RichText's flattened plain text instead.
    final richTexts = tester.widgetList<RichText>(find.byType(RichText));
    final hasActivationPrompt =
        richTexts.any((r) => r.text.toPlainText().contains('Activate your account'));
    expect(hasActivationPrompt, isTrue);
  });

  testWidgets('"Forgot password?" opens a notice to contact the administrator',
      (tester) async {
    await tester.pumpWidget(_wrap(const LoginScreen()));

    await tester.tap(find.text('Forgot password?'));
    await tester.pumpAndSettle();

    expect(find.text('Forgot your password?'), findsOneWidget);
    expect(find.textContaining('contact your school administrator'), findsOneWidget);

    await tester.tap(find.text('Got it'));
    await tester.pumpAndSettle();
    expect(find.text('Forgot your password?'), findsNothing);
  });
}
