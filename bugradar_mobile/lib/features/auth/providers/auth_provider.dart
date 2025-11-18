import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:uni_links/uni_links.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../config/app_config.dart';
import '../../../services/auth_service.dart';
import '../state/auth_state.dart';

final authServiceProvider = Provider<AuthService>((ref) => AuthService());

final authNotifierProvider =
    StateNotifierProvider<AuthNotifier, AuthState>((ref) {
  final service = ref.watch(authServiceProvider);
  final notifier = AuthNotifier(service);
  ref.onDispose(notifier.dispose);
  return notifier;
});

class AuthNotifier extends StateNotifier<AuthState> {
  AuthNotifier(
    this._authService, {
    bool autoInitialize = true,
  }) : super(AuthState.initial()) {
    if (autoInitialize) {
      _init();
    }
  }

  final AuthService _authService;
  StreamSubscription<Uri?>? _linkSubscription;

  Future<void> _init() async {
    try {
      final token = await _authService.getSavedToken();
      if (token != null) {
        await _loadUser();
      } else {
        state = state.copyWith(isLoading: false);
      }

      _linkSubscription = uriLinkStream.listen(
        _handleIncomingUri,
        onError: (Object err) {
          if (kDebugMode) {
            debugPrint('Deep link error: $err');
          }
          state = state.copyWith(
            error: 'Failed to process login link',
            isLoading: false,
          );
        },
      );

      final initialUri = await getInitialUri();
      if (initialUri != null) {
        _handleIncomingUri(initialUri);
      }
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: 'Failed to initialize auth: $e',
      );
    }
  }

  Future<void> _handleIncomingUri(Uri? uri) async {
    if (uri == null) return;
    if (uri.scheme != AppConfig.appScheme) return;

    final success = uri.queryParameters['success'] ?? 'false';
    final token = uri.queryParameters['token'];
    final errorMessage =
        uri.queryParameters['error'] ?? 'Authentication cancelled';

    if (success == 'true' && token != null) {
      state = state.copyWith(isLoading: true, clearError: true);
      await _authService.persistToken(token);
      await _loadUser();
    } else {
      state = state.copyWith(
        error: errorMessage,
        isLoading: false,
      );
    }
  }

  Future<void> _loadUser() async {
    try {
      final user = await _authService.fetchCurrentUser();
      state = state.copyWith(
        user: user,
        token: await _authService.getSavedToken(),
        isLoading: false,
        clearError: true,
      );
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: 'Failed to load user profile',
      );
    }
  }

  Future<void> launchOAuth(String provider) async {
    final uri = AppConfig.oauthUrl(provider);
    if (!await launchUrl(uri, mode: LaunchMode.externalApplication)) {
      state = state.copyWith(error: 'Could not open $provider login');
    }
  }

  Future<void> logout() async {
    await _authService.clearToken();
    state = AuthState.initial().copyWith(isLoading: false);
  }

  @override
  void dispose() {
    _linkSubscription?.cancel();
    super.dispose();
  }
}

