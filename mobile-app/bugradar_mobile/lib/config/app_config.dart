class AppConfig {
  const AppConfig._();

  /// Update this via --dart-define=API_BASE_URL="https://api.example.com"
  static const String apiBaseUrl =
      String.fromEnvironment('API_BASE_URL', defaultValue: 'http://10.0.2.2:8000');

  static const String appScheme = 'bugradar';
  static const String authHost = 'auth';

  static String get authRedirectUri => '$appScheme://$authHost';

  static Uri oauthUrl(String provider) {
    final redirectParam = Uri.encodeComponent(authRedirectUri);
    final normalizedBase = apiBaseUrl.endsWith('/')
        ? apiBaseUrl.substring(0, apiBaseUrl.length - 1)
        : apiBaseUrl;
    return Uri.parse('$normalizedBase/api/auth/$provider?redirect_url=$redirectParam');
  }
}

