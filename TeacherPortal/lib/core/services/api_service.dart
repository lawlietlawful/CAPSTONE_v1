import 'dart:convert';
import 'package:http/http.dart' as http;
import '../constants/api_constants.dart';

class ApiService {
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
    final response = await http.get(_uri(endpoint), headers: _headers);
    return _parse(response);
  }

  static Future<Map<String, dynamic>> post(
    String endpoint, {
    Map<String, dynamic>? body,
  }) async {
    final response = await http.post(
      _uri(endpoint),
      headers: _headers,
      body: body != null ? jsonEncode(body) : null,
    );
    return _parse(response);
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
