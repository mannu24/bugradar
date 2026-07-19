# BugRadar Backend — Testing Guide

> All 60 automated tests pass as of July 2026.

---

## Quick Start

```bash
# 1. Terminal 1 — Start the server
php artisan serve --port=8000

# 2. Terminal 2 — Start the queue worker
php artisan queue:work --tries=3

# 3. Fresh DB with test data
php artisan migrate:fresh --force
php artisan db:seed --class=TestDataSeeder

# 4. Run the full automated test suite
php tests/api_test.php
```

Expected output: `60 passed, 0 failed`

---

## Test Data Overview

The `TestDataSeeder` creates:

| Type | Count | Details |
|---|---|---|
| User | 1 | `test@bugradar.dev` |
| Integrations | 2 | GitHub (id=1) + GitLab (id=2) |
| Pull Requests | 4 | 2 GitHub open, 1 GitHub merged, 1 GitLab open |
| Issues | 5 | 2 critical bugs, 1 high bug, 1 feature, 1 task |
| Reviews | 4 | 2 approved, 1 changes_requested, 1 commented |

---

## Getting a Token

All protected endpoints need `Authorization: Bearer {token}`.

**Dev login** (local env only):
```bash
curl http://localhost:8000/api/auth/dev-login
# → { "token": "1|abc...", "user": {...} }
```

Save the token:
```bash
TOKEN=$(curl -s http://localhost:8000/api/auth/dev-login | python3 -c "import sys,json; print(json.load(sys.stdin)['token'])")
```

---

## Manual Testing Reference

Use this as a cheat sheet for manual API testing with curl or Postman.

### Auth

```bash
# Get current user
curl http://localhost:8000/api/auth/user \
  -H "Authorization: Bearer $TOKEN"

# Logout (invalidates token)
curl -X POST http://localhost:8000/api/auth/logout \
  -H "Authorization: Bearer $TOKEN"

# OAuth login flows (open in browser):
# http://localhost:8000/api/auth/google?redirect_url=bugradar://oauth-callback
# http://localhost:8000/api/auth/github?redirect_url=bugradar://oauth-callback
```

### Integrations

```bash
# List all connected integrations
curl http://localhost:8000/api/integrations \
  -H "Authorization: Bearer $TOKEN"

# Connect GitHub (open in browser with your token):
# http://localhost:8000/api/integrations/github/connect?token=YOUR_TOKEN

# Connect GitLab (open in browser):
# http://localhost:8000/api/integrations/gitlab/connect?token=YOUR_TOKEN

# Trigger manual sync
curl -X POST http://localhost:8000/api/integrations/1/sync \
  -H "Authorization: Bearer $TOKEN"

# Disconnect
curl -X DELETE http://localhost:8000/api/integrations/2 \
  -H "Authorization: Bearer $TOKEN"
```

### Dashboard

```bash
# Stats overview
curl http://localhost:8000/api/dashboard/stats \
  -H "Authorization: Bearer $TOKEN"
# → { stats: { open_prs, assigned_issues, total_reviews }, charts: {...} }

# Recent activity
curl http://localhost:8000/api/dashboard/recent \
  -H "Authorization: Bearer $TOKEN"
# → { recent_prs: [...], recent_issues: [...], recent_reviews: [...] }
```

### Pull Requests

```bash
# List all (paginated, 20 per page)
curl http://localhost:8000/api/pull-requests \
  -H "Authorization: Bearer $TOKEN"

# Filter by state
curl "http://localhost:8000/api/pull-requests?status=open" \
  -H "Authorization: Bearer $TOKEN"

# Filter by platform
curl "http://localhost:8000/api/pull-requests?platform=github" \
  -H "Authorization: Bearer $TOKEN"

# Filter by platform + state
curl "http://localhost:8000/api/pull-requests?platform=github&status=open" \
  -H "Authorization: Bearer $TOKEN"

# Sort (newest first by default)
curl "http://localhost:8000/api/pull-requests?sort_by=updated_at&sort_order=asc" \
  -H "Authorization: Bearer $TOKEN"

# Filter by repository
curl "http://localhost:8000/api/pull-requests?repository=testuser/my-app" \
  -H "Authorization: Bearer $TOKEN"

# PRs reviewed by me
curl http://localhost:8000/api/pull-requests/reviewed \
  -H "Authorization: Bearer $TOKEN"

# PR detail (loads reviews)
curl http://localhost:8000/api/pull-requests/1 \
  -H "Authorization: Bearer $TOKEN"
```

### Issues

```bash
# List all
curl http://localhost:8000/api/issues \
  -H "Authorization: Bearer $TOKEN"

# Filter by type (bug / task / feature)
curl "http://localhost:8000/api/issues?type=bug" \
  -H "Authorization: Bearer $TOKEN"

# Filter by state (open / closed)
curl "http://localhost:8000/api/issues?status=open" \
  -H "Authorization: Bearer $TOKEN"

# Filter by priority (low / medium / high / critical)
curl "http://localhost:8000/api/issues?priority=critical" \
  -H "Authorization: Bearer $TOKEN"

# Filter by platform
curl "http://localhost:8000/api/issues?platform=gitlab" \
  -H "Authorization: Bearer $TOKEN"

# Combine filters
curl "http://localhost:8000/api/issues?type=bug&status=open&priority=critical" \
  -H "Authorization: Bearer $TOKEN"

# Bugs only (shortcut endpoint)
curl http://localhost:8000/api/issues/bugs \
  -H "Authorization: Bearer $TOKEN"

# Bugs sorted by severity (critical → high → medium → low)
curl "http://localhost:8000/api/issues/bugs?sort_by=priority" \
  -H "Authorization: Bearer $TOKEN"

# Tasks only
curl http://localhost:8000/api/issues/tasks \
  -H "Authorization: Bearer $TOKEN"

# Issue detail
curl http://localhost:8000/api/issues/1 \
  -H "Authorization: Bearer $TOKEN"
```

### Reviews

```bash
# List all reviews
curl http://localhost:8000/api/reviews \
  -H "Authorization: Bearer $TOKEN"

# Filter by state (approved / changes_requested / commented / dismissed)
curl "http://localhost:8000/api/reviews?status=approved" \
  -H "Authorization: Bearer $TOKEN"

# Filter by platform
curl "http://localhost:8000/api/reviews?platform=github" \
  -H "Authorization: Bearer $TOKEN"

# Filter by date range
curl "http://localhost:8000/api/reviews?from_date=2026-07-01&to_date=2026-07-31" \
  -H "Authorization: Bearer $TOKEN"

# Review stats
curl http://localhost:8000/api/reviews/stats \
  -H "Authorization: Bearer $TOKEN"
# → { total_reviews, approved, changes_requested, commented, this_week, this_month }

# Review detail
curl http://localhost:8000/api/reviews/1 \
  -H "Authorization: Bearer $TOKEN"
```

---

## Testing the OAuth Flow (Browser)

The OAuth flows need a real browser because GitHub/Google redirect back with a code.

### Step 1 — Get your Sanctum token
Open in terminal:
```bash
curl http://localhost:8000/api/auth/dev-login
```
Copy the `token` value.

### Step 2 — Connect GitHub integration
Open this URL in your browser (replace YOUR_TOKEN):
```
http://localhost:8000/api/integrations/github/connect?token=YOUR_TOKEN
```
You'll be redirected to GitHub → authorize → success page shows.

### Step 3 — Verify sync started
```bash
# Check integration appeared
curl http://localhost:8000/api/integrations -H "Authorization: Bearer YOUR_TOKEN"

# Check sync log in the queue worker terminal — you'll see job processing
# Check data appeared
curl http://localhost:8000/api/pull-requests -H "Authorization: Bearer YOUR_TOKEN"
```

### Step 4 — Test the mobile deep-link flow
Open in browser:
```
http://localhost:8000/api/auth/github?redirect_url=bugradar://oauth-callback
```
After GitHub auth, the page will try to redirect to `bugradar://oauth-callback?token=xxx&success=true`. On desktop this will fail (no app) but you can copy the token from the URL.

---

## Testing with Postman

Import these as a Postman collection:

1. Create a new collection `BugRadar`
2. Add collection variable: `base_url = http://localhost:8000/api`
3. Add collection variable: `token` (empty, set after login)
4. Add a Pre-request script on the collection:
   ```javascript
   // Auto-login if no token
   if (!pm.collectionVariables.get('token')) {
       pm.sendRequest('{{base_url}}/auth/dev-login', (err, res) => {
           pm.collectionVariables.set('token', res.json().token);
       });
   }
   ```
5. Set collection Authorization → Bearer Token → `{{token}}`

---

## What the Automated Test Suite Covers

`php tests/api_test.php` tests:

| Category | Tests |
|---|---|
| Auth | dev-login works, /auth/user returns correct user |
| Integrations | List, tokens hidden, manual sync, disconnect, cascade check |
| Dashboard | Stats counts correct, charts present, recent data populated |
| Pull Requests | Listing, filters (state/platform/combined), reviewed, detail with reviews, labels |
| Issues | Listing, filters (type/state/priority/platform/combined), bugs endpoint, tasks endpoint, priority sort, detail |
| Reviews | Listing, state filter, stats (total/approved/changes_requested/commented/this_week), detail with PR |
| Security | 401 on all protected endpoints without token, dev-login accessible |
| Logout | Token invalidated after logout |
| Cascade | GitLab integration delete cascades to PRs/issues/reviews |

---

## Checking the Queue Worker

After triggering a sync (integration connect or `POST /integrations/{id}/sync`), check the worker:

```bash
# In Terminal 2, you'll see:
# [2026-07-07 20:32:00] Processing: App\Jobs\SyncGitHubData
# [2026-07-07 20:32:05] Processed: App\Jobs\SyncGitHubData

# Check sync log directly
php artisan tinker --execute="print_r(App\Models\SyncLog::latest()->first()->toArray());"
```

---

## Checking the Scheduler

```bash
# Run the scheduler manually (normally runs via cron every minute)
php artisan schedule:run

# List scheduled tasks
php artisan schedule:list
```

---

## Resetting Test Data

```bash
# Full reset
php artisan migrate:fresh --force && php artisan db:seed --class=TestDataSeeder

# Just clear data, keep schema
php artisan db:seed --class=TestDataSeeder
```

---

## Known Limitations (Not Bugs)

| Limitation | Reason |
|---|---|
| `pending_reviews` always 0 in dashboard | Requires storing "review_requested" state on PRs — post-MVP |
| GitLab review sync returns 0 | GitLab approval API needs `project_id` + `mr_iid` to be stored per MR — post-MVP |
| Bitbucket review sync not implemented | Bitbucket review model differs significantly — post-MVP |
| `sort_by` accepts any column name | No whitelist validation — don't expose to untrusted input in production |
| Dev login works on all envs | `DevAuthController` guards on `APP_ENV !== 'local'` but `APP_ENV=local` in your `.env` |

---

## Deployment Checklist

When deploying to `bugradar.laravue.in`:

```bash
# 1. Upload code
# 2. Set APP_ENV=production in .env
# 3. Run migrations
php artisan migrate --force

# 4. Cache config and routes
php artisan config:cache
php artisan route:cache

# 5. Start queue worker with Supervisor (keep alive)
php artisan queue:work --queue=default --tries=3 --sleep=3

# 6. Add cron for scheduler (runs every minute, picks what to execute)
* * * * * cd /path/to/bugradar && php artisan schedule:run >> /dev/null 2>&1

# 7. Verify health check
curl https://bugradar.laravue.in/up
```
