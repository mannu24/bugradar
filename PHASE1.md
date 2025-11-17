# 🚀 BugRadar Phase 1 - MVP Implementation

## Overview

Phase 1 focuses on building the core foundation of BugRadar with essential integrations and a simplified mobile dashboard.

## 📋 Phase 1 Features

### 0. User Authentication

#### 0.1 OAuth Sign In/Sign Up
- **Google OAuth**
  - Sign in with Google account
  - Sign up with Google (auto-create account)
  - Fetch user profile (name, email, avatar)
  - Store user information in database

- **GitHub OAuth**
  - Sign in with GitHub account
  - Sign up with GitHub (auto-create account)
  - Fetch user profile (name, email, avatar, username)
  - Store user information in database

#### 0.2 Authentication Flow
- User selects "Sign in with Google" or "Sign in with GitHub"
- Redirect to OAuth provider
- Handle OAuth callback
- Create or update user account
- Generate API token (Laravel Sanctum)
- Return token to mobile app

#### 0.3 User Account Management
- Auto-create account on first OAuth login
- Link multiple OAuth providers to same account (if same email)
- Store OAuth provider ID and email
- Handle account merging (same email, different providers)

### 1. Platform Integrations

#### 1.1 GitHub Integration
- **OAuth 2.0 Authentication**
  - Connect GitHub account via OAuth
  - Store encrypted access tokens
  - Handle token refresh

- **GitHub API Integration**
  - Fetch user's assigned issues
  - Fetch pull requests (created, assigned, review requested)
  - Fetch repositories and projects
  - Fetch commits related to issues/PRs
  - Fetch issue comments and PR reviews

- **GitHub Projects Support**
  - Fetch project boards
  - Fetch project cards and columns
  - Track project status for issues/PRs

#### 1.2 GitLab Integration
- **OAuth 2.0 Authentication**
  - Connect GitLab account (cloud/self-hosted)
  - Store encrypted access tokens
  - Handle token refresh

- **GitLab API Integration**
  - Fetch merge requests (assigned, review requested)
  - Fetch issues (assigned, created)
  - Fetch projects and groups
  - Fetch commits and pipelines
  - Fetch issue/MR comments

#### 1.3 Bitbucket Integration
- **OAuth 2.0 Authentication**
  - Connect Bitbucket account
  - Store encrypted access tokens
  - Handle token refresh

- **Bitbucket API Integration**
  - Fetch pull requests (assigned, review requested)
  - Fetch issues (assigned, created)
  - Fetch repositories and workspaces
  - Fetch commits and pipelines
  - Fetch PR/issue comments

### 2. Data Aggregation & Display

#### 2.1 Pull Requests (PRs)
- **Unified PR List**
  - All open PRs from GitHub, GitLab, Bitbucket
  - Filter by platform, repository, status
  - Sort by date, priority, repository

- **PR Details**
  - Title, description, author
  - Status (open, merged, closed, draft)
  - Review status (approved, changes requested, pending)
  - Related commits
  - Review comments count
  - Labels/tags

#### 2.2 Bug Issues
- **Unified Issue List**
  - All bug issues from all platforms
  - Filter by platform, repository, priority, status
  - Sort by date, priority, due date

- **Issue Details**
  - Title, description, reporter
  - Status (open, closed, in progress)
  - Priority level
  - Labels/tags
  - Assignees
  - Related PRs and commits

#### 2.3 Tasks
- **Task List**
  - All task-type issues from all platforms
  - Filter by platform, repository, status
  - Sort by due date, priority

- **Task Details**
  - Title, description
  - Status and progress
  - Due dates
  - Assignees
  - Related items

#### 2.4 PRs Reviewed
- **Review History**
  - List of PRs user has reviewed
  - Review status (approved, changes requested, commented)
  - Review date and time
  - Filter by platform, repository, date range

### 3. Simplified Mobile Dashboard (Flutter)

#### 3.1 Dashboard Screen
- **Overview Cards**
  - Total open PRs
  - Total assigned issues
  - Pending reviews
  - Recent activity

- **Quick Stats**
  - PRs by platform (pie chart)
  - Issues by status (bar chart)
  - Activity timeline

- **Recent Items List**
  - Latest PRs
  - Latest issues
  - Latest reviews

#### 3.2 PRs Screen
- List view of all PRs
- Pull-to-refresh
- Filter and search
- Tap to view details
- Quick actions (approve, comment, view diff)

#### 3.3 Issues Screen
- List view of all issues
- Filter by type (bug, task, feature)
- Filter by status
- Tap to view details
- Quick actions (assign, comment, close)

#### 3.4 Reviews Screen
- List of PRs reviewed
- Review status indicators
- Filter by date range
- View review comments

#### 3.5 Settings Screen
- Manage connected accounts
- Add/remove integrations
- Sync settings
- Notification preferences

### 4. Backend Architecture (Laravel)

#### 4.1 Database Schema (MySQL)

**Core Tables:**
- `users` - User accounts
- `oauth_providers` - OAuth provider accounts (Google, GitHub for authentication)
- `integrations` - Connected platform accounts (GitHub, GitLab, Bitbucket for data sync)
- `pull_requests` - Cached PR data
- `issues` - Cached issue data
- `reviews` - PR review data
- `sync_logs` - Sync history and status

**Relationships:**
- User has many OAuth Providers (for authentication)
- User has many Integrations (for data sync)
- Integration has many Pull Requests
- Integration has many Issues
- Pull Request has many Reviews

#### 4.2 API Endpoints (REST/GraphQL)

**Authentication:**
- `GET /api/auth/google` - Redirect to Google OAuth
- `GET /api/auth/google/callback` - Handle Google OAuth callback
- `GET /api/auth/github` - Redirect to GitHub OAuth
- `GET /api/auth/github/callback` - Handle GitHub OAuth callback
- `POST /api/auth/logout` - Logout user
- `GET /api/auth/user` - Get current authenticated user

**Integrations:**
- `GET /api/integrations` - List all connected accounts
- `POST /api/integrations/github` - Connect GitHub
- `POST /api/integrations/gitlab` - Connect GitLab
- `POST /api/integrations/bitbucket` - Connect Bitbucket
- `DELETE /api/integrations/{id}` - Disconnect account
- `POST /api/integrations/{id}/sync` - Manual sync

**Pull Requests:**
- `GET /api/pull-requests` - List all PRs
- `GET /api/pull-requests/{id}` - Get PR details
- `GET /api/pull-requests/reviewed` - Get reviewed PRs

**Issues:**
- `GET /api/issues` - List all issues
- `GET /api/issues/{id}` - Get issue details
- `GET /api/issues/bugs` - Get only bug issues
- `GET /api/issues/tasks` - Get only task issues

**Dashboard:**
- `GET /api/dashboard/stats` - Get dashboard statistics
- `GET /api/dashboard/recent` - Get recent activity

#### 4.3 Background Jobs
- Sync jobs for each platform (GitHub, GitLab, Bitbucket)
- Scheduled sync every 15-30 minutes
- Queue-based processing using Redis

#### 4.4 Services
- `OAuthService` - Handle Google/GitHub OAuth authentication
- `GitHubService` - Handle GitHub API calls (for data sync)
- `GitLabService` - Handle GitLab API calls
- `BitbucketService` - Handle Bitbucket API calls
- `SyncService` - Orchestrate data synchronization
- `TokenEncryptionService` - Encrypt/decrypt API tokens

## 📁 Project Structure

```
bugradar-me/
├── app/                    # Laravel application
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Middleware/
│   │   └── Requests/
│   ├── Models/
│   ├── Services/          # Integration services
│   ├── Jobs/              # Background sync jobs
│   └── GraphQL/           # GraphQL schema (optional)
├── config/
├── database/
│   ├── migrations/
│   └── seeders/
├── routes/
│   ├── api.php
│   └── web.php
├── tests/
├── mobile-app/            # Flutter application
│   ├── lib/
│   │   ├── models/        # Data models
│   │   ├── services/      # API services
│   │   ├── screens/       # UI screens
│   │   ├── widgets/       # Reusable widgets
│   │   └── utils/         # Utilities
│   ├── assets/
│   └── pubspec.yaml
├── .env
├── composer.json
├── package.json
└── README.md
```

## 🔧 Technical Stack

### Backend
- **Framework**: Laravel 10+
- **Database**: MySQL 8.0+
- **Queue**: Redis
- **API**: REST API (GraphQL optional)
- **Auth**: Laravel Sanctum
- **OAuth**: Laravel Socialite (for Google & GitHub OAuth)
- **Encryption**: Laravel's built-in encryption

### Mobile App
- **Framework**: Flutter 3.0+
- **State Management**: Provider or Riverpod
- **HTTP Client**: Dio or http package
- **Local Storage**: Hive or SharedPreferences
- **UI**: Material Design 3

## 🎯 Phase 1 Deliverables

1. ✅ Backend API with Laravel
2. ✅ Database schema and migrations
3. ✅ Google & GitHub OAuth authentication (sign in/sign up)
4. ✅ GitHub, GitLab, Bitbucket OAuth integration (for data sync)
5. ✅ API endpoints for authentication, PRs, Issues, Reviews
6. ✅ Background sync jobs
7. ✅ Flutter mobile app with OAuth login
8. ✅ Dashboard, PRs, Issues, Reviews screens
9. ✅ Settings and integration management

## 📝 Implementation Steps

1. **Setup Laravel Backend**
   - Initialize Laravel project
   - Setup MySQL database
   - Configure Redis for queues
   - Setup Laravel Sanctum
   - Install Laravel Socialite for OAuth

2. **Database Design**
   - Create migrations for all tables (users, oauth_providers, integrations, etc.)
   - Define relationships
   - Create seeders for testing

3. **User Authentication (OAuth)**
   - Configure Google OAuth credentials
   - Configure GitHub OAuth credentials
   - Implement Google OAuth sign in/sign up flow
   - Implement GitHub OAuth sign in/sign up flow
   - Create OAuthService for handling authentication
   - Handle account creation and linking

4. **Platform Integrations (Data Sync)**
   - Implement GitHub OAuth flow (for data sync)
   - Implement GitLab OAuth flow
   - Implement Bitbucket OAuth flow
   - Token encryption service

5. **API Services**
   - Create service classes for each platform
   - Implement API client methods
   - Error handling and retry logic

6. **Sync Jobs**
   - Create background jobs for each platform
   - Implement sync logic
   - Schedule periodic syncs

7. **REST API Endpoints**
   - OAuth authentication endpoints (Google, GitHub)
   - Integration management endpoints
   - PRs, Issues, Reviews endpoints
   - Dashboard endpoints

8. **Flutter App Setup**
   - Initialize Flutter project in mobile-app folder
   - Setup project structure
   - Configure API client
   - Setup OAuth authentication flow

9. **Flutter Screens**
   - Login/Sign up screen (Google & GitHub OAuth)
   - Dashboard screen
   - PRs list and detail screens
   - Issues list and detail screens
   - Reviews screen
   - Settings screen

10. **Testing**
    - Unit tests for services
    - API endpoint tests
    - OAuth flow tests
    - Flutter widget tests

11. **Documentation**
    - API documentation
    - OAuth setup instructions
    - Setup instructions
    - Deployment guide

## 🔐 Security Considerations

- Encrypt all stored OAuth tokens
- Use HTTPS for all API calls
- Implement rate limiting
- Validate and sanitize all inputs
- Use Laravel Sanctum for API authentication
- Store sensitive data securely

## 📊 Success Metrics

- Successfully connect to GitHub, GitLab, Bitbucket
- Sync PRs, Issues, Reviews from all platforms
- Display unified data in mobile app
- Sync completes within 30 seconds
- Mobile app loads dashboard in < 2 seconds

