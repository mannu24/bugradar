# BugRadar - Developer Issue Tracking Platform

A comprehensive platform for tracking pull requests, issues, and code reviews across GitHub, GitLab, and Bitbucket.

## Features

- 🔐 **OAuth Authentication** - Google and GitHub OAuth integration
- 🔄 **Multi-Platform Sync** - Sync data from GitHub, GitLab, and Bitbucket
- 📊 **Dashboard** - View statistics and recent activity
- 🔍 **Advanced Filtering** - Filter by status, repository, priority, and more
- 📱 **Mobile App** - Flutter mobile application (in `bugradar_mobile/`)
- 🔔 **Background Sync** - Automatic data synchronization via queue workers

## Tech Stack

### Backend
- **Framework:** Laravel 11
- **Database:** MySQL
- **Authentication:** Laravel Sanctum (Token-based API)
- **Queue:** Database queue driver
- **PHP:** 8.4+

### Mobile
- **Framework:** Flutter
- **State Management:** Riverpod
- **HTTP Client:** Dio

## Quick Start

### Prerequisites
- PHP 8.4+
- Composer
- MySQL 8.0+
- Node.js & NPM (for asset compilation)

### Installation

1. **Clone the repository**
```bash
git clone <repository-url>
cd bugradar
```

2. **Install dependencies**
```bash
composer install
npm install
```

3. **Environment setup**
```bash
cp .env.example .env
php artisan key:generate
```

4. **Configure database**
Edit `.env` file:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bugradar
DB_USERNAME=root
DB_PASSWORD=
```

5. **Run migrations**
```bash
php artisan migrate
```

6. **Configure OAuth**
Add your OAuth credentials to `.env`:
```env
# Google OAuth
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GOOGLE_REDIRECT_URI=http://localhost:8006/api/auth/google/callback

# GitHub OAuth
GITHUB_CLIENT_ID=your_github_client_id
GITHUB_CLIENT_SECRET=your_github_client_secret
GITHUB_REDIRECT_URI=http://localhost:8006/api/auth/github/callback
```

7. **Start the server**
```bash
php artisan serve --port=8006
```

8. **Start queue worker** (for background sync)
```bash
php artisan queue:work
```

## API Documentation

### Authentication

#### Google OAuth Login
```
GET /api/auth/google
```
Redirects to Google OAuth. After successful login, returns user info and API token.

#### Get User Info
```
GET /api/auth/user
Authorization: Bearer {token}
```

#### Logout
```
POST /api/auth/logout
Authorization: Bearer {token}
```

### Integrations

#### List Integrations
```
GET /api/integrations
Authorization: Bearer {token}
```

#### Connect GitHub
```
GET /api/integrations/github/connect?token={token}
```

#### Connect GitLab
```
GET /api/integrations/gitlab/connect?token={token}
```

#### Connect Bitbucket
```
GET /api/integrations/bitbucket/connect?token={token}
```

#### Disconnect Integration
```
DELETE /api/integrations/{id}
Authorization: Bearer {token}
```

#### Trigger Sync
```
POST /api/integrations/{id}/sync
Authorization: Bearer {token}
```

### Pull Requests

#### List Pull Requests
```
GET /api/pull-requests
Authorization: Bearer {token}

Query Parameters:
- status: open|closed|merged
- repository: owner/repo
- platform: github|gitlab|bitbucket
- per_page: 20 (default)
- page: 1 (default)
```

#### Get PR Details
```
GET /api/pull-requests/{id}
Authorization: Bearer {token}
```

#### Get Reviewed PRs
```
GET /api/pull-requests/reviewed
Authorization: Bearer {token}
```

### Issues

#### List Issues
```
GET /api/issues
Authorization: Bearer {token}

Query Parameters:
- status: open|closed
- type: bug|feature|task
- priority: low|medium|high|critical
- repository: owner/repo
- per_page: 20 (default)
```

#### Get Issue Details
```
GET /api/issues/{id}
Authorization: Bearer {token}
```

#### Get Bugs Only
```
GET /api/issues/bugs
Authorization: Bearer {token}
```

#### Get Tasks Only
```
GET /api/issues/tasks
Authorization: Bearer {token}
```

### Reviews

#### List Reviews
```
GET /api/reviews
Authorization: Bearer {token}

Query Parameters:
- status: approved|changes_requested|commented
- per_page: 20 (default)
```

#### Get Review Details
```
GET /api/reviews/{id}
Authorization: Bearer {token}
```

#### Get Review Statistics
```
GET /api/reviews/stats
Authorization: Bearer {token}
```

### Dashboard

#### Get Statistics
```
GET /api/dashboard/stats
Authorization: Bearer {token}
```

Returns:
- Open PRs count
- Assigned issues count
- Total reviews count
- PRs by platform
- Issues by status
- Issues by priority

#### Get Recent Activity
```
GET /api/dashboard/recent
Authorization: Bearer {token}
```

Returns:
- Recent PRs (last 10)
- Recent issues (last 10)
- Recent reviews (last 10)

## Database Schema

### Users
- id, name, email, email_verified_at, password, remember_token, timestamps

### OAuth Providers
- id, user_id, provider, provider_user_id, access_token, refresh_token, expires_at, timestamps

### Integrations
- id, user_id, platform, platform_user_id, username, email, avatar, access_token, refresh_token, expires_at, is_active, last_synced_at, timestamps

### Pull Requests
- id, integration_id, platform, platform_pr_id, repository, title, description, state, author_username, author_avatar, branch_from, branch_to, commits_count, additions, deletions, comments_count, review_status, labels, created_at_platform, updated_at_platform, merged_at, timestamps

### Issues
- id, integration_id, platform, platform_issue_id, repository, title, description, type, state, priority, author_username, author_avatar, assignees, labels, comments_count, due_date, created_at_platform, updated_at_platform, closed_at, timestamps

### Reviews
- id, pull_request_id, platform, platform_review_id, reviewer_username, reviewer_avatar, state, body, submitted_at, timestamps

### Sync Logs
- id, integration_id, sync_type, status, prs_synced, issues_synced, reviews_synced, error_message, started_at, completed_at, timestamps

## Postman Collection

Import the Postman collection for easy API testing:
- Collection: `postman/BugRadar-API.postman_collection.json`
- Environment (Local): `postman/BugRadar-Local.postman_environment.json`
- Environment (Production): `postman/BugRadar-Production.postman_environment.json`

See `postman/README.md` for detailed instructions.

## Mobile App

The Flutter mobile app is located in `bugradar_mobile/` directory.

### Setup
```bash
cd bugradar_mobile
flutter pub get
```

### Configure API URL
Edit `lib/config/app_config.dart`:
```dart
static const String baseUrl = 'http://localhost:8006/api';
```

### Run
```bash
flutter run
```

## Development

### Queue Worker
For background sync to work, keep the queue worker running:
```bash
php artisan queue:work
```

### Clear Cache
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

### Run Tests
```bash
php artisan test
```

## Deployment

See `DEPLOYMENT_GUIDE.md` for detailed deployment instructions.

## OAuth Setup

See `OAUTH_SETUP_GUIDE.md` for detailed OAuth configuration instructions for:
- Google OAuth
- GitHub OAuth
- GitLab OAuth
- Bitbucket OAuth

## API Documentation

See `API_DOCUMENTATION.md` for complete API reference with examples.

## Project Structure

```
bugradar/
├── app/
│   ├── Http/Controllers/     # API Controllers
│   ├── Jobs/                 # Background Jobs
│   ├── Models/               # Eloquent Models
│   └── Services/             # External API Services
├── database/
│   └── migrations/           # Database Migrations
├── routes/
│   ├── api.php              # API Routes
│   └── web.php              # Web Routes
├── bugradar_mobile/         # Flutter Mobile App
├── postman/                 # Postman Collection
└── public/                  # Public Assets
```

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License.

## Support

For issues and questions, please open an issue on GitHub.
