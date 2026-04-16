import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:mobile_ver/core/config/api_config.dart';
import 'package:mobile_ver/core/storage/local_storage.dart';

class ApiClient {
  static const Duration _timeout = Duration(seconds: 20);
  static String get baseUrl => ApiConfig.baseUrl;

  static Future<Map<String, String>> _getHeaders() async {
    final token = await LocalStorage.getToken();
    return {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      if (token != null) 'Authorization': 'Bearer $token',
    };
  }

  static Future<http.Response> get(String endpoint) async {
    final headers = await _getHeaders();
    return http.get(ApiConfig.endpointUri(endpoint), headers: headers).timeout(_timeout);
  }

  static Future<http.Response> post(String endpoint, Map<String, dynamic> body) async {
    final headers = await _getHeaders();
    return http.post(
      ApiConfig.endpointUri(endpoint),
      headers: headers,
      body: jsonEncode(body),
    ).timeout(_timeout);
  }

  static Future<http.Response> put(String endpoint, Map<String, dynamic> body) async {
    final headers = await _getHeaders();
    return http.put(
      ApiConfig.endpointUri(endpoint),
      headers: headers,
      body: jsonEncode(body),
    ).timeout(_timeout);
  }

  static Future<http.Response> patch(String endpoint, Map<String, dynamic> body) async {
    final headers = await _getHeaders();
    return http.patch(
      ApiConfig.endpointUri(endpoint),
      headers: headers,
      body: jsonEncode(body),
    ).timeout(_timeout);
  }

  static Future<http.Response> delete(String endpoint) async {
    final headers = await _getHeaders();
    return http.delete(ApiConfig.endpointUri(endpoint), headers: headers).timeout(_timeout);
  }
}
