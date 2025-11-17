import 'package:bugradar_mobile/features/auth/providers/auth_provider.dart';
import 'package:bugradar_mobile/main.dart';
import 'package:bugradar_mobile/models/user.dart';
import 'package:bugradar_mobile/services/auth_service.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('BugRadar app renders login screen by default', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          authNotifierProvider.overrideWith(
            (ref) => _FakeAuthNotifier(),
          ),
        ],
        child: const BugRadarApp(),
      ),
    );

    await tester.pumpAndSettle();

    expect(find.text('BugRadar'), findsOneWidget);
    expect(find.text('Continue with Google'), findsOneWidget);
  });
}

class _FakeAuthNotifier extends AuthNotifier {
  _FakeAuthNotifier()
      : super(_FakeAuthService(), autoInitialize: false) {
    state = state.copyWith(isLoading: false, clearError: true);
  }
}

class _FakeAuthService extends AuthService {
  _FakeAuthService()
      : super(
          secureStorage: const FlutterSecureStorage(),
        );

  @override
  Future<void> persistToken(String token) async {}

  @override
  Future<String?> getSavedToken() async => null;

  @override
  Future<void> clearToken() async {}

  @override
  Future<User?> fetchCurrentUser() async => null;
}
