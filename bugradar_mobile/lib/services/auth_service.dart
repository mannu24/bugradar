import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../models/user.dart';
import 'api_client.dart';

class AuthService {
  AuthService({
    FlutterSecureStorage? secureStorage,
    ApiClient? apiClient,
  })  : _storage = secureStorage ?? const FlutterSecureStorage(),
        _apiClient = apiClient ?? ApiClient();

  static const _tokenKey = 'auth_token';

  final FlutterSecureStorage _storage;
  final ApiClient _apiClient;

  ApiClient get apiClient => _apiClient;

  Future<void> persistToken(String token) async {
    await _storage.write(key: _tokenKey, value: token);
    _apiClient.setToken(token);
  }

  Future<void> clearToken() async {
    await _storage.delete(key: _tokenKey);
    _apiClient.setToken(null);
  }

  Future<String?> getSavedToken() async {
    final token = await _storage.read(key: _tokenKey);
    if (token != null) {
      _apiClient.setToken(token);
    }
    return token;
  }

  Future<User?> fetchCurrentUser() async {
    final response = await _apiClient.get<Map<String, dynamic>>('/api/auth/user');
    final data = response.data?['user'] as Map<String, dynamic>?;
    if (data == null) return null;
    return User.fromJson(data);
  }
}

