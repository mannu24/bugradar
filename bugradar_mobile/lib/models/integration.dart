class Integration {
  final int id;
  final int userId;
  final String platform;
  final String platformUserId;
  final String platformUsername;
  final bool isActive;
  final DateTime? lastSyncedAt;
  final DateTime createdAt;
  final DateTime updatedAt;

  Integration({
    required this.id,
    required this.userId,
    required this.platform,
    required this.platformUserId,
    required this.platformUsername,
    required this.isActive,
    this.lastSyncedAt,
    required this.createdAt,
    required this.updatedAt,
  });

  factory Integration.fromJson(Map<String, dynamic> json) {
    return Integration(
      id: json['id'] as int,
      userId: json['user_id'] as int,
      platform: json['platform'] as String,
      platformUserId: json['platform_user_id'] as String,
      platformUsername: json['platform_username'] as String,
      isActive: json['is_active'] as bool? ?? true,
      lastSyncedAt: json['last_synced_at'] != null
          ? DateTime.parse(json['last_synced_at'] as String)
          : null,
      createdAt: DateTime.parse(json['created_at'] as String),
      updatedAt: DateTime.parse(json['updated_at'] as String),
    );
  }

  String get platformDisplayName {
    switch (platform) {
      case 'github':
        return 'GitHub';
      case 'gitlab':
        return 'GitLab';
      case 'bitbucket':
        return 'Bitbucket';
      default:
        return platform;
    }
  }
}
