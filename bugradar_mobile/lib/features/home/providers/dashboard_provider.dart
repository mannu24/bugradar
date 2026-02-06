import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../services/api_client.dart';
import '../../../models/pull_request.dart';
import '../../../models/issue.dart';
import '../../auth/providers/auth_provider.dart';

class DashboardState {
  final bool isLoading;
  final String? error;
  final Map<String, dynamic>? stats;
  final List<PullRequest>? recentPrs;
  final List<Issue>? recentIssues;

  const DashboardState({
    this.isLoading = false,
    this.error,
    this.stats,
    this.recentPrs,
    this.recentIssues,
  });

  DashboardState copyWith({
    bool? isLoading,
    String? error,
    Map<String, dynamic>? stats,
    List<PullRequest>? recentPrs,
    List<Issue>? recentIssues,
  }) {
    return DashboardState(
      isLoading: isLoading ?? this.isLoading,
      error: error,
      stats: stats ?? this.stats,
      recentPrs: recentPrs ?? this.recentPrs,
      recentIssues: recentIssues ?? this.recentIssues,
    );
  }
}

final dashboardProvider = StateNotifierProvider<DashboardNotifier, DashboardState>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return DashboardNotifier(apiClient);
});

class DashboardNotifier extends StateNotifier<DashboardState> {
  final ApiClient _apiClient;

  DashboardNotifier(this._apiClient) : super(const DashboardState());

  Future<void> loadDashboard() async {
    state = state.copyWith(isLoading: true, error: null);

    try {
      // Load stats
      final statsData = await _apiClient.getDashboardStats();
      
      // Load recent activity
      final recentData = await _apiClient.getRecentActivity();
      
      final recentPrs = (recentData['recent_prs'] as List<dynamic>?)
          ?.map((json) => PullRequest.fromJson(json as Map<String, dynamic>))
          .toList();
      
      final recentIssues = (recentData['recent_issues'] as List<dynamic>?)
          ?.map((json) => Issue.fromJson(json as Map<String, dynamic>))
          .toList();

      state = state.copyWith(
        isLoading: false,
        stats: statsData['stats'] as Map<String, dynamic>?,
        recentPrs: recentPrs,
        recentIssues: recentIssues,
      );
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: e.toString(),
      );
    }
  }
}
