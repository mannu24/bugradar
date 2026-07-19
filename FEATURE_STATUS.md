# BugRadar Backend — Feature Status Document

> Last updated: July 2026  
> Stack: Laravel 12 · PHP 8.2 · MySQL · Sanctum · Socialite · Queue (database driver)

---

## Legend
- ✅ Done & working
- ⚠️ Partially done / known limitation
- ❌ Not started (post-MVP)

---

## 1. Authentication  — ✅ VERIFIED END-TO-END (real Google + GitHub accounts)

| Feature | Status | Notes |
|---|---|---|
| Google OAuth sign-in | ✅ Tested | Stateless + HMAC-signed state, no session dependency. Verified with real account. |
| GitHub OAuth sign-in | ✅ Tested | Same stateless flow. Verified with real account. |
| GitHub integration connect | ✅ Tested | Same GitHub OAuth app, intent routed via signed `state` param |
| Deep-link redirect to mobile | ✅ | HTML page JS-redirects → `bugradar://oauth-callback?token=`; `redirect_url` encoded in state |
| OAuth state CSRF protection | ✅ | URL-safe base64 payload + HMAC-SHA256 signature; forged/tampered state rejected |
| Sanctum token issuance | ✅ | 30-day expiry, Bearer token |
| `GET /api/auth/user` | ✅ | Returns authenticated user |
| `POST /api/auth/logout` | ✅ | Deletes current access token |
| `GET /api/auth/dev-login` | ✅ | Local-env only. Supports `?email=` / `?user_id=` to log in as any real user |
| `GET /api/dev/auth/login` | ✅ | Alias route |
| Token refresh | ❌ | Post-MVP — user must re-auth after 30 days |

---

## 2. Platform Integrations

| Feature | Status | Notes |
|---|---|---|
| GitHub connect OAuth | ✅ | `GET /api/integrations/github/connect?token=xxx` |
| GitHub callback | ✅ | Reads `session('integration_user_id')`, saves raw token (model mutator encrypts) |
| GitLab connect OAuth | ✅ | **Fixed** — session stored correctly |
| GitLab callback | ✅ | **Fixed** — reads session, no more `$request->user()` null |
| Bitbucket connect OAuth | ✅ | **Fixed** — same session fix |
| Bitbucket callback | ✅ | **Fixed** — same session fix |
| `GET /api/integrations` — list | ✅ | Returns all user integrations |
| `DELETE /api/integrations/{id}` — disconnect | ✅ | Ownership check in place |
| `POST /api/integrations/{id}/sync` — manual sync | ✅ | Dispatches correct job per platform |
| Token encryption | ✅ | **Fixed** — model mutator is the single source of encryption/decryption; no manual `encrypt()`/`decrypt()` calls anywhere |
| Multi-account per platform | ⚠️ | UNIQUE `(user_id, platform)` — one account per platform only; post-MVP |
| Integration OAuth deep-link (`redirect_url`) | ✅ | All platforms bounce back to `bugradar://oauth-callback` via signed state |

---

## 2b. Repository Tracking (per-repo toggle) — ✅ NEW

| Feature | Status | Notes |
|---|---|---|
| `GET /integrations/{id}/repositories` | ✅ Tested | Lists platform repos with `tracked`/`webhook` flags |
| `POST /integrations/{id}/repositories/track` | ✅ Tested | Toggle ON → register webhook (best-effort) + sync |
| `DELETE /integrations/{id}/repositories/track` | ✅ Tested | Toggle OFF → remove webhook, **keep data (Option A)** |
| Platform-agnostic repo listing | ✅ | GitHub / GitLab / Bitbucket normalized to one shape |
| `tracked_repositories` table | ✅ | Stores toggle state, webhook id/secret (encrypted) |

## 2c. Webhooks (real-time) — ✅ ALL THREE PLATFORMS

### GitHub
| Feature | Status | Notes |
|---|---|---|
| Webhook registration on track | ✅ | Needs `admin:repo_hook` scope + repo admin; falls back to polling if not permitted |
| Webhook removal on untrack | ✅ | Best-effort delete |
| `POST /api/webhooks/github/{trackedRepository}` | ✅ Tested | HMAC-SHA256 via `X-Hub-Signature-256` |
| Events handled | ✅ Tested | `pull_request`, `issues`, `pull_request_review`, `ping` |

### GitLab
| Feature | Status | Notes |
|---|---|---|
| Webhook registration on track | ✅ Tested | Needs `api` scope + Maintainer on project; falls back to polling if denied |
| Webhook removal on untrack | ✅ | Best-effort delete |
| `POST /api/webhooks/gitlab/{trackedRepository}` | ✅ Tested | Plaintext token in `X-Gitlab-Token` header |
| Events handled | ✅ Tested | `Merge Request Hook` (open/merge/approve), `Issue Hook`, `Note Hook` (MR comments) |

### Bitbucket
| Feature | Status | Notes |
|---|---|---|
| Webhook registration on track | ✅ Tested | Needs `webhook` scope + repo admin; falls back to polling if denied |
| Webhook removal on untrack | ✅ | Best-effort delete; UUID handling |
| `POST /api/webhooks/bitbucket/{trackedRepository}` | ✅ Tested | HMAC-SHA256 via `X-Hub-Signature` (same format as GitHub) |
| Events handled | ✅ Tested | `pullrequest:created/updated/fulfilled/approved`, `issue:created/updated` |

### Notes
- All three platforms use the **same per-repo secret model** — each `tracked_repositories` row has its own signed secret, encrypted at rest.
- Approvals are recorded as `Review` rows for parity across platforms.
- Signature/token mismatch → 401 on all three (verified by tests).

## 2d. Push Notifications (FCM) — ✅ NEW

| Feature | Status | Notes |
|---|---|---|
| `POST /api/device-tokens` register | ✅ Tested | Idempotent; reassigns token across users |
| `DELETE /api/device-tokens` unregister | ✅ Tested | On logout |
| `device_tokens` table | ✅ | Per-user FCM tokens |
| FCM HTTP v1 sender | ✅ | RS256 JWT via openssl (no extra deps); OAuth token cached 55 min |
| Notification on webhook events | ✅ Tested | New PR / merged / new issue / bug / review |
| Graceful no-op when FCM unconfigured | ✅ | Logs instead of sending (safe for local dev) |
| Real FCM delivery | ⚠️ | Requires `FCM_CREDENTIALS` + `FCM_PROJECT_ID` set; not tested with live Firebase |

---

## 3. Background Sync Jobs

### GitHub (`SyncGitHubData`)
| Feature | Status | Notes |
|---|---|---|
| Sync PRs (author) | ✅ | `/search/issues?q=is:pr author:{user}` up to 100 |
| Sync issues (assigned) | ✅ | `/search/issues?q=is:issue assignee:{user}` up to 100 |
| Sync reviews (reviewed-by) | ✅ | Fetches reviews per PR, stores only current user's reviews |
| Priority & type detection from labels | ✅ | `critical/urgent/high/low` and `bug/feature/task` |
| SyncLog creation | ✅ | Tracks `running` → `success`/`failed`, counts, timestamps |
| Token decryption | ✅ | **Fixed** — no more `decrypt()` call; model mutator handles it |
| `json_encode` on arrays | ✅ | **Fixed** — passes raw arrays; model cast handles encoding |
| `author_username` (was null fallback) | ✅ | **Fixed** — defaults to `'unknown'` not `null` |

### GitLab (`SyncGitLabData`)
| Feature | Status | Notes |
|---|---|---|
| Sync merge requests | ✅ | Correct column names: `state`, `author_username`, `branch_from`, `branch_to` |
| Sync issues | ✅ | `state` mapped from `'opened'` → `'open'` |
| Sync reviews/approvals | ✅ Tested | Backfills each MR's approvers as `Review` rows in the same pass, using `project_id` + `iid` from the MR payload |
| SyncLog creation | ✅ | Consistent with GitHub job |
| Token decryption | ✅ | Uses model mutator |
| Instance URL | ✅ | Reads from `config('services.gitlab.base_uri')` |
| Approvals endpoint failure handling | ✅ Tested | 403/network error is logged; sync continues |

### Bitbucket (`SyncBitbucketData`)
| Feature | Status | Notes |
|---|---|---|
| Sync pull requests | ✅ | Correct column names, state mapping, avatar path |
| Sync issues | ✅ | Iterates user's repos, syncs issues from repos with `has_issues=true` |
| Sync reviews (approvals) | ✅ Tested | Extracts `approval` entries from each PR's activity feed as `Review` rows |
| SyncLog creation | ✅ | Consistent with GitHub/GitLab jobs |
| Token decryption | ✅ | Uses model mutator |
| Activity endpoint failure handling | ✅ | Errors logged; sync continues |

---

## 4. Pull Requests API

| Feature | Status | Notes |
|---|---|---|
| `GET /pull-requests` | ✅ | Filters: `status`, `repository`, `platform`, `sort_by`, `sort_order`, pagination |
| `GET /pull-requests/{id}` | ✅ | Ownership check, loads `integration` + `reviews` |
| `GET /pull-requests/reviewed` | ✅ | **Fixed** — correct nested `whereHas` via `integration` scope on PR |

---

## 5. Issues API

| Feature | Status | Notes |
|---|---|---|
| `GET /issues` | ✅ | Filters: `status`, `type`, `priority`, `repository`, `platform`, pagination |
| `GET /issues/{id}` | ✅ | Ownership check |
| `GET /issues/bugs` | ✅ | **Fixed** — default sort by `updated_at`; `sort_by=priority` uses `FIELD()` for correct order |
| `GET /issues/tasks` | ✅ | |

---

## 6. Reviews API

| Feature | Status | Notes |
|---|---|---|
| `GET /reviews` | ✅ | **Fixed** — field names corrected: `state` (not `status`), `submitted_at` (not `reviewed_at`) |
| `GET /reviews/{id}` | ✅ | Ownership via `pullRequest.integration` chain |
| `GET /reviews/stats` | ✅ | **Fixed** — same field name corrections |

---

## 7. Dashboard API

| Feature | Status | Notes |
|---|---|---|
| `GET /dashboard/stats` | ✅ | **Fixed** — `total_reviews` now scoped to current user |
| `GET /dashboard/recent` | ✅ | **Fixed** — reviews use `pullRequest.integration` chain; ordered by `submitted_at` |
| `pending_reviews` count | ⚠️ | Returns `0` — requires storing "review_requested" state per PR (post-MVP) |

---

## 8. Models

| Model | Status | Notes |
|---|---|---|
| `Integration` | ✅ | Mutators encrypt/decrypt `access_token` and `refresh_token` automatically |
| `PullRequest` | ✅ | `labels` cast to `array`; relations to `integration` and `reviews` |
| `Issue` | ✅ | `assignees` + `labels` cast to `array` |
| `Review` | ✅ | **Fixed** — `integration()` `hasOneThrough` relationship with correct key order |
| `SyncLog` | ✅ | |
| `OAuthProvider` | ✅ | |
| `User` | ✅ | `integrations()` hasMany |

---

## 9. Scheduled Auto-Sync

| Feature | Status | Notes |
|---|---|---|
| Periodic sync of all active integrations | ✅ | **Added** — runs every 30 minutes in `routes/console.php` via `Schedule::call()` with `withoutOverlapping()` |

---

## 10. Routes Summary (29 total)

```
GET  /api/auth/dev-login                          ← dev only (local env guard in controller)
GET  /api/dev/auth/login                          ← alias
GET  /api/auth/google                             ← no auth
GET  /api/auth/google/callback                    ← no auth
GET  /api/auth/github                             ← no auth
GET  /api/auth/github/callback                    ← no auth
GET  /api/integrations/github/connect             ← ?token= or Bearer
GET  /api/integrations/github/callback            ← session
GET  /api/integrations/gitlab/connect             ← ?token= or Bearer
GET  /api/integrations/gitlab/callback            ← session
GET  /api/integrations/bitbucket/connect          ← ?token= or Bearer
GET  /api/integrations/bitbucket/callback         ← session

── All below require Authorization: Bearer {token} ──
GET    /api/auth/user
POST   /api/auth/logout
GET    /api/integrations
DELETE /api/integrations/{id}
POST   /api/integrations/{id}/sync
GET    /api/pull-requests
GET    /api/pull-requests/reviewed
GET    /api/pull-requests/{id}
GET    /api/issues
GET    /api/issues/bugs
GET    /api/issues/tasks
GET    /api/issues/{id}
GET    /api/reviews
GET    /api/reviews/stats
GET    /api/reviews/{id}
GET    /api/dashboard/stats
GET    /api/dashboard/recent
```

---

## 11. Not Implemented (Post-MVP)

| Feature | Notes |
|---|---|
| CI/CD pipeline monitoring | GitHub Actions, GitLab CI, Bitbucket Pipelines, Jenkins, CircleCI |
| AI debugging assistant | Paste error → root cause, fix suggestions, PR summaries |
| Push notifications | Firebase FCM |
| Webhooks (inbound) | Real-time updates instead of polling |
| Jira / Trello / Linear / Asana | Additional issue tracker integrations |
| Personal Kanban board | User's own task board |
| Team features | Shared dashboards, team activity |
| Slack / Discord alerts | Outbound notifications |
| Token auto-refresh | Refresh OAuth tokens before expiry |
| Multi-account per platform | Requires removing UNIQUE constraint |
| `pending_reviews` in dashboard | Needs `review_requested` state on PRs |

---

## Deployment Checklist

```bash
# On server:
php artisan migrate
php artisan config:cache
php artisan route:cache

# Queue worker (keep alive with supervisor):
php artisan queue:work --queue=default --tries=3

# Scheduler (cron entry — runs every minute, scheduler picks what to run):
* * * * * cd /path/to/bugradar && php artisan schedule:run >> /dev/null 2>&1
```

### Required `.env` keys
```env
APP_ENV=production
APP_KEY=

DB_CONNECTION=mysql
DB_HOST=
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

QUEUE_CONNECTION=database

# App login OAuth
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=https://bugradar.laravue.in/api/auth/google/callback

GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
GITHUB_REDIRECT_URI=https://bugradar.laravue.in/api/auth/github/callback

# Platform integration OAuth (separate app registrations)
GITHUB_INTEGRATION_CLIENT_ID=
GITHUB_INTEGRATION_CLIENT_SECRET=

GITLAB_CLIENT_ID=
GITLAB_CLIENT_SECRET=
GITLAB_REDIRECT_URI=https://bugradar.laravue.in/api/integrations/gitlab/callback

BITBUCKET_CLIENT_ID=
BITBUCKET_CLIENT_SECRET=
BITBUCKET_REDIRECT_URI=https://bugradar.laravue.in/api/integrations/bitbucket/callback
```
