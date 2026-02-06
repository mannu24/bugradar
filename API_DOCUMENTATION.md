# BugRadar API Documentation

## Base URL
```
http://localhost:8006/api  (Local Development)
https://your-domain.com/api  (Production)
```

## Authentication
All protected endpoints require a Bearer token in the Authorization header:
```
Authorization: Bearer {your_token}
```

---

## Authentication Endpoints

### 1. Google OAuth Login
**GET** `/auth/google`

Redirects to Google OAuth consent screen.

**Response:** Redirect to Google

---

### 2. Google OAuth Callback
**GET** `/auth/google/callback`

Handles Google OAuth callback and returns authentication token.

**Response:**
```json
{
  "success": true,
  "token": "your_api_token",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com"
  }
}
```

---

### 3. GitHub OAuth Login
**GET** `/auth/github`

Redirects to GitHub OAuth consent screen.

**Response:** Redirect to GitHub

---

### 4. GitHub OAuth Callback
**GET** `/auth/github/callback`

Handles GitHub OAuth callback and returns authentication token.

**Response:**
```json
{
  "success": true,
  "token": "your_api_token",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com"
  }
}
```

---

### 5. Get Current User
**GET** `/auth/user`

Returns the authenticated user's information.

**Headers:** `Authorization: Bearer {token}`

**Response:**
```json
{
  "id": 1,
  "name": "John Doe",
  "email": "john@example.com",
  "avatar": "https://...",
  "created_at": "2024-01-01T00:00:00.000000Z"
}
```

---

### 6. Logout
**POST** `/auth/logout`

Revokes the current authentication token.

**Headers:** `Authorization: Bearer {token}`

**Response:**
```json
{
  "message": "Logged out successfully"
}
```

---

## Integration Endpoints

### 1. List Integrations
**GET** `/integrations`

Returns all connected platform integrations for the authenticated user.

**Headers:** `Authorization: Bearer {token}`

**Response:**
```json
{
  "integrations": [
    {
      "id": 1,
      "platform": "github",
      "platform_username": "johndoe",
      "is_active": true,
      "last_synced_at": "2024-01-01T00:00:00.000000Z",
      "created_at": "2024-01-01T00:00:00.000000Z"
    }
  ]
}
```

---

### 2. Connect GitHub
**GET** `/integrations/github/connect`

Initiates GitHub OAuth flow for integration.

**Headers:** `Authorization: Bearer {token}`

**Response:** Redirect to GitHub OAuth

---

### 3. Connect GitLab
**GET** `/integrations/gitlab/connect`

Initiates GitLab OAuth flow for integration.

**Headers:** `Authorization: Bearer {token}`

**Response:** Redirect to GitLab OAuth

---

### 4. Connect Bitbucket
**GET** `/integrations/bitbucket/connect`

Initiates Bitbucket OAuth flow for integration.

**Headers:** `Authorization: Bearer {token}`

**Response:** Redirect to Bitbucket OAuth

---

### 5. Disconnect Integration
**DELETE** `/integrations/{id}`

Disconnects and removes an integration.

**Headers:** `Authorization: Bearer {token}`

**Response:**
```json
{
  "success": true,
  "message": "Integration disconnected"
}
```

---

### 6. Sync Integration
**POST** `/integrations/{id}/sync`

Manually triggers a sync for the specified integration.

**Headers:** `Authorization: Bearer {token}`

**Response:**
```json
{
  "success": true,
  "message": "Sync started"
}
```

---

## Pull Request Endpoints

### 1. List Pull Requests
**GET** `/pull-requests`

Returns all pull requests from connected integrations.

**Headers:** `Authorization: Bearer {token}`

**Query Parameters:**
- `platform` (optional): Filter by platform (github, gitlab, bitbucket)
- `status` (optional): Filter by status (open, closed, merged)
- `repository` (optional): Filter by repository name
- `sort_by` (optional): Sort field (created_at, updated_at, title)
- `sort_order` (optional): Sort order (asc, desc)
- `per_page` (optional): Items per page (default: 20)
- `page` (optional): Page number

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "title": "Fix bug in authentication",
      "description": "This PR fixes...",
      "status": "open",
      "url": "https://github.com/...",
      "repository": "owner/repo",
      "author": "johndoe",
      "is_draft": false,
      "created_at_platform": "2024-01-01T00:00:00Z",
      "updated_at_platform": "2024-01-01T00:00:00Z",
      "integration": {
        "platform": "github"
      }
    }
  ],
  "current_page": 1,
  "per_page": 20,
  "total": 50
}
```

---

### 2. Get Pull Request Details
**GET** `/pull-requests/{id}`

Returns detailed information about a specific pull request.

**Headers:** `Authorization: Bearer {token}`

**Response:**
```json
{
  "pull_request": {
    "id": 1,
    "title": "Fix bug in authentication",
    "description": "This PR fixes...",
    "status": "open",
    "url": "https://github.com/...",
    "repository": "owner/repo",
    "author": "johndoe",
    "is_draft": false,
    "metadata": {
      "number": 123,
      "head_branch": "fix-auth",
      "base_branch": "main",
      "labels": ["bug", "high-priority"]
    },
    "reviews": [
      {
        "id": 1,
        "reviewer": "janedoe",
        "status": "approved",
        "reviewed_at": "2024-01-01T00:00:00Z"
      }
    ]
  }
}
```

---

### 3. Get Reviewed Pull Requests
**GET** `/pull-requests/reviewed`

Returns pull requests that the user has reviewed.

**Headers:** `Authorization: Bearer {token}`

**Query Parameters:** Same as List Pull Requests

**Response:** Same format as List Pull Requests

---

## Issue Endpoints

### 1. List Issues
**GET** `/issues`

Returns all issues from connected integrations.

**Headers:** `Authorization: Bearer {token}`

**Query Parameters:**
- `platform` (optional): Filter by platform
- `type` (optional): Filter by type (bug, feature, task)
- `status` (optional): Filter by status (open, closed, in_progress)
- `priority` (optional): Filter by priority (low, medium, high, critical)
- `repository` (optional): Filter by repository
- `sort_by` (optional): Sort field
- `sort_order` (optional): Sort order
- `per_page` (optional): Items per page
- `page` (optional): Page number

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "title": "Login page not responsive",
      "description": "The login page...",
      "status": "open",
      "type": "bug",
      "priority": "high",
      "url": "https://github.com/...",
      "repository": "owner/repo",
      "author": "johndoe",
      "assignees": ["janedoe"],
      "labels": ["bug", "ui"],
      "created_at_platform": "2024-01-01T00:00:00Z"
    }
  ],
  "current_page": 1,
  "per_page": 20,
  "total": 100
}
```

---

### 2. Get Issue Details
**GET** `/issues/{id}`

Returns detailed information about a specific issue.

**Headers:** `Authorization: Bearer {token}`

**Response:**
```json
{
  "issue": {
    "id": 1,
    "title": "Login page not responsive",
    "description": "The login page...",
    "status": "open",
    "type": "bug",
    "priority": "high",
    "url": "https://github.com/...",
    "repository": "owner/repo",
    "author": "johndoe",
    "assignees": ["janedoe"],
    "labels": ["bug", "ui"],
    "metadata": {
      "number": 456,
      "comments_count": 5,
      "milestone": "v1.0"
    }
  }
}
```

---

### 3. Get Bug Issues
**GET** `/issues/bugs`

Returns only bug-type issues.

**Headers:** `Authorization: Bearer {token}`

**Query Parameters:** Same as List Issues

**Response:** Same format as List Issues

---

### 4. Get Task Issues
**GET** `/issues/tasks`

Returns only task-type issues.

**Headers:** `Authorization: Bearer {token}`

**Query Parameters:** Same as List Issues

**Response:** Same format as List Issues

---

## Review Endpoints

### 1. List Reviews
**GET** `/reviews`

Returns all reviews by the authenticated user.

**Headers:** `Authorization: Bearer {token}`

**Query Parameters:**
- `platform` (optional): Filter by platform
- `status` (optional): Filter by status (approved, changes_requested, commented)
- `from_date` (optional): Filter from date (YYYY-MM-DD)
- `to_date` (optional): Filter to date (YYYY-MM-DD)
- `sort_by` (optional): Sort field (default: reviewed_at)
- `sort_order` (optional): Sort order (default: desc)
- `per_page` (optional): Items per page

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "reviewer": "johndoe",
      "status": "approved",
      "comment": "Looks good!",
      "reviewed_at": "2024-01-01T00:00:00Z",
      "pull_request": {
        "id": 1,
        "title": "Fix bug in authentication",
        "repository": "owner/repo"
      }
    }
  ],
  "current_page": 1,
  "per_page": 20,
  "total": 30
}
```

---

### 2. Get Review Details
**GET** `/reviews/{id}`

Returns detailed information about a specific review.

**Headers:** `Authorization: Bearer {token}`

**Response:**
```json
{
  "review": {
    "id": 1,
    "reviewer": "johndoe",
    "status": "approved",
    "comment": "Looks good!",
    "reviewed_at": "2024-01-01T00:00:00Z",
    "pull_request": {
      "id": 1,
      "title": "Fix bug in authentication",
      "url": "https://github.com/..."
    }
  }
}
```

---

### 3. Get Review Statistics
**GET** `/reviews/stats`

Returns review statistics for the authenticated user.

**Headers:** `Authorization: Bearer {token}`

**Response:**
```json
{
  "total_reviews": 150,
  "approved": 100,
  "changes_requested": 30,
  "commented": 20,
  "this_week": 5,
  "this_month": 25
}
```

---

## Dashboard Endpoints

### 1. Get Dashboard Statistics
**GET** `/dashboard/stats`

Returns overview statistics for the dashboard.

**Headers:** `Authorization: Bearer {token}`

**Response:**
```json
{
  "pull_requests": {
    "total": 50,
    "open": 30,
    "merged": 15,
    "closed": 5
  },
  "issues": {
    "total": 100,
    "open": 60,
    "closed": 40,
    "bugs": 30,
    "tasks": 50,
    "features": 20
  },
  "reviews": {
    "total": 150,
    "this_week": 5,
    "this_month": 25
  },
  "integrations": {
    "total": 3,
    "active": 3,
    "platforms": ["github", "gitlab", "bitbucket"]
  }
}
```

---

### 2. Get Recent Activity
**GET** `/dashboard/recent`

Returns recent activity across all integrations.

**Headers:** `Authorization: Bearer {token}`

**Query Parameters:**
- `limit` (optional): Number of items to return (default: 10)

**Response:**
```json
{
  "recent_pull_requests": [
    {
      "id": 1,
      "title": "Fix bug",
      "repository": "owner/repo",
      "updated_at": "2024-01-01T00:00:00Z"
    }
  ],
  "recent_issues": [
    {
      "id": 1,
      "title": "Login issue",
      "repository": "owner/repo",
      "updated_at": "2024-01-01T00:00:00Z"
    }
  ],
  "recent_reviews": [
    {
      "id": 1,
      "pull_request_title": "Fix bug",
      "status": "approved",
      "reviewed_at": "2024-01-01T00:00:00Z"
    }
  ]
}
```

---

## Error Responses

All endpoints may return the following error responses:

### 401 Unauthorized
```json
{
  "message": "Unauthenticated."
}
```

### 403 Forbidden
```json
{
  "success": false,
  "message": "Unauthorized"
}
```

### 404 Not Found
```json
{
  "message": "Resource not found"
}
```

### 500 Internal Server Error
```json
{
  "success": false,
  "message": "An error occurred"
}
```

---

## Rate Limiting

API requests are rate-limited to prevent abuse. Current limits:
- 60 requests per minute for authenticated users
- 10 requests per minute for unauthenticated users

Rate limit headers are included in responses:
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
X-RateLimit-Reset: 1640000000
```
