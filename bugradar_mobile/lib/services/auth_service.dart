import 'dart:async';
import 'package:flutter/foundation.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:app_links/app_links.dart';

import '../config/app_config.dart';
import 'api_client.dart';
import '../models/user.dart';

class AuthService {
  final ApiClient _apiClient;
  final AppLinks _appLinks = AppLinks();
  StreamSubscription? _linkSubscription;

  AuthService(this._apiClient);

  /// Launch OAuth flow for given provider
  Future<void> launchOAuth(String provider) async {
    final url = Uri.parse('${AppConfig.backendBaseUrl}/api/auth/$provider');
    
    if (kDebugMode) {
      debugPrint('Launching OAuth URL: $url');
    }
    
    if (await canLaunchUrl(url)) {
      await launchUrl(url, mode: LaunchMode.externalApplication);
    } else {
      throw Exception('Could not launch OAuth URL: $url');
    }
  }

  /// Listen for OAuth callback deep link
  Stream<String?> listenForOAuthCallback() {
    return _appLinks.uriLinkStream.map((uri) {
      if (uri.queryParameters.containsKey('token')) {
        return uri.queryParameters['token'];
      }
      return null;
    });
  }

  /// Handle OAuth callback with token
  Future<User> handleOAuthCallback(String token) async {
    await _apiClient.saveToken(token);
    final userData = await _apiClient.getUser();
    return User.fromJson(userData['user']);
  }

  /// Check if user is authenticated
  Future<bool> isAuthenticated() async {
    return await _apiClient.isAuthenticated();
  }

  /// Get current user
  Future<User?> getCurrentUser() async {
    try {
      if (!await isAuthenticated()) {
        return null;
      }
      final userData = await _apiClient.getUser();
      return User.fromJson(userData['user']);
    } catch (e) {
      if (kDebugMode) {
        debugPrint('Error getting current user: $e');
      }
      return null;
    }
  }

  /// Logout
  Future<void> logout() async {
    try {
      await _apiClient.logout();
    } catch (e) {
      if (kDebugMode) {
        debugPrint('Error during logout: $e');
      }
    }
  }

  /// Development bypass - skip OAuth (ONLY FOR DEVELOPMENT!)
  Future<User> devLogin() async {
    try {
      final response = await _apiClient.client.get('/auth/dev-login');
      final token = response.data['token'] as String;
      await _apiClient.saveToken(token);
      return User.fromJson(response.data['user']);
    } catch (e) {
      if (kDebugMode) {
        debugPrint('Dev login error: $e');
      }
      rethrow;
    }
  }

  /// Dispose resources
  void dispose() {
    _linkSubscription?.cancel();
  }
}
