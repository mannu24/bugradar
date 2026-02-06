import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../auth/providers/auth_provider.dart';
import '../../auth/state/auth_state.dart';
import '../providers/dashboard_provider.dart';

class HomeScreen extends ConsumerStatefulWidget {
  const HomeScreen({super.key});

  @override
  ConsumerState<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends ConsumerState<HomeScreen> {
  int _selectedIndex = 0;

  @override
  void initState() {
    super.initState();
    // Load dashboard data
    Future.microtask(() => ref.read(dashboardProvider.notifier).loadDashboard());
  }

  @override
  Widget build(BuildContext context) {
    final authState = ref.watch(authNotifierProvider);
    final dashboardState = ref.watch(dashboardProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('BugRadar'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => ref.read(dashboardProvider.notifier).loadDashboard(),
          ),
          PopupMenuButton(
            itemBuilder: (context) => [
              PopupMenuItem(
                child: const Text('Logout'),
                onTap: () => ref.read(authNotifierProvider.notifier).logout(),
              ),
            ],
          ),
        ],
      ),
      body: _selectedIndex == 0 ? _buildDashboard(dashboardState) : _buildSettings(authState),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _selectedIndex,
        onDestinationSelected: (index) => setState(() => _selectedIndex = index),
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.dashboard),
            label: 'Dashboard',
          ),
          NavigationDestination(
            icon: Icon(Icons.settings),
            label: 'Settings',
          ),
        ],
      ),
    );
  }

  Widget _buildDashboard(DashboardState state) {
    if (state.isLoading && state.stats == null) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.error != null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text('Error: ${state.error}'),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: () => ref.read(dashboardProvider.notifier).loadDashboard(),
              child: const Text('Retry'),
            ),
          ],
        ),
      );
    }

    final stats = state.stats;
    if (stats == null) {
      return const Center(child: Text('No data available'));
    }

    return RefreshIndicator(
      onRefresh: () => ref.read(dashboardProvider.notifier).loadDashboard(),
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(
            'Overview',
            style: Theme.of(context).textTheme.headlineSmall,
          ),
          const SizedBox(height: 16),
          _buildStatsGrid(stats),
          const SizedBox(height: 24),
          Text(
            'Recent Activity',
            style: Theme.of(context).textTheme.headlineSmall,
          ),
          const SizedBox(height: 16),
          _buildRecentActivity(state),
        ],
      ),
    );
  }

  Widget _buildStatsGrid(Map<String, dynamic> stats) {
    return GridView.count(
      crossAxisCount: 2,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      mainAxisSpacing: 16,
      crossAxisSpacing: 16,
      childAspectRatio: 1.5,
      children: [
        _buildStatCard(
          'Open PRs',
          stats['open_prs']?.toString() ?? '0',
          Icons.code,
          Colors.blue,
        ),
        _buildStatCard(
          'Assigned Issues',
          stats['assigned_issues']?.toString() ?? '0',
          Icons.bug_report,
          Colors.red,
        ),
        _buildStatCard(
          'Pending Reviews',
          stats['pending_reviews']?.toString() ?? '0',
          Icons.rate_review,
          Colors.orange,
        ),
        _buildStatCard(
          'Total Reviews',
          stats['total_reviews']?.toString() ?? '0',
          Icons.check_circle,
          Colors.green,
        ),
      ],
    );
  }

  Widget _buildStatCard(String title, String value, IconData icon, Color color) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 32, color: color),
            const SizedBox(height: 8),
            Text(
              value,
              style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
            ),
            Text(
              title,
              style: Theme.of(context).textTheme.bodySmall,
              textAlign: TextAlign.center,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildRecentActivity(DashboardState state) {
    final recentPrs = state.recentPrs ?? [];
    final recentIssues = state.recentIssues ?? [];

    if (recentPrs.isEmpty && recentIssues.isEmpty) {
      return const Card(
        child: Padding(
          padding: EdgeInsets.all(16),
          child: Text('No recent activity'),
        ),
      );
    }

    return Column(
      children: [
        ...recentPrs.take(5).map((pr) => Card(
              child: ListTile(
                leading: const Icon(Icons.code, color: Colors.blue),
                title: Text(pr.title, maxLines: 1, overflow: TextOverflow.ellipsis),
                subtitle: Text(pr.repository ?? 'Unknown repo'),
                trailing: Chip(
                  label: Text(pr.status),
                  backgroundColor: pr.status == 'open' ? Colors.green : Colors.grey,
                ),
              ),
            )),
        ...recentIssues.take(5).map((issue) => Card(
              child: ListTile(
                leading: const Icon(Icons.bug_report, color: Colors.red),
                title: Text(issue.title, maxLines: 1, overflow: TextOverflow.ellipsis),
                subtitle: Text(issue.repository ?? 'Unknown repo'),
                trailing: Chip(
                  label: Text(issue.priority),
                  backgroundColor: _getPriorityColor(issue.priority),
                ),
              ),
            )),
      ],
    );
  }

  Color _getPriorityColor(String priority) {
    switch (priority.toLowerCase()) {
      case 'critical':
        return Colors.red;
      case 'high':
        return Colors.orange;
      case 'medium':
        return Colors.yellow;
      case 'low':
        return Colors.green;
      default:
        return Colors.grey;
    }
  }

  Widget _buildSettings(AuthState authState) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        if (authState.user != null) ...[
          CircleAvatar(
            radius: 40,
            backgroundImage: authState.user!.avatar != null
                ? NetworkImage(authState.user!.avatar!)
                : null,
            child: authState.user!.avatar == null
                ? Text(authState.user!.name[0].toUpperCase())
                : null,
          ),
          const SizedBox(height: 16),
          Text(
            authState.user!.name,
            style: Theme.of(context).textTheme.headlineSmall,
            textAlign: TextAlign.center,
          ),
          Text(
            authState.user!.email,
            style: Theme.of(context).textTheme.bodyMedium,
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 32),
        ],
        const Text('Integrations', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
        const SizedBox(height: 8),
        const Card(
          child: ListTile(
            leading: Icon(Icons.code),
            title: Text('GitHub'),
            subtitle: Text('Connect your GitHub account'),
            trailing: Icon(Icons.arrow_forward_ios),
          ),
        ),
        const SizedBox(height: 32),
        ElevatedButton(
          onPressed: () => ref.read(authNotifierProvider.notifier).logout(),
          style: ElevatedButton.styleFrom(
            backgroundColor: Colors.red,
            foregroundColor: Colors.white,
          ),
          child: const Text('Logout'),
        ),
      ],
    );
  }
}
