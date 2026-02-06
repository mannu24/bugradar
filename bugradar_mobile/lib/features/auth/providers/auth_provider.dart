import 'dart:async';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../services/api_client.dart';
import '../../../services/auth_service.dart';
import '../state/auth_state.dart';

final apiClientProvider = Provider<ApiClient>((ref) => ApiClient());

final authServiceProvider = Provider<AuthService>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return AuthService(apiClient);
});

final authNotifierProvider = StateNotifierProvider<AuthNotifier, AuthState>((ref) {
  final authService = ref.watch(authServiceProvider);
  return AuthNotifier(authService);
});

class AuthNotifier extends StateNotifier<AuthState> {
  final AuthService _authService;
  StreamSubscription? _linkSubscription;

  AuthNotifier(this._authService) : super(const AuthState()) {
    _checkAuthStatus();
    _listenForOAuthCallback();
  }

  Future<void> _checkAuthStatus() async {
    state = state.copyWith(isLoading: true);
    
    try {
      final user = await _authService.getCurrentUser();
      if (user != null) {
        state = state.copyWith(
          isAuthenticated: true,
          user: user,
          isLoading: false,
        );
      } else {
        state = state.copyWith(
          isAuthenticated: false,
          isLoading: false,
        );
      }
    } catch (e) {
      state = state.copyWith(
        isAuthenticated: false,
        isLoading: false,
        error: e.toString(),
      );
    }
  }

  void _listenForOAuthCallback() {
    _linkSubscription = _authService.listenForOAuthCallback().listen((token) {
      if (token != null) {
        _handleOAuthToken(token);
      }
    });
  }

  Future<void> _handleOAuthToken(String token) async {
    state = state.copyWith(isLoading: true, error: null);
    
    try {
      final user = await _authService.handleOAuthCallback(token);
      state = state.copyWith(
        isAuthenticated: true,
        user: user,
        isLoading: false,
      );
    } catch (e) {
      state = state.copyWith(
        isAuthenticated: false,
        isLoading: false,
        error: 'Authentication failed: ${e.toString()}',
      );
    }
  }

  Future<void> launchOAuth(String provider) async {
    state = state.copyWith(error: null);
    
    try {
      await _authService.launchOAuth(provider);
    } catch (e) {
      state = state.copyWith(
        error: 'Failed to launch OAuth: ${e}',
      );
    }
  }

  Future<void> logout() async {
    state = state.copyWith(isLoading: true);
    
    try {
      await _authService.logout();
      state = const AuthState(
        isAuthenticated: false,
        isLoading: false,
      );
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: 'Logout failed: ${e.toString()}',
      );
    }
  }

  // Development bypass - skip OAuth
  Future<void> devLogin() async {
    state = state.copyWith(isLoading: true, error: null);
    
    try {
      final user = await _authService.devLogin();
      state = state.copyWith(
        isAuthenticated: true,
        user: user,
        isLoading: false,
      );
    } catch (e) {
      state = state.copyWith(
        isAuthenticated: false,
        isLoading: false,
        error: 'Dev login failed: ${e.toString()}',
      );
    }
  }

  @override
  void dispose() {
    _linkSubscription?.cancel();
    _authService.dispose();
    super.dispose();
  }
}
