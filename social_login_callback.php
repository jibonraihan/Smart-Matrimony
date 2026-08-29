<?php

require_once 'config/db.php';
require_once 'config/oauth.php';
require_once 'includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function oauth_fail($message, $destination = 'login.php')
{
    $_SESSION['oauth_error'] = $message;

    header('Location: ' . $destination);
    exit;
}

function http_get_json($url, $headers = [])
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => $headers
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    curl_close($ch);

    if (
        $response === false ||
        $curlError ||
        $httpCode < 200 ||
        $httpCode >= 300
    ) {
        return null;
    }

    $data = json_decode($response, true);

    return is_array($data) ? $data : null;
}

function http_post_json($url, $data)
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded'
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    curl_close($ch);

    if (
        $response === false ||
        $curlError ||
        $httpCode < 200 ||
        $httpCode >= 300
    ) {
        return null;
    }

    $decoded = json_decode($response, true);

    return is_array($decoded) ? $decoded : null;
}

$provider = $_GET['provider'] ?? '';

if (!in_array($provider, ['google', 'facebook'], true)) {
    oauth_fail('Invalid social login provider.');
}

if (!isset($_GET['state'], $_SESSION['oauth_state'])) {
    oauth_fail('Social login session expired. Please try again.');
}

$storedState = $_SESSION['oauth_state'];

/*
 * Keep the mode before clearing the OAuth state.
 */
$mode = (
    ($storedState['mode'] ?? 'login') === 'register'
)
    ? 'register'
    : 'login';

$destination = ($mode === 'register')
    ? 'register.php'
    : 'login.php';

unset($_SESSION['oauth_state']);

if (
    !is_array($storedState) ||
    ($storedState['provider'] ?? '') !== $provider ||
    empty($storedState['value']) ||
    !hash_equals(
        $storedState['value'],
        $_GET['state']
    ) ||
    (time() - (int)($storedState['created_at'] ?? 0)) > 600
) {
    oauth_fail(
        'Invalid social login request. Please try again.',
        $destination
    );
}

if (isset($_GET['error'])) {
    oauth_fail(
        'Social login was cancelled or denied.',
        $destination
    );
}

if (empty($_GET['code'])) {
    oauth_fail(
        'No authorization code was returned.',
        $destination
    );
}

$code = $_GET['code'];

if ($provider === 'google') {

    $token = http_post_json(
        'https://oauth2.googleapis.com/token',
        [
            'code'          => $code,
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'grant_type'    => 'authorization_code'
        ]
    );

    if (
        !$token ||
        empty($token['access_token'])
    ) {
        oauth_fail(
            'Google authentication could not be completed.',
            $destination
        );
    }

    $profile = http_get_json(
        'https://openidconnect.googleapis.com/v1/userinfo',
        [
            'Authorization: Bearer '
            . $token['access_token']
        ]
    );

    if (
        !$profile ||
        empty($profile['sub']) ||
        empty($profile['email']) ||
        ($profile['email_verified'] ?? false) !== true
    ) {
        oauth_fail(
            'Google did not return a verified email address.',
            $destination
        );
    }

    $providerId = $profile['sub'];

    $email = strtolower(
        trim($profile['email'])
    );

    $firstName = trim(
        $profile['given_name'] ?? ''
    );

    $lastName = trim(
        $profile['family_name'] ?? ''
    );

    $column = 'google_id';

} else {

    $tokenUrl =
        'https://graph.facebook.com/'
        . FACEBOOK_GRAPH_VERSION
        . '/oauth/access_token';

    $token = http_get_json(
        $tokenUrl . '?' .
        http_build_query([
            'client_id'     => FACEBOOK_APP_ID,
            'client_secret' => FACEBOOK_APP_SECRET,
            'redirect_uri'  => FACEBOOK_REDIRECT_URI,
            'code'          => $code
        ])
    );

    if (
        !$token ||
        empty($token['access_token'])
    ) {
        oauth_fail(
            'Facebook authentication could not be completed.',
            $destination
        );
    }

    $profile = http_get_json(
        'https://graph.facebook.com/'
        . FACEBOOK_GRAPH_VERSION
        . '/me?' .
        http_build_query([
            'fields' => 'id,first_name,last_name,name,email',
            'access_token' => $token['access_token']
        ])
    );

    if (
        !$profile ||
        empty($profile['id'])
    ) {
        oauth_fail(
            'Facebook profile information could not be retrieved.',
            $destination
        );
    }

    $providerId = $profile['id'];

    $email = strtolower(
        trim($profile['email'] ?? '')
    );

    $firstName = trim(
        $profile['first_name'] ?? ''
    );

    $lastName = trim(
        $profile['last_name'] ?? ''
    );

    $column = 'facebook_id';
}

if ($email === '') {
    oauth_fail(
        'Your social account did not provide an email address.',
        $destination
    );
}


/*
 * ==========================================================
 * REGISTRATION FLOW
 * ==========================================================
 *
 * A social provider gives us verified identity information,
 * but Smart Matrimony still requires gender, mobile and a
 * password. Therefore we send the user back to register.php
 * with the provider data stored securely in the session.
 */

if ($mode === 'register') {

    /*
     * If this exact social account is already registered,
     * do not create a duplicate account.
     */
    $stmt = mysqli_prepare(
        $conn,
        "SELECT user_id
         FROM users
         WHERE {$column}=?
         LIMIT 1"
    );

    mysqli_stmt_bind_param(
        $stmt,
        's',
        $providerId
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    $alreadyLinked =
        $result &&
        mysqli_num_rows($result) === 1;

    mysqli_stmt_close($stmt);

    if ($alreadyLinked) {
        oauth_fail(
            'This ' . ucfirst($provider) .
            ' account is already registered. Please login instead.',
            'login.php'
        );
    }

    /*
     * Do not silently merge a new social registration with an
     * existing account that has the same email. The user can
     * use the normal login flow and link the social account there.
     */
    $stmt = mysqli_prepare(
        $conn,
        "SELECT user_id
         FROM users
         WHERE email=?
         LIMIT 1"
    );

    mysqli_stmt_bind_param(
        $stmt,
        's',
        $email
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    $emailExists =
        $result &&
        mysqli_num_rows($result) === 1;

    mysqli_stmt_close($stmt);

    if ($emailExists) {
        oauth_fail(
            'An account with this email already exists. Please login instead.',
            'login.php'
        );
    }

    $_SESSION['social_registration'] = [
        'provider'    => $provider,
        'provider_id' => $providerId,
        'email'       => $email,
        'first_name'  => $firstName,
        'last_name'   => $lastName,
        'created_at'  => time()
    ];

    header('Location: register.php?social=1');
    exit;
}


/*
 * ==========================================================
 * NORMAL SOCIAL LOGIN FLOW
 * ==========================================================
 */

/*
 * First: check whether this exact Google/Facebook account
 * is already linked.
 */
$user = null;

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        user_id,
        first_name,
        last_name,
        email,
        mobile,
        role,
        account_status
     FROM users
     WHERE {$column}=?
     LIMIT 1"
);

mysqli_stmt_bind_param(
    $stmt,
    's',
    $providerId
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if (
    $result &&
    mysqli_num_rows($result) === 1
) {
    $user = mysqli_fetch_assoc($result);
}

mysqli_stmt_close($stmt);


/*
 * If social ID is not linked yet, find the existing account
 * by verified email and link it.
 */
if (!$user) {

    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            user_id,
            first_name,
            last_name,
            email,
            mobile,
            role,
            account_status,
            google_id,
            facebook_id
         FROM users
         WHERE email=?
         LIMIT 1"
    );

    mysqli_stmt_bind_param(
        $stmt,
        's',
        $email
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if (
        $result &&
        mysqli_num_rows($result) === 1
    ) {
        $user = mysqli_fetch_assoc($result);
    }

    mysqli_stmt_close($stmt);

    if ($user) {

        if (
            !empty($user[$column]) &&
            $user[$column] !== $providerId
        ) {
            oauth_fail(
                'This social account is already linked to another account.'
            );
        }

        $stmt = mysqli_prepare(
            $conn,
            "UPDATE users
             SET {$column}=?
             WHERE user_id=?"
        );

        mysqli_stmt_bind_param(
            $stmt,
            'si',
            $providerId,
            $user['user_id']
        );

        mysqli_stmt_execute($stmt);

        mysqli_stmt_close($stmt);
    }
}


/*
 * Social account is not registered yet.
 */
if (!$user) {

    oauth_fail(
        'No Smart Matrimony account is linked to this ' .
        ucfirst($provider) .
        ' account. Please create an account first.'
    );
}

if ($user['account_status'] !== 'Active') {

    oauth_fail(
        'Your account is not active. Please contact support.'
    );
}


/*
 * Successful social login
 */
session_regenerate_id(true);

$_SESSION['user_id'] = $user['user_id'];
$_SESSION['first_name'] = $user['first_name'];
$_SESSION['last_name'] = $user['last_name'];
$_SESSION['role'] = $user['role'];

header('Location: home.php');
exit;
