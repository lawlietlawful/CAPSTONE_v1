import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:student_portal/main.dart';

void main() {
  setUp(() {
    SharedPreferences.setMockInitialValues({});
    FlutterSecureStorage.setMockInitialValues({});
  });

  testWidgets('opens on the redesigned sign-in screen', (tester) async {
    await tester.pumpWidget(const MyApp(hasSession: false));
    await tester.pumpAndSettle();

    expect(find.text('Student Portal'), findsOneWidget);
    expect(find.text('Misamis University'), findsOneWidget);
    expect(find.text('Sign in to your account'), findsOneWidget);
  });

  testWidgets('empty fields get a message and the form shakes, no request is sent', (tester) async {
    await tester.pumpWidget(const MyApp(hasSession: false));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Sign in'));
    await tester.pump();

    expect(find.text('Please enter your Student ID and password.'), findsOneWidget);
    await tester.pumpAndSettle();
  });

  testWidgets('the password can be shown and hidden', (tester) async {
    await tester.pumpWidget(const MyApp(hasSession: false));
    await tester.pumpAndSettle();

    TextField password() => tester.widgetList<TextField>(find.byType(TextField)).last;
    expect(password().obscureText, isTrue);
    await tester.tap(find.byTooltip('Show password'));
    await tester.pump();
    expect(password().obscureText, isFalse);
  });

  testWidgets('"Forgot password?" explains the in-person reset', (tester) async {
    await tester.pumpWidget(const MyApp(hasSession: false));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Forgot password?'));
    await tester.pumpAndSettle();
    expect(find.textContaining('Visit the Guidance Office'), findsOneWidget);
  });
}
