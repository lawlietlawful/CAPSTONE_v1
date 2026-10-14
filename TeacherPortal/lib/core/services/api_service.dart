import 'dart:async';
import 'dart:convert';
import 'package:http/http.dart' as http;
import '../constants/api_constants.dart';

class ApiService {
  static String? _token;

  /// How long to wait for the server before giving up, so a hung request
  /// shows a message instead of a spinner that never stops.
  static Duration timeout = const Duration(seconds: 15);

  /// Called once when the API returns 401 Unauthorized (expired/invalid token).
  /// Wired in main.dart to clear the session and redirect to the login screen.
  static void Function()? onUnauthorized;
  static bool _handlingUnauthorized = false;

  /// The HTTP client; replaceable in tests.
  static http.Client client = http.Client();

  static void setToken(String token) {
    _token = token;
    _handlingUnauthorized = false;
  }

  static void clearToken() => _token = null;

  static bool get hasToken => _token != null && _token!.isNotEmpty;

  static Map<String, String> get _headers => {
        if (hasToken) 'Authorization': 'Bearer $_token',
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      };

  static Uri _uri(String endpoint) =>
      Uri.parse('${ApiConstants.baseUrl}$endpoint');

  static Future<Map<String, dynamic>> get(String endpoint) =>
      _send(() => client.get(_uri(endpoint), headers: _headers));

  static Future<Map<String, dynamic>> post(
    String endpoint, {
    Map<String, dynamic>? body,
  }) =>
      _send(() => client.post(
            _uri(endpoint),
            headers: _headers,
            body: body != null ? jsonEncode(body) : null,
          ));

  static Future<Map<String, dynamic>> _send(
    Future<http.Response> Function() request,
  ) async {
    // A request made while signed in; a 401 to one of these means the session
    // ended. A 401 to the sign-in request itself is just a wrong password.
    final hadSession = hasToken;
    final http.Response response;
    try {
      response = await request().timeout(timeout);
    } on TimeoutException {
      throw const ApiException(
        statusCode: 0,
        message: 'The server is taking too long to respond. Please try again.',
      );
    } on http.ClientException {
      throw const ApiException(
        statusCode: 0,
        message:
            "Can't reach the server. Check your internet connection and try again.",
      );
    }
    return _parse(response, hadSession: hadSession);
  }

  static Map<String, dynamic> _parse(
    http.Response response, {
    required bool hadSession,
  }) {
    final Object? data;
    try {
      data = response.body.isEmpty ? null : jsonDecode(response.body);
    } on FormatException {
      throw ApiException(
        statusCode: response.statusCode,
        message:
            'Something went wrong on the server (error ${response.statusCode}). Please try again.',
      );
    }

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return data is Map<String, dynamic> ? data : <String, dynamic>{};
    }
    // Session expired — trigger a single global logout, even if several
    // parallel requests all come back 401 at once.
    if (response.statusCode == 401 &&
        hadSession &&
        !_handlingUnauthorized &&
        onUnauthorized != null) {
      _handlingUnauthorized = true;
      onUnauthorized!();
    }
    final message = data is Map<String, dynamic> ? data['message'] : null;
    throw ApiException(
      statusCode: response.statusCode,
      data: data is Map<String, dynamic> ? data : null,
      message: message is String && message.isNotEmpty
          ? message
          : 'Something went wrong (error ${response.statusCode}). Please try again.',
    );
  }
}

class ApiException implements Exception {
  final int statusCode;
  final String message;

  /// The error response body, for errors that carry more than a message
  /// (e.g. the 409 duplicate-referral warning's `existing` referral).
  final Map<String, dynamic>? data;

  const ApiException({required this.statusCode, required this.message, this.data});

  /// The request never got an answer (offline, unreachable, timed out).
  bool get isNetworkError => statusCode == 0;

  @override
  String toString() => 'ApiException($statusCode): $message';
}
