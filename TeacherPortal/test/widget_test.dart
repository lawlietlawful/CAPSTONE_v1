import 'package:flutter_test/flutter_test.dart';

import 'package:teacher_portal/main.dart';

void main() {
  testWidgets('App boots to the login screen when unauthenticated',
      (WidgetTester tester) async {
    await tester.pumpWidget(const MyApp(hasSession: false));
    await tester.pump();

    expect(find.text('Teacher Portal'), findsOneWidget);
    expect(find.text('Sign in'), findsOneWidget);
    expect(find.text('Email'), findsOneWidget);
    expect(find.text('Password'), findsOneWidget);
  });
}
