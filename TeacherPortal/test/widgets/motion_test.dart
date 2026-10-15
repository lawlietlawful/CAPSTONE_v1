import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:teacher_portal/widgets/common/app_nav_bar.dart';
import 'package:teacher_portal/widgets/premium/motion.dart';
import 'package:teacher_portal/widgets/premium/risk_gauge.dart';
import 'package:teacher_portal/widgets/premium/success_check.dart';

Widget _app(Widget child, {bool reduceMotion = false}) => MaterialApp(
      home: MediaQuery(
        data: MediaQueryData(disableAnimations: reduceMotion),
        child: Scaffold(body: Center(child: child)),
      ),
    );

double _opacity(WidgetTester tester) =>
    tester.widget<Opacity>(find.descendant(of: find.byType(FadeSlideIn), matching: find.byType(Opacity))).opacity;

void main() {
  testWidgets('FadeSlideIn fades its child in, later items after earlier ones', (tester) async {
    await tester.pumpWidget(_app(const Column(children: [
      FadeSlideIn(index: 0, child: Text('first')),
    ])));
    expect(_opacity(tester), 0);

    await tester.pump(const Duration(milliseconds: 160));
    expect(_opacity(tester), inExclusiveRange(0, 1));

    await tester.pumpAndSettle();
    expect(_opacity(tester), 1);

    // A later item hasn't started when the first has.
    await tester.pumpWidget(_app(const FadeSlideIn(index: 4, child: Text('fifth'))));
    await tester.pump(const Duration(milliseconds: 150));
    expect(_opacity(tester), 0, reason: 'still waiting for its turn');
    await tester.pumpAndSettle();
    expect(_opacity(tester), 1);
  });

  testWidgets('CountUp counts up to its value', (tester) async {
    await tester.pumpWidget(_app(const CountUp(value: 40)));
    expect(find.text('0'), findsOneWidget);

    await tester.pump(const Duration(milliseconds: 200));
    final mid = int.parse(tester.widget<Text>(find.byType(Text)).data!);
    expect(mid, inExclusiveRange(0, 40));

    await tester.pumpAndSettle();
    expect(find.text('40'), findsOneWidget);
  });

  testWidgets('with "reduce motion" on, everything is in place at once', (tester) async {
    await tester.pumpWidget(_app(
      const Column(children: [FadeSlideIn(index: 3, child: Text('x')), CountUp(value: 12)]),
      reduceMotion: true,
    ));
    expect(_opacity(tester), 1);
    expect(find.text('12'), findsOneWidget);
  });

  testWidgets('Pressable presses in while held and calls onTap', (tester) async {
    var taps = 0;
    await tester.pumpWidget(_app(Pressable(
      onTap: () => taps++,
      child: const SizedBox(width: 120, height: 60, child: Text('card')),
    )));
    double scale() => tester.widget<AnimatedScale>(find.byType(AnimatedScale)).scale;

    final gesture = await tester.startGesture(tester.getCenter(find.text('card')));
    await tester.pump();
    expect(scale(), lessThan(1));
    await gesture.up();
    await tester.pumpAndSettle();
    expect(scale(), 1);
    expect(taps, 1);
  });

  testWidgets('the risk gauge sweeps up to the score', (tester) async {
    await tester.pumpWidget(_app(const RiskGauge(score: 51, color: Colors.orange, label: 'Moderate risk')));
    expect(find.text('0'), findsOneWidget);
    await tester.pump(const Duration(milliseconds: 300));
    expect(find.text('51'), findsNothing, reason: 'still sweeping');
    await tester.pumpAndSettle();
    expect(find.text('51'), findsOneWidget);
    expect(find.text('Moderate risk · out of 100'), findsOneWidget);
  });

  testWidgets('the success check pops in', (tester) async {
    await tester.pumpWidget(_app(const SuccessCheck(size: 40)));
    double scale() => tester.widget<Transform>(find.descendant(of: find.byType(SuccessCheck), matching: find.byType(Transform))).transform.storage[0]; // x scale
    expect(scale(), lessThan(0.2));
    await tester.pumpAndSettle();
    expect(scale(), closeTo(1, 0.001));
  });

  testWidgets('the bottom bar highlight slides to the tapped tab', (tester) async {
    var current = 0;
    await tester.pumpWidget(StatefulBuilder(
      builder: (context, setState) => _app(Align(
        alignment: Alignment.bottomCenter,
        child: SizedBox(width: 390, child: AppNavBar(currentIndex: current, onTap: (i) => setState(() => current = i))),
      )),
    ));
    double pillLeft() => tester.widget<AnimatedPositioned>(find.byType(AnimatedPositioned)).left!;
    final start = pillLeft();

    await tester.tap(find.text('Reports'));
    await tester.pumpAndSettle();
    expect(current, 3);
    expect(pillLeft(), greaterThan(start + 200));
  });
}
