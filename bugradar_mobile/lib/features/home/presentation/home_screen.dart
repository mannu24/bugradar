import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../auth/providers/auth_provider.dart';

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(authNotifierProvider);
    final notifier = ref.read(authNotifierProvider.notifier);

    final user = state.user;

    return Scaffold(
      appBar: AppBar(
        title: const Text('BugRadar Dashboard'),
        actions: [
          IconButton(
            onPressed: notifier.logout,
            icon: const Icon(Icons.logout),
            tooltip: 'Logout',
          )
        ],
      ),
      body: user == null
          ? const Center(child: Text('No user data available'))
          : RefreshIndicator(
              onRefresh: () async => notifier.launchOAuth('google'),
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  Row(
                    children: [
                      CircleAvatar(
                        radius: 32,
                        backgroundImage:
                            user.avatar != null ? NetworkImage(user.avatar!) : null,
                        child: user.avatar == null
                            ? Text(user.name.substring(0, 1).toUpperCase())
                            : null,
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              user.name,
                              style: Theme.of(context)
                                  .textTheme
                                  .titleLarge
                                  ?.copyWith(fontWeight: FontWeight.bold),
                            ),
                            Text(user.email),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 24),
                  Text(
                    'Phase 1 Preview',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: 12),
                  _FeatureCard(
                    title: 'Pull Requests',
                    description:
                        'Coming soon: View all PRs across GitHub / GitLab / Bitbucket.',
                  ),
                  _FeatureCard(
                    title: 'Issues & Tasks',
                    description: 'Unified issue tracker with filters and quick actions.',
                  ),
                  _FeatureCard(
                    title: 'Build & Deploy',
                    description:
                        'Monitor pipelines and deployments in a single timeline.',
                  ),
                ],
              ),
            ),
    );
  }
}

class _FeatureCard extends StatelessWidget {
  const _FeatureCard({
    required this.title,
    required this.description,
  });

  final String title;
  final String description;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: Theme.of(context)
                  .textTheme
                  .titleMedium
                  ?.copyWith(fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 8),
            Text(description),
          ],
        ),
      ),
    );
  }
}

