import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../config/app_config.dart';

class ApiClient {
  final Dio _dio;
  final FlutterSecureStorage _storage;

  ApiClient()
      : _dio = Dio(BaseOptions(
          baseUrl: AppConfig.apiBaseUrl,
          connectTimeout: const Duration(seconds: 30),
          receiveTimeout: const Duration(seconds: 30),
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
          },
        )),
        _storage = const FlutterSecureStorage() {
    _setupInterceptors();
  }

  void _setupInterceptors() {
    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await _storage.read(key: 'auth_token');
          if (token != null) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          return handler.next(options);
        },
        onError: (error, handler) async {
          if (error.response?.statusCode == 401) {
            await _storage.delete(key: 'auth_token');
          }
          if (kDebugMode) {
            debugPrint('API Error: ${error.message}');
          }
          return handler.next(error);
        },
      ),
    );
  }

  Dio get client => _dio;

  // Auth endpoints
  Future<Map<String, dynamic>> getUser() async {
    final response = await _dio.get('/auth/user');
    return response.data;
  }

  Future<void> logout() async {
    await _dio.post('/auth/logout');
    await _storage.delete(key: 'auth_token');
  }

  // Integration endpoints
  Future<List<dynamic>> getIntegrations() async {
    final response = await _dio.get('/integrations');
    return response.data['integrations'];
  }

  Future<void> disconnectIntegration(int integrationId) async {
    await _dio.delete('/integrations/$integrationId');
  }

  Future<void> syncIntegration(int integrationId) async {
    await _dio.post('/integrations/$integrationId/sync');
  }

  // Pull Request endpoints
  Future<Map<String, dynamic>> getPullRequests({
    String? status,
    String? repository,
    String? platform,
    int page = 1,
  }) async {
    final response = await _dio.get('/pull-requests', queryParameters: {
      if (status != null) 'status': status,
      if (repository != null) 'repository': repository,
      if (platform != null) 'platform': platform,
      'page': page,
    });
    return response.data;
  }

  Future<Map<String, dynamic>> getPullRequestDetails(int prId) async {
    final response = await _dio.get('/pull-requests/$prId');
    return response.data;
  }

  Future<Map<String, dynamic>> getReviewedPullRequests({int page = 1}) async {
    final response = await _dio.get('/pull-requests/reviewed', queryParameters: {
      'page': page,
    });
    return response.data;
  }

  // Issue endpoints
  Future<Map<String, dynamic>> getIssues({
    String? status,
    String? type,
    String? priority,
    String? repository,
    String? platform,
    int page = 1,
  }) async {
    final response = await _dio.get('/issues', queryParameters: {
      if (status != null) 'status': status,
      if (type != null) 'type': type,
      if (priority != null) 'priority': priority,
      if (repository != null) 'repository': repository,
      if (platform != null) 'platform': platform,
      'page': page,
    });
    return response.data;
  }

  Future<Map<String, dynamic>> getIssueDetails(int issueId) async {
    final response = await _dio.get('/issues/$issueId');
    return response.data;
  }

  Future<Map<String, dynamic>> getBugIssues({int page = 1}) async {
    final response = await _dio.get('/issues/bugs', queryParameters: {
      'page': page,
    });
    return response.data;
  }

  Future<Map<String, dynamic>> getTaskIssues({int page = 1}) async {
    final response = await _dio.get('/issues/tasks', queryParameters: {
      'page': page,
    });
    return response.data;
  }

  // Dashboard endpoints
  Future<Map<String, dynamic>> getDashboardStats() async {
    final response = await _dio.get('/dashboard/stats');
    return response.data;
  }

  Future<Map<String, dynamic>> getRecentActivity() async {
    final response = await _dio.get('/dashboard/recent');
    return response.data;
  }

  // Token management
  Future<void> saveToken(String token) async {
    await _storage.write(key: 'auth_token', value: token);
  }

  Future<String?> getToken() async {
    return await _storage.read(key: 'auth_token');
  }

  Future<bool> isAuthenticated() async {
    final token = await getToken();
    return token != null;
  }
}
