import 'dart:convert';
import 'package:http/http.dart' as http;
import '../constants/api_constants.dart';
import 'mock_data.dart';

class ApiService {
  static const _useMockData = false;
  static String? _token;

  /// Called once when the API returns 401 Unauthorized (expired/invalid token).
  /// Wired in main.dart to clear the session and redirect to the login screen.
  static void Function()? onUnauthorized;
  static bool _handlingUnauthorized = false;

  static void setToken(String token) {
    _token = token;
    _handlingUnauthorized = false;
  }

  static void clearToken() => _token = null;

  static Map<String, String> get _headers => {
        'Authorization': 'Bearer ${_token ?? ''}',
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      };

  static Uri _uri(String endpoint) =>
      Uri.parse('${ApiConstants.baseUrl}$endpoint');

  static Future<Map<String, dynamic>> get(String endpoint) async {
    if (_useMockData) {
      return MockData.response(endpoint);
    }

    final response = await http.get(_uri(endpoint), headers: _headers);
    return _parse(response);
  }

  static Future<Map<String, dynamic>> post(
    String endpoint, {
    Map<String, dynamic>? body,
  }) async {
    if (_useMockData) {
      return _mockPost(endpoint, body: body);
    }

    final response = await http.post(
      _uri(endpoint),
      headers: _headers,
      body: body != null ? jsonEncode(body) : null,
    );
    return _parse(response);
  }

  static Future<Map<String, dynamic>> _mockPost(
    String endpoint, {
    Map<String, dynamic>? body,
  }) async {
    await Future.delayed(const Duration(milliseconds: 700));

    if (endpoint == ApiConstants.login) {
      final id = body?['email']?.toString().trim().toLowerCase() ?? '';
      final password = body?['password']?.toString().trim() ?? '';
      final validId = id == MockData.studentId ||
          id == MockData.student['email'].toString().toLowerCase();

      if (validId && password == MockData.password) {
        return {'token': MockData.mockToken, 'student': MockData.student};
      }

      throw const ApiException(
        statusCode: 401,
        message: 'Invalid Student ID or password.',
      );
    }

    if (endpoint == ApiConstants.logout) {
      return {'message': 'Logged out', 'success': true};
    }

    return MockData.response(endpoint);
  }

  static Map<String, dynamic> _parse(http.Response response) {
    final data = jsonDecode(response.body);
    if (response.statusCode >= 200 && response.statusCode < 300) {
      return data as Map<String, dynamic>;
    }
    // Session expired — trigger a single global logout, even if several
    // parallel requests all come back 401 at once.
    if (response.statusCode == 401 &&
        !_handlingUnauthorized &&
        onUnauthorized != null) {
      _handlingUnauthorized = true;
      onUnauthorized!();
    }
    throw ApiException(
      statusCode: response.statusCode,
      message: (data as Map<String, dynamic>)['message'] as String? ??
          'An error occurred',
    );
  }
}

class ApiException implements Exception {
  final int statusCode;
  final String message;

  const ApiException({required this.statusCode, required this.message});

  @override
  String toString() => 'ApiException($statusCode): $message';
}
