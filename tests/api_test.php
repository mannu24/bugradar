<?php
/**
 * BugRadar API Test Runner
 * Run: php tests/api_test.php
 */

$BASE = 'http://localhost:8000/api';
$pass = 0;
$fail = 0;

// Get a token first
$loginResp = json_decode(file_get_contents("$BASE/auth/dev-login"), true);
$token = $loginResp['token'] ?? null;

if (!$token) {
    echo "FATAL: Could not get dev login token. Is the server running?\n";
    exit(1);
}

echo "\nTOKEN: " . substr($token, 0, 20) . "...\n";

function req(string $method, string $url, string $token): array {
    $ctx = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => "Authorization: Bearer $token\r\nAccept: application/json\r\n",
            'ignore_errors' => true,
        ]
    ]);
    $body = file_get_contents($url, false, $ctx);
    return json_decode($body, true) ?? [];
}

function test(string $name, bool $result, string $details = ''): void {
    global $pass, $fail;
    if ($result) {
        echo "  \033[32mPASS\033[0m  $name\n";
        $pass++;
    } else {
        echo "  \033[31mFAIL\033[0m  $name" . ($details ? " | $details" : '') . "\n";
        $fail++;
    }
}

function http_status(string $url, string $token = ''): int {
    $headers = "Accept: application/json\r\n";
    if ($token) $headers .= "Authorization: Bearer $token\r\n";
    $ctx = stream_context_create(['http' => ['method' => 'GET', 'header' => $headers, 'ignore_errors' => true]]);
    file_get_contents($url, false, $ctx);
    preg_match('/HTTP\/\d\.\d (\d{3})/', $http_response_header[0] ?? '', $m);
    return (int)($m[1] ?? 0);
}

echo "\n" . str_repeat('═', 60) . "\n";
echo "  BugRadar Backend — Full Test Suite\n";
echo str_repeat('═', 60) . "\n";

// ─── AUTH ────────────────────────────────────────────────────────
echo "\n[ AUTH ]\n";
$r = req('GET', "$BASE/auth/user", $token);
test('GET /auth/user - returns user', isset($r['user']['email']) && $r['user']['email'] === 'test@bugradar.dev');

$r = req('GET', "$BASE/auth/dev-login", $token);
test('GET /auth/dev-login - success=true', $r['success'] === true && isset($r['token']));

// ─── INTEGRATIONS ────────────────────────────────────────────────
echo "\n[ INTEGRATIONS ]\n";
$r = req('GET', "$BASE/integrations", $token);
test('GET /integrations - returns 2', count($r['integrations'] ?? []) === 2);
test('GET /integrations - tokens hidden', !isset($r['integrations'][0]['access_token']));
test('GET /integrations - refresh_token hidden', !isset($r['integrations'][0]['refresh_token']));

$r = req('POST', "$BASE/integrations/1/sync", $token);
test('POST /integrations/1/sync - dispatches', $r['success'] === true && $r['message'] === 'Sync started');

// ─── DASHBOARD ───────────────────────────────────────────────────
echo "\n[ DASHBOARD ]\n";
$r = req('GET', "$BASE/dashboard/stats", $token);
test('GET /dashboard/stats - open_prs=3', ($r['stats']['open_prs'] ?? -1) === 3);
test('GET /dashboard/stats - assigned_issues=4', ($r['stats']['assigned_issues'] ?? -1) === 4);
test('GET /dashboard/stats - total_reviews=4', ($r['stats']['total_reviews'] ?? -1) === 4);
test('GET /dashboard/stats - charts prs_by_platform present', count($r['charts']['prs_by_platform'] ?? []) > 0);
test('GET /dashboard/stats - issues_by_priority present', count($r['charts']['issues_by_priority'] ?? []) > 0);

$r = req('GET', "$BASE/dashboard/recent", $token);
test('GET /dashboard/recent - recent_prs=4', count($r['recent_prs'] ?? []) === 4);
test('GET /dashboard/recent - recent_issues=5', count($r['recent_issues'] ?? []) === 5);
test('GET /dashboard/recent - recent_reviews=4', count($r['recent_reviews'] ?? []) === 4);
$firstReview = $r['recent_reviews'][0] ?? [];
test('GET /dashboard/recent - review has pull_request', isset($firstReview['pull_request']));

// ─── PULL REQUESTS ───────────────────────────────────────────────
echo "\n[ PULL REQUESTS ]\n";
$r = req('GET', "$BASE/pull-requests", $token);
test('GET /pull-requests - total=4', ($r['total'] ?? -1) === 4);
test('GET /pull-requests - paginated', isset($r['data']) && isset($r['current_page']));

$r = req('GET', "$BASE/pull-requests?status=open", $token);
test('GET /pull-requests?status=open - total=3', ($r['total'] ?? -1) === 3);

$r = req('GET', "$BASE/pull-requests?platform=github", $token);
test('GET /pull-requests?platform=github - total=3', ($r['total'] ?? -1) === 3);

$r = req('GET', "$BASE/pull-requests?platform=gitlab", $token);
test('GET /pull-requests?platform=gitlab - total=1', ($r['total'] ?? -1) === 1);

$r = req('GET', "$BASE/pull-requests?status=open&platform=github", $token);
test('GET /pull-requests?status=open&platform=github - total=2', ($r['total'] ?? -1) === 2);

$r = req('GET', "$BASE/pull-requests/reviewed", $token);
test('GET /pull-requests/reviewed - total=4', ($r['total'] ?? -1) === 4);
$firstPr = $r['data'][0] ?? [];
test('GET /pull-requests/reviewed - includes reviews array', isset($firstPr['reviews']));

$r = req('GET', "$BASE/pull-requests/1", $token);
test('GET /pull-requests/1 - has reviews', count($r['pull_request']['reviews'] ?? []) === 1);
test('GET /pull-requests/1 - labels decoded (array)', is_array($r['pull_request']['labels'] ?? null));
test('GET /pull-requests/1 - labels contain critical', in_array('critical', $r['pull_request']['labels'] ?? []));
test('GET /pull-requests/1 - integration loaded', isset($r['pull_request']['integration']));

// ─── ISSUES ──────────────────────────────────────────────────────
echo "\n[ ISSUES ]\n";
$r = req('GET', "$BASE/issues", $token);
test('GET /issues - total=5', ($r['total'] ?? -1) === 5);

$r = req('GET', "$BASE/issues?status=open", $token);
test('GET /issues?status=open - total=4', ($r['total'] ?? -1) === 4);

$r = req('GET', "$BASE/issues?type=bug", $token);
test('GET /issues?type=bug - total=3', ($r['total'] ?? -1) === 3);

$r = req('GET', "$BASE/issues?priority=critical", $token);
test('GET /issues?priority=critical - total=2', ($r['total'] ?? -1) === 2);

$r = req('GET', "$BASE/issues?platform=gitlab", $token);
test('GET /issues?platform=gitlab - total=1', ($r['total'] ?? -1) === 1);

$r = req('GET', "$BASE/issues/bugs", $token);
test('GET /issues/bugs - total=3', ($r['total'] ?? -1) === 3);

$r = req('GET', "$BASE/issues/tasks", $token);
test('GET /issues/tasks - total=1', ($r['total'] ?? -1) === 1);

$r = req('GET', "$BASE/issues/bugs?sort_by=priority", $token);
$priorities = array_column($r['data'] ?? [], 'priority');
test('GET /issues/bugs?sort_by=priority - critical first', ($priorities[0] ?? '') === 'critical');
test('GET /issues/bugs?sort_by=priority - high after critical', in_array('critical', $priorities) && !in_array('low', array_slice($priorities, 0, 2)));

$r = req('GET', "$BASE/issues/1", $token);
test('GET /issues/1 - type=bug', ($r['issue']['type'] ?? '') === 'bug');
test('GET /issues/1 - priority=critical', ($r['issue']['priority'] ?? '') === 'critical');
test('GET /issues/1 - assignees is array', is_array($r['issue']['assignees'] ?? null));
test('GET /issues/1 - integration loaded', isset($r['issue']['integration']));

// ─── REVIEWS ─────────────────────────────────────────────────────
echo "\n[ REVIEWS ]\n";
$r = req('GET', "$BASE/reviews", $token);
test('GET /reviews - total=4', ($r['total'] ?? -1) === 4);

$r = req('GET', "$BASE/reviews?status=approved", $token);
test('GET /reviews?status=approved - total=2', ($r['total'] ?? -1) === 2);

$r = req('GET', "$BASE/reviews?status=changes_requested", $token);
test('GET /reviews?status=changes_requested - total=1', ($r['total'] ?? -1) === 1);

$r = req('GET', "$BASE/reviews/stats", $token);
test('GET /reviews/stats - total_reviews=4', ($r['total_reviews'] ?? -1) === 4);
test('GET /reviews/stats - approved=2', ($r['approved'] ?? -1) === 2);
test('GET /reviews/stats - changes_requested=1', ($r['changes_requested'] ?? -1) === 1);
test('GET /reviews/stats - commented=1', ($r['commented'] ?? -1) === 1);
test('GET /reviews/stats - this_week=4', ($r['this_week'] ?? -1) === 4);

$r = req('GET', "$BASE/reviews/1", $token);
test('GET /reviews/1 - state=approved', ($r['review']['state'] ?? '') === 'approved');
test('GET /reviews/1 - has pull_request', isset($r['review']['pull_request']));
test('GET /reviews/1 - pull_request has title', isset($r['review']['pull_request']['title']));

// ─── SECURITY ────────────────────────────────────────────────────
echo "\n[ SECURITY ]\n";
$s = http_status("$BASE/pull-requests");
test('No token → 401 on /pull-requests', $s === 401, "got $s");

$s = http_status("$BASE/issues");
test('No token → 401 on /issues', $s === 401, "got $s");

$s = http_status("$BASE/dashboard/stats");
test('No token → 401 on /dashboard/stats', $s === 401, "got $s");

$s = http_status("$BASE/auth/dev-login");
test('GET /auth/dev-login accessible without token', $s === 200, "got $s");

// ─── LOGOUT ──────────────────────────────────────────────────────
echo "\n[ LOGOUT ]\n";
$r = req('POST', "$BASE/auth/logout", $token);
test('POST /auth/logout - success=true', $r['success'] === true);

$s = http_status("$BASE/auth/user", $token);
test('Token invalid after logout → 401', $s === 401, "got $s");

// ─── DESTRUCTIVE (run last — cascade deletes GitLab data) ────────
echo "\n[ DESTRUCTIVE / CLEANUP ]\n";
// Get a fresh token since we just logged out
$loginResp2 = json_decode(file_get_contents("$BASE/auth/dev-login"), true);
$token2 = $loginResp2['token'];

$r = req('DELETE', "$BASE/integrations/2", $token2);
test('DELETE /integrations/2 - disconnects GitLab', $r['success'] === true);

$r = req('GET', "$BASE/integrations", $token2);
test('GET /integrations - only 1 after delete', count($r['integrations'] ?? []) === 1);

// After GitLab integration deleted, PRs/issues/reviews cascade-deleted
$r = req('GET', "$BASE/pull-requests", $token2);
test('GET /pull-requests after gitlab delete - total=3', ($r['total'] ?? -1) === 3, "got ".($r['total']??'null'));

// ─── RESULTS ─────────────────────────────────────────────────────
echo "\n" . str_repeat('═', 60) . "\n";
echo "  RESULTS: \033[32m$pass passed\033[0m, " . ($fail > 0 ? "\033[31m$fail failed\033[0m" : "0 failed") . "\n";
echo str_repeat('═', 60) . "\n\n";

exit($fail > 0 ? 1 : 0);
