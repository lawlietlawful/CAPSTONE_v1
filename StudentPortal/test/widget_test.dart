import 'package:flutter_test/flutter_test.dart';
import 'package:student_portal/main.dart';

void main() {
  testWidgets('App smoke test – renders without crashing',
      (WidgetTester tester) async {
    await tester.pumpWidget(const MyApp(hasSession: false));
    await tester.pump();
  });
}
