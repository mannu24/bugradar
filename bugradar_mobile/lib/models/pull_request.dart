class PullRequest {
  final int id;
  final int integrationId;
  final String platformPrId;
  final String title;
  final String description;
  final String status;
  final String url;
  final String? repository;
  final String? author;
  final List<String> labels;
  final DateTime createdAt;
  final DateTime updatedAt;
  final DateTime? mergedAt;
  final Map<String, dynamic>? metadata;

  PullRequest({
    required this.id,
    required this.integrationId,
    required this.platformPrId,
    required this.title,
    required this.description,
    required this.status,
    required this.url,
    this.repository,
    this.author,
    required this.labels,
    required this.createdAt,
    required this.updatedAt,
    this.mergedAt,
    this.metadata,
  });

  factory PullRequest.fromJson(Map<String, dynamic> json) {
    return PullRequest(
      id: json['id'] as int,
      integrationId: json['integration_id'] as int,
      platformPrId: json['platform_pr_id'] as String,
      title: json['title'] as String,
      description: json['description'] as String? ?? '',
      status: json['status'] as String,
      url: json['url'] as String,
      repository: json['repository'] as String?,
      author: json['author'] as String?,
      labels: (json['labels'] as List<dynamic>?)?.map((e) => e.toString()).toList() ?? [],
      createdAt: DateTime.parse(json['created_at'] as String),
      updatedAt: DateTime.parse(json['updated_at'] as String),
      mergedAt: json['merged_at'] != null ? DateTime.parse(json['merged_at'] as String) : null,
      metadata: json['metadata'] as Map<String, dynamic>?,
    );
  }
}
