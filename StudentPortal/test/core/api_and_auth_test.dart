import 'dart:async';
import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:student_portal/core/services/api_service.dart';
import 'package:student_portal/core/services/auth_service.dart';
import 'package:student_portal/core/utils/password_rules.dart';

Map<String, dynamic> _signedIn() => {
      'token': 'tok-123',
      'student': {'id': 7, 'first_name': 'Ana', 'last_name': 'Cruz', 'grade_level': '1st Year', 'section': 'A'},
    };

void main() {
  late int unauthorizedCalls;

  setUp(() {
    SharedPreferences.setMockInitialValues({});
    FlutterSecureStorage.setMockInitialValues({});
    ApiService.clearToken();
    ApiService.timeout = const Duration(seconds: 15);
    unauthorizedCalls = 0;
    ApiService.onUnauthorized = () => unauthorizedCalls++;
  });

  tearDown(() => ApiService.client = http.Client());

  test('a wrong password at sign-in is not treated as an expired session', () async {
    ApiService.client = MockClient((_) async => http.Response(
        jsonEncode({'message': 'Invalid Student ID or password.'}), 401));

    await expectLater(AuthService.login('2026-0001', 'wrong'),
        throwsA(isA<ApiException>().having((e) => e.message, 'message', 'Invalid Student ID or password.')));
    expect(unauthorizedCalls, 0);
  });

  test('a 401 while signed in ends the session once', () async {
    ApiService.setToken('expired');
    ApiService.client = MockClient((_) async => http.Response('{"message":"Unauthenticated."}', 401));

    await expectLater(ApiService.get('/student/profile'), throwsA(isA<ApiException>()));
    await expectLater(ApiService.get('/student/seminars'), throwsA(isA<ApiException>()));
    expect(unauthorizedCalls, 1);
  });

  test('a server that never answers times out with a plain message', () async {
    ApiService.timeout = const Duration(milliseconds: 50);
    ApiService.client = MockClient((_) => Completer<http.Response>().future);

    await expectLater(ApiService.get('/student/profile'),
        throwsA(isA<ApiException>().having((e) => e.message, 'message', contains('taking too long'))));
  });

  test('an unreachable server or an error page gets a plain message', () async {
    ApiService.client = MockClient((_) async => throw http.ClientException('refused'));
    await expectLater(ApiService.get('/student/profile'),
        throwsA(isA<ApiException>().having((e) => e.isNetworkError, 'network', isTrue)));

    ApiService.client = MockClient((_) async => http.Response('<html>500</html>', 500));
    await expectLater(ApiService.get('/student/profile'),
        throwsA(isA<ApiException>().having((e) => e.message, 'message', contains('error 500'))));
  });

  test('a sign-in pause carries the seconds to wait', () async {
    ApiService.client = MockClient((_) async => http.Response(
        jsonEncode({'message': 'Too many sign-in attempts. Please try again in 1 minute.', 'retry_after': 60}), 429));

    await expectLater(AuthService.login('2026-0001', 'x'),
        throwsA(isA<ApiException>().having((e) => e.retryAfter, 'retryAfter', 60)));
  });

  test('the password is sent exactly as typed and the session key goes to secure storage', () async {
    late Map<String, dynamic> sent;
    ApiService.client = MockClient((request) async {
      sent = jsonDecode(request.body) as Map<String, dynamic>;
      return http.Response(jsonEncode(_signedIn()), 200);
    });

    await AuthService.login('  2026-0001 ', ' pass word 1 ');

    expect(sent['email'], '2026-0001');
    expect(sent['password'], ' pass word 1 ');
    expect(await AuthService.getSavedToken(), 'tok-123');
    final prefs = await SharedPreferences.getInstance();
    expect(prefs.getString('auth_token'), isNull, reason: 'not in ordinary preferences');
  });

  test('signing out forgets the session on this phone', () async {
    ApiService.client = MockClient((_) async => http.Response(jsonEncode(_signedIn()), 200));
    await AuthService.login('2026-0001', 'Student2026');

    await AuthService.logout();

    expect(await AuthService.getSavedToken(), isNull);
    expect(ApiService.hasToken, isFalse);
  });

  test('the password rule matches the server', () {
    expect(PasswordRules.problem('short1'), contains('8 characters'));
    expect(PasswordRules.problem('lettersonly'), contains('number'));
    expect(PasswordRules.problem('12345678'), contains('letter'));
    expect(PasswordRules.problem('Student2026'), isNull);
  });
}
