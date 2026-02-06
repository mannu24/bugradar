import 'dart:io';

class AppConfig {
  // Your machine's local IP address
  static const String _machineIp = '192.168.1.3';
  static const int _port = 8006;

  // API Base URL (for API calls from the app)
  static String get apiBaseUrl {
    // Use machine IP for all platforms when testing on physical device
    return 'http://$_machineIp:$_port/api';
  }

  // Backend Base URL (for OAuth browser redirects)
  static String get backendBaseUrl {
    return 'http://$_machineIp:$_port';
  }

  // OAuth Deep Link Scheme
  static const String deepLinkScheme = 'bugradar';
}
