class Issue {
  final int id;
  final int integrationId;
  final String platformIssueId;
  final String title;
  final String description;
  final String status;
  final String type;
  final String priority;
  final String url;
  final String? repository;
  final String? author;
  final List<String> labels;
  final DateTime createdAt;
  final DateTime updatedAt;
  final DateTime? closedAt;
  final Map<String, dynamic>? metadata;

  Issue({
    required this.id,
    required this.integrationId,
    required this.platformIssueId,
    required this.title,
    required this.description,
    required this.status,
    required this.type,
    required this.priority,
    required this.url,
    this.repository,
    this.author,
    required this.labels,
    required this.createdAt,
    required this.updatedAt,
    this.closedAt,
    this.metadata,
  });

  factory Issue.fromJson(Map<String, dynamic> json) {
    return Issue(
      id: json['id'] as int,
      integrationId: json['integration_id'] as int,
      platformIssueId: json['platform_issue_id'] as String,
      title: json['title'] as String,
      description: json['description'] as String? ?? '',
      status: json['status'] as String,
      type: json['type'] as String,
      priority: json['priority'] as String,
      url: json['url'] as String,
      repository: json['repository'] as String?,
      author: json['author'] as String?,
      labels: (json['labels'] as List<dynamic>?)?.map((e) => e.toString()).toList() ?? [],
      createdAt: DateTime.parse(json['created_at'] as String),
      updatedAt: DateTime.parse(json['updated_at'] as String),
      closedAt: json['closed_at'] != null ? DateTime.parse(json['closed_at'] as String) : null,
      metadata: json['metadata'] as Map<String, dynamic>?,
    );
  }
}
