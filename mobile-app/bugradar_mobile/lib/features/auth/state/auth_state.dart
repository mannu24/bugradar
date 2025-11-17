import '../../../models/user.dart';

class AuthState {
  final bool isLoading;
  final User? user;
  final String? token;
  final String? error;

  const AuthState({
    required this.isLoading,
    this.user,
    this.token,
    this.error,
  });

  factory AuthState.initial() => const AuthState(isLoading: true);

  bool get isAuthenticated => user != null && token != null;

  AuthState copyWith({
    bool? isLoading,
    User? user,
    String? token,
    String? error,
    bool clearError = false,
  }) {
    return AuthState(
      isLoading: isLoading ?? this.isLoading,
      user: user ?? this.user,
      token: token ?? this.token,
      error: clearError ? null : (error ?? this.error),
    );
  }
}

