<?php
/**
 * Google OAuth 2.0 (OpenID Connect) for Sign-In / Sign-Up.
 *
 * In Google Cloud Console: APIs & Services → Credentials → Create OAuth 2.0 Client ID (Web application).
 * Authorized redirect URI must match exactly, e.g. http://localhost/abed-hub/handlers/google-oauth-callback.php
 *
 * Optional: set environment variables GOOGLE_OAUTH_CLIENT_ID and GOOGLE_OAUTH_CLIENT_SECRET.
 */

// --- Paste OAuth 2.0 Web client credentials here, or set GOOGLE_OAUTH_CLIENT_ID / GOOGLE_OAUTH_CLIENT_SECRET in the environment ---
$_GOOGLE_OAUTH_CLIENT_ID = '736704772351-ua7k4s710etntn96bl1j1aukng7do5g6.apps.googleusercontent.com';
$_GOOGLE_OAUTH_CLIENT_SECRET = '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';

$_env_oid = getenv('GOOGLE_OAUTH_CLIENT_ID');
if (is_string($_env_oid) && $_env_oid !== '') {
    $_GOOGLE_OAUTH_CLIENT_ID = $_env_oid;
}
$_env_osec = getenv('GOOGLE_OAUTH_CLIENT_SECRET');
if (is_string($_env_osec) && $_env_osec !== '') {
    $_GOOGLE_OAUTH_CLIENT_SECRET = $_env_osec;
}

define('GOOGLE_OAUTH_CLIENT_ID', $_GOOGLE_OAUTH_CLIENT_ID);
define('GOOGLE_OAUTH_CLIENT_SECRET', $_GOOGLE_OAUTH_CLIENT_SECRET);
unset($_GOOGLE_OAUTH_CLIENT_ID, $_GOOGLE_OAUTH_CLIENT_SECRET, $_env_oid, $_env_osec);

function google_oauth_allowed_office_units(): array
{
    return ['BKSP', 'LGED', 'APD', 'AFMAD', 'Other'];
}

function google_oauth_is_configured(): bool
{
    return GOOGLE_OAUTH_CLIENT_ID !== '' && GOOGLE_OAUTH_CLIENT_SECRET !== '';
}

/**
 * Public base URL for this app (scheme + host + path prefix), no trailing slash.
 */
function google_oauth_app_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $basePath = '';
    if (strpos($script, '/handlers/') !== false) {
        $basePath = dirname(dirname($script));
    } else {
        $basePath = dirname($script);
    }
    if ($basePath === '/' || $basePath === '.' || $basePath === '') {
        return $scheme . '://' . $host;
    }
    return $scheme . '://' . $host . $basePath;
}

function google_oauth_redirect_uri(): string
{
    return google_oauth_app_base_url() . '/handlers/google-oauth-callback.php';
}

function google_oauth_authorization_url(string $state): string
{
    $params = [
        'client_id' => GOOGLE_OAUTH_CLIENT_ID,
        'redirect_uri' => google_oauth_redirect_uri(),
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'prompt' => 'select_account',
    ];
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

/**
 * @return array<string, mixed>|null
 */
function google_oauth_exchange_code(string $code): ?array
{
    $post = [
        'code' => $code,
        'client_id' => GOOGLE_OAUTH_CLIENT_ID,
        'client_secret' => GOOGLE_OAUTH_CLIENT_SECRET,
        'redirect_uri' => google_oauth_redirect_uri(),
        'grant_type' => 'authorization_code',
    ];
    $body = google_oauth_http_post('https://oauth2.googleapis.com/token', $post);
    if ($body === null || $body === '') {
        return null;
    }
    $data = json_decode($body, true);
    return is_array($data) ? $data : null;
}

/**
 * @return array<string, mixed>|null
 */
function google_oauth_fetch_userinfo(string $accessToken): ?array
{
    $url = 'https://openidconnect.googleapis.com/v1/userinfo';
    $body = google_oauth_http_get($url, [
        'Authorization: Bearer ' . $accessToken,
    ]);
    if ($body === null || $body === '') {
        return null;
    }
    $data = json_decode($body, true);
    return is_array($data) ? $data : null;
}

function google_oauth_derive_username(mysqli $conn, string $email, string $googleSub): string
{
    $local = strstr($email, '@', true);
    if ($local === false || $local === '') {
        $local = 'user_' . substr(preg_replace('/\D/', '', $googleSub), 0, 12);
    }
    $local = preg_replace('/[^a-zA-Z0-9_]/', '_', $local);
    $local = trim((string) $local, '_');
    if (strlen($local) < 3) {
        $local .= '_' . substr(hash('sha256', $googleSub), 0, 6);
    }
    $base = substr($local, 0, 20);
    $candidate = $base;
    for ($n = 0; $n < 100; $n++) {
        $stmt = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $candidate);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if (!$exists) {
            return $candidate;
        }
        $suffix = '_' . ($n + 1);
        $candidate = substr($base, 0, max(1, 20 - strlen($suffix))) . $suffix;
    }
    return substr($base, 0, 10) . '_' . substr(hash('sha256', $googleSub), 0, 8);
}

function google_oauth_http_post(string $url, array $fields): ?string
{
    $body = http_build_query($fields);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($resp === false || $code >= 400) {
            return null;
        }
        return is_string($resp) ? $resp : null;
    }

    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $body,
            'timeout' => 20,
        ],
    ];
    $resp = @file_get_contents($url, false, stream_context_create($opts));
    return $resp !== false ? (string) $resp : null;
}

function google_oauth_http_get(string $url, array $headers): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($resp === false || $code >= 400) {
            return null;
        }
        return is_string($resp) ? $resp : null;
    }

    $hdr = implode("\r\n", $headers);
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => $hdr . "\r\n",
            'timeout' => 20,
        ],
    ];
    $resp = @file_get_contents($url, false, stream_context_create($opts));
    return $resp !== false ? (string) $resp : null;
}
