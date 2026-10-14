import 'dart:async';
import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:teacher_portal/core/services/api_service.dart';
import 'package:teacher_portal/core/services/auth_service.dart';

void main() {
  late int unauthorizedCalls;

  setUp(() {
    SharedPreferences.setMockInitialValues({});
    ApiService.clearToken();
    ApiService.timeout = const Duration(seconds: 15);
    unauthorizedCalls = 0;
    ApiService.onUnauthorized = () => unauthorizedCalls++;
  });

  tearDown(() => ApiService.client = http.Client());

  group('401 responses', () {
    test('a wrong password at sign-in is not treated as an expired session', () async {
      ApiService.client = MockClient((_) async => http.Response(
            jsonEncode({'message': 'Invalid Employee ID or password.'}), 401));

      await expectLater(
        ApiService.post('/teacher/login', body: {'email': 'T-1', 'password': 'x'}),
        throwsA(isA<ApiException>().having((e) => e.message, 'message', 'Invalid Employee ID or password.')),
      );
      expect(unauthorizedCalls, 0, reason: 'the login screen must not be reloaded');
    });

    test('a 401 while signed in ends the session once', () async {
      ApiService.setToken('expired-token');
      ApiService.client = MockClient((_) async => http.Response('{"message":"Unauthenticated."}', 401));

      await expectLater(ApiService.get('/teacher/dashboard'), throwsA(isA<ApiException>()));
      await expectLater(ApiService.get('/teacher/roster'), throwsA(isA<ApiException>()));
      expect(unauthorizedCalls, 1);
    });
  });

  group('server trouble gets a plain message', () {
    test('a server that never answers times out', () async {
      ApiService.timeout = const Duration(milliseconds: 50);
      ApiService.client = MockClient((_) => Completer<http.Response>().future);

      await expectLater(
        ApiService.get('/teacher/dashboard'),
        throwsA(isA<ApiException>().having((e) => e.message, 'message', contains('taking too long'))),
      );
    });

    test('an unreachable server', () async {
      ApiService.client = MockClient((_) async => throw http.ClientException('Connection refused'));

      await expectLater(
        ApiService.get('/teacher/dashboard'),
        throwsA(isA<ApiException>().having((e) => e.message, 'message', contains("Can't reach the server"))),
      );
    });

    test('an error page instead of JSON', () async {
      ApiService.client = MockClient((_) async => http.Response('<html>Server Error</html>', 500));

      await expectLater(
        ApiService.get('/teacher/dashboard'),
        throwsA(isA<ApiException>().having((e) => e.message, 'message', contains('error 500'))),
      );
    });

    test('a too-many-attempts pause shows the server message', () async {
      ApiService.client = MockClient((_) async => http.Response(
            jsonEncode({'message': 'Too many sign-in attempts. Please try again in 1 minute.', 'retry_after': 60}), 429));

      await expectLater(
        ApiService.post('/teacher/login'),
        throwsA(isA<ApiException>().having((e) => e.message, 'message', contains('try again in 1 minute'))),
      );
    });
  });

  test('requests carry the token only when signed in', () async {
    final seen = <String?>[];
    ApiService.client = MockClient((request) async {
      seen.add(request.headers['Authorization']);
      return http.Response('{}', 200);
    });

    await ApiService.get('/teacher/dashboard');
    ApiService.setToken('abc');
    await ApiService.get('/teacher/dashboard');

    expect(seen, [null, 'Bearer abc']);
  });

  test('the password is sent exactly as typed', () async {
    late Map<String, dynamic> sent;
    ApiService.client = MockClient((request) async {
      sent = jsonDecode(request.body) as Map<String, dynamic>;
      return http.Response(jsonEncode({
        'token': 't',
        'teacher': {'id': 1, 'name': 'Ana Cruz', 'email': null, 'username': 'T-1'},
      }), 200);
    });

    await AuthService.login('  T-1  ', ' pass word 1 ');

    expect(sent['email'], 'T-1');
    expect(sent['password'], ' pass word 1 ');
  });
}
