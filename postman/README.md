# BugRadar API - Postman Collection

This folder contains the Postman collection and environment files for testing the BugRadar API.

## 📁 Files

- **BugRadar-API.postman_collection.json** - Complete API collection with all endpoints
- **BugRadar-Local.postman_environment.json** - Environment for local development
- **BugRadar-Production.postman_environment.json** - Environment for production server

---

## 🚀 Quick Start

### 1. Import into Postman

1. Open Postman
2. Click **Import** button
3. Select all three JSON files from this folder
4. Click **Import**

### 2. Select Environment

- For local testing: Select **BugRadar - Local** environment
- For production: Select **BugRadar - Production** environment (update base_url first)

### 3. Get Authentication Token

#### Option A: Dev Login (Local Only)
1. Select **BugRadar - Local** environment
2. Go to **Authentication** → **Dev Login (Local Only)**
3. Click **Send**
4. Token will be automatically saved to environment

#### Option B: OAuth Login
1. Go to **Authentication** → **Google OAuth - Redirect** or **GitHub OAuth - Redirect**
2. Copy the request URL
3. Open in browser and complete OAuth flow
4. Copy the token from the response
5. Paste into environment variable `auth_token`

---

## 📚 Collection Structure

### 1. Authentication (5 requests)
- Google OAuth - Redirect
- GitHub OAuth - Redirect
- Dev Login (Local Only)
- Get Current User
- Logout

### 2. Integrations (6 requests)
- List All Integrations
- Connect GitHub
- Connect GitLab
- Connect Bitbucket
- Disconnect Integration
- Sync Integration

### 3. Pull Requests (4 requests)
- List Pull Requests
- List Pull Requests - Filtered by Platform
- Get Pull Request Details
- Get Reviewed Pull Requests

### 4. Issues (5 requests)
- List Issues
- List Issues - High Priority Bugs
- Get Issue Details
- Get Bug Issues
- Get Task Issues

### 5. Reviews (4 requests)
- List Reviews
- List Reviews - This Month
- Get Review Details
- Get Review Statistics

### 6. Dashboard (2 requests)
- Get Dashboard Statistics
- Get Recent Activity

**Total: 26 requests**

---

## 🔧 Environment Variables

### Required Variables

| Variable | Description | Example |
|----------|-------------|---------|
| `base_url` | API base URL | `http://localhost:8006` |
| `auth_token` | Bearer token for authentication | Auto-set by Dev Login |

### Optional Variables (for specific requests)

| Variable | Description | Default |
|----------|-------------|---------|
| `integration_id` | Integration ID for testing | `1` |
| `pr_id` | Pull request ID for testing | `1` |
| `issue_id` | Issue ID for testing | `1` |
| `review_id` | Review ID for testing | `1` |
| `current_month_start` | Start date for filtering | `2024-01-01` |

---

## 🎯 Testing Workflow

### Step 1: Authentication
```
1. Run "Dev Login (Local Only)" or complete OAuth flow
2. Verify token is saved in environment
3. Run "Get Current User" to verify authentication
```

### Step 2: Connect Integrations
```
1. Run "Connect GitHub" (open URL in browser)
2. Complete OAuth flow
3. Run "List All Integrations" to verify connection
```

### Step 3: Sync Data
```
1. Get integration_id from "List All Integrations"
2. Update environment variable
3. Run "Sync Integration"
4. Wait for sync to complete (check logs)
```

### Step 4: View Data
```
1. Run "List Pull Requests"
2. Run "List Issues"
3. Run "Get Dashboard Statistics"
4. Run "Get Recent Activity"
```

---

## 📝 Request Examples

### List Pull Requests with Filters
```
GET /api/pull-requests?platform=github&status=open&per_page=20
```

### List High Priority Bugs
```
GET /api/issues?type=bug&priority=high&status=open
```

### Get Reviews from This Month
```
GET /api/reviews?from_date=2024-01-01
```

---

## 🔐 Authentication

All requests (except OAuth redirects and Dev Login) require authentication:

```
Authorization: Bearer {{auth_token}}
```

The collection is configured to automatically use the `auth_token` environment variable.

---

## 🌐 OAuth Flow Testing

### For OAuth endpoints (Google, GitHub, GitLab, Bitbucket):

1. **Copy the request URL** from Postman
2. **Open in browser** (Postman can't handle OAuth redirects)
3. **Complete OAuth flow** in browser
4. **Copy token** from response
5. **Save to environment** variable `auth_token`

Example:
```
http://localhost:8006/api/auth/google
```

---

## 🧪 Testing Tips

### 1. Use Collection Runner
- Select the collection
- Click **Run**
- Select environment
- Run all requests in sequence

### 2. Use Pre-request Scripts
The Dev Login request automatically saves the token:
```javascript
if (pm.response.code === 200) {
    var jsonData = pm.response.json();
    if (jsonData.token) {
        pm.environment.set('auth_token', jsonData.token);
    }
}
```

### 3. Check Response Status
- 200: Success
- 401: Unauthorized (check token)
- 403: Forbidden (check permissions)
- 404: Not Found (check ID)
- 500: Server Error (check logs)

### 4. View Logs
Check Laravel logs for detailed error messages:
```bash
tail -f storage/logs/laravel.log
```

---

## 🔄 Updating Environment

### For Production
1. Open **BugRadar - Production** environment
2. Update `base_url` to your production domain:
   ```
   https://api.bugradar.com
   ```
3. Save environment

### For Custom Port
If running on different port:
```
http://localhost:8006
```

---

## 📊 Response Examples

### Successful Authentication
```json
{
  "success": true,
  "token": "1|abc123...",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com"
  }
}
```

### Pull Requests List
```json
{
  "data": [
    {
      "id": 1,
      "title": "Fix authentication bug",
      "status": "open",
      "repository": "owner/repo",
      "author": "johndoe"
    }
  ],
  "current_page": 1,
  "per_page": 20,
  "total": 50
}
```

### Dashboard Statistics
```json
{
  "pull_requests": {
    "total": 50,
    "open": 30,
    "merged": 15
  },
  "issues": {
    "total": 100,
    "bugs": 30,
    "tasks": 50
  },
  "reviews": {
    "total": 150,
    "this_week": 5
  }
}
```

---

## 🐛 Troubleshooting

### Token Not Working
- Check if token is set in environment
- Verify token hasn't expired
- Try getting a new token

### OAuth Redirects Not Working
- OAuth flows must be completed in browser
- Copy URL from Postman and open in browser
- Don't try to send OAuth requests from Postman

### 404 Errors
- Check if IDs exist in database
- Update environment variables with valid IDs
- Run sync job to populate data

### 500 Errors
- Check Laravel logs: `storage/logs/laravel.log`
- Verify database connection
- Check queue worker is running

---

## 📖 Additional Resources

- **API Documentation**: See `../API_DOCUMENTATION.md`
- **Setup Guide**: See `../SETUP.md`
- **Deployment Guide**: See `../DEPLOYMENT_GUIDE.md`
- **Quick Start**: See `../QUICK_START.md`

---

## 🎉 Happy Testing!

If you encounter any issues:
1. Check the API documentation
2. Review Laravel logs
3. Verify environment variables
4. Ensure queue workers are running
5. Check OAuth credentials in `.env`
