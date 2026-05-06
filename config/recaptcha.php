<?php
/**
 * Google reCAPTCHA v2 (checkbox) — keys and server-side verification.
 *
 * Get keys: https://www.google.com/recaptcha/admin
 *
 * --- INSERT YOUR KEYS BELOW (same pair for both login and signup) ---
 */
define('RECAPTCHA_SITE_KEY', '6LeOfsUsAAAAAAW_BIws2l8pJ-bnw-fqqepYZKaS');   // Public — safe in HTML
define('RECAPTCHA_SECRET_KEY', '6LeOfsUsAAAAAHuvXWoC3d2oMIY4hSYpfNkdRLlN'); // Private — never expose to browser

/**
 * Verify checkbox token with Google (call only from PHP handlers).
 *
 * @param string|null $response Value from POST field g-recaptcha-response
 * @param string|null $remote_ip Client IP (optional; forwarded for logging/score)
 * @return array{success:bool, error_codes?:array}
 */
function verify_recaptcha_v2(?string $response, ?string $remote_ip = null): array
{
    $response = is_string($response) ? trim($response) : '';
    if ($response === '') {
        return ['success' => false, 'error_codes' => ['missing-input-response']];
    }

    $post = [
        'secret'   => RECAPTCHA_SECRET_KEY,
        'response' => $response,
    ];
    if ($remote_ip !== null && filter_var($remote_ip, FILTER_VALIDATE_IP)) {
        $post['remoteip'] = $remote_ip;
    }

    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $body = false;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($post),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
    }

    if ($body === false || $body === '') {
        $opts = [
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($post),
                'timeout' => 10,
            ],
        ];
        $body = @file_get_contents($url, false, stream_context_create($opts));
    }

    if ($body === false || $body === '') {
        return ['success' => false, 'error_codes' => ['recaptcha-not-reachable']];
    }

    $data = json_decode($body, true);
    if (!is_array($data) || !isset($data['success'])) {
        return ['success' => false, 'error_codes' => ['invalid-json']];
    }

    $out = ['success' => (bool) $data['success']];
    if (!empty($data['error-codes'])) {
        $out['error_codes'] = $data['error-codes'];
    }
    return $out;
}
