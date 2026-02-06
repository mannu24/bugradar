# BugRadar API - Postman Collection Summary

## 📊 Collection Overview

**Total Requests:** 26  
**Total Folders:** 6  
**Environments:** 2 (Local + Production)

---

## 📁 Folder Breakdown

### 1. Authentication (5 requests)
Test user authentication and OAuth flows.

| Request | Method | Endpoint | Auth Required |
|---------|--------|----------|---------------|
| Google OAuth - Redirect | GET | `/api/auth/google` | No |
| GitHub OAuth - Redirect | GET | `/api/auth/github` | No |
| Dev Login (Local Only) | GET | `/api/auth/dev-login` | No |
| Get Current User | GET | `/api/auth/user` | Yes |
| Logout | POST | `/api/auth/logout` | Yes |

**Use Cases:**
- Test OAuth login flows
- Get authentication token for testing
- Verify user session
- Test logout functionality

---

### 2. Integrations (6 requests)
Manage platform integrations (GitHub, GitLab, Bitbucket).

| Request | Method | Endpoint | Auth Required |
|---------|--------|----------|---------------|
| List All Integrations | GET | `/api/integrations` | Yes |
| Connect GitHub | GET | `/api/integrations/github/connect` | Yes |
| Connect GitLab | GET | `/api/integrations/gitlab/connect` | Yes |
| Connect Bitbucket | GET | `/api/integrations/bitbucket/connect` | Yes |
| Disconnect Integration | DELETE | `/api/integrations/{id}` | Yes |
| Sync Integration | POST | `/api/integrations/{id}/sync` | Yes |

**Use Cases:**
- View connected platforms
- Add new platform integrations
- Remove integrations
- Trigger manual data sync

---

### 3. Pull Requests (4 requests)
View and filter pull requests across all platforms.

| Request | Method | Endpoint | Auth Required |
|---------|--------|----------|---------------|
| List Pull Requests | GET | `/api/pull-requests` | Yes |
| List Pull Requests - Filtered | GET | `/api/pull-requests?platform=github` | Yes |
| Get Pull Request Details | GET | `/api/pull-requests/{id}` | Yes |
| Get Reviewed Pull Requests | GET | `/api/pull-requests/reviewed` | Yes |

**Available Filters:**
- `platform` - github, gitlab, bitbucket
- `status` - open, closed, merged
- `repository` - owner/repo
- `sort_by` - created_at, updated_at, title
- `sort_order` - asc, desc
- `per_page` - pagination
- `page` - page number

**Use Cases:**
- View all PRs across platforms
- Filter by platform or status
- Get PR details with reviews
- View PRs you've reviewed

---

### 4. Issues (5 requests)
View and filter issues across all platforms.

| Request | Method | Endpoint | Auth Required |
|---------|--------|----------|---------------|
| List Issues | GET | `/api/issues` | Yes |
| List Issues - High Priority Bugs | GET | `/api/issues?type=bug&priority=high` | Yes |
| Get Issue Details | GET | `/api/issues/{id}` | Yes |
| Get Bug Issues | GET | `/api/issues/bugs` | Yes |
| Get Task Issues | GET | `/api/issues/tasks` | Yes |

**Available Filters:**
- `platform` - github, gitlab, bitbucket
- `type` - bug, feature, task
- `status` - open, closed, in_progress
- `priority` - low, medium, high, critical
- `repository` - owner/repo
- `sort_by` - created_at, updated_at, priority
- `sort_order` - asc, desc
- `per_page` - pagination
- `page` - page number

**Use Cases:**
- View all issues across platforms
- Filter by type, priority, or status
- Get issue details
- Focus on bugs or tasks

---

### 5. Reviews (4 requests)
View review history and statistics.

| Request | Method | Endpoint | Auth Required |
|---------|--------|----------|---------------|
| List Reviews | GET | `/api/reviews` | Yes |
| List Reviews - This Month | GET | `/api/reviews?from_date={date}` | Yes |
| Get Review Details | GET | `/api/reviews/{id}` | Yes |
| Get Review Statistics | GET | `/api/reviews/stats` | Yes |

**Available Filters:**
- `platform` - github, gitlab, bitbucket
- `status` - approved, changes_requested, commented
- `from_date` - YYYY-MM-DD
- `to_date` - YYYY-MM-DD
- `sort_by` - reviewed_at
- `sort_order` - asc, desc
- `per_page` - pagination
- `page` - page number

**Use Cases:**
- View review history
- Filter reviews by date range
- Get review statistics
- Track review activity

---

### 6. Dashboard (2 requests)
Get overview statistics and recent activity.

| Request | Method | Endpoint | Auth Required |
|---------|--------|----------|---------------|
| Get Dashboard Statistics | GET | `/api/dashboard/stats` | Yes |
| Get Recent Activity | GET | `/api/dashboard/recent` | Yes |

**Use Cases:**
- Get overview of all data
- View recent activity
- Dashboard metrics
- Quick summary

---

## 🔐 Authentication

### Bearer Token
All protected endpoints use Bearer token authentication:
```
Authorization: Bearer {{auth_token}}
```

The collection automatically uses the `auth_token` environment variable.

### Getting a Token

**Local Development:**
1. Run "Dev Login (Local Only)" request
2. Token is automatically saved to environment

**Production:**
1. Complete OAuth flow in browser
2. Copy token from response
3. Manually set in environment variable

---

## 🌍 Environments

### BugRadar - Local
```json
{
  "base_url": "http://localhost:8006",
  "auth_token": "",
  "integration_id": "1",
  "pr_id": "1",
  "issue_id": "1",
  "review_id": "1"
}
```

### BugRadar - Production
```json
{
  "base_url": "https://your-domain.com",
  "auth_token": "",
  "integration_id": "1",
  "pr_id": "1",
  "issue_id": "1",
  "review_id": "1"
}
```

---

## 🎯 Common Testing Scenarios

### Scenario 1: First Time Setup
```
1. Dev Login (Local Only)
2. Get Current User
3. Connect GitHub
4. List All Integrations
5. Sync Integration
6. Get Dashboard Statistics
```

### Scenario 2: View All Data
```
1. List Pull Requests
2. List Issues
3. List Reviews
4. Get Dashboard Statistics
5. Get Recent Activity
```

### Scenario 3: Filter Specific Data
```
1. List Pull Requests - Filtered by Platform
2. List Issues - High Priority Bugs
3. List Reviews - This Month
```

### Scenario 4: Get Details
```
1. Get Pull Request Details
2. Get Issue Details
3. Get Review Details
4. Get Review Statistics
```

---

## 📈 Response Formats

### Success Response (200)
```json
{
  "data": [...],
  "current_page": 1,
  "per_page": 20,
  "total": 100
}
```

### Single Item Response (200)
```json
{
  "pull_request": {...}
}
```

### Error Response (4xx/5xx)
```json
{
  "message": "Error description",
  "errors": {...}
}
```

---

## 🔄 Pagination

All list endpoints support pagination:
```
?per_page=20&page=1
```

Response includes:
- `current_page` - Current page number
- `per_page` - Items per page
- `total` - Total items
- `last_page` - Last page number
- `from` - First item number
- `to` - Last item number

---

## 🎨 Collection Features

### ✅ Auto-Authentication
All requests automatically include Bearer token from environment.

### ✅ Auto-Save Token
Dev Login request automatically saves token to environment.

### ✅ Query Parameters
All list endpoints include example query parameters (disabled by default).

### ✅ Request Descriptions
Each request includes detailed description and usage examples.

### ✅ Example Requests
Includes pre-configured example requests with common filters.

### ✅ Environment Variables
Uses variables for easy switching between local and production.

---

## 📊 Testing Coverage

| Feature | Endpoints | Coverage |
|---------|-----------|----------|
| Authentication | 5 | 100% |
| Integrations | 6 | 100% |
| Pull Requests | 4 | 100% |
| Issues | 5 | 100% |
| Reviews | 4 | 100% |
| Dashboard | 2 | 100% |
| **Total** | **26** | **100%** |

---

## 🚀 Quick Commands

### Import Collection
```bash
# In Postman:
Import → Files → Select all 3 JSON files
```

### Run All Tests
```bash
# In Postman:
Collection → Run → Select Environment → Run
```

### Export Results
```bash
# After running:
Runner → Export Results → Save JSON
```

---

## 📚 Related Documentation

- **README.md** - Detailed usage guide
- **IMPORT_GUIDE.md** - Step-by-step import instructions
- **../API_DOCUMENTATION.md** - Complete API reference
- **../QUICK_START.md** - Quick start guide

---

## 🎉 Ready to Test!

Import the collection and start testing the BugRadar API in minutes!
