<?php

require_once 'config/oauth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$provider = $_GET['provider'] ?? '';
$mode = $_GET['mode'] ?? 'login';

if (!in_array($provider, ['google', 'facebook'], true)) {
    header('Location: login.php');
    exit;
}

if (!in_array($mode, ['login', 'register'], true)) {
    $mode = 'login';
}

$state = bin2hex(random_bytes(32));

$_SESSION['oauth_state'] = [
    'provider'   => $provider,
    'mode'       => $mode,
    'value'      => $state,
    'created_at' => time()
];

if ($provider === 'google') {

    $params = [
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
        'prompt'        => 'select_account'
    ];

    header(
        'Location: https://accounts.google.com/o/oauth2/v2/auth?'
        . http_build_query($params)
    );

    exit;
}

$params = [
    'client_id'     => FACEBOOK_APP_ID,
    'redirect_uri'  => FACEBOOK_REDIRECT_URI,
    'state'         => $state,
    'scope'         => 'email,public_profile'
];

header(
    'Location: https://www.facebook.com/'
    . FACEBOOK_GRAPH_VERSION
    . '/dialog/oauth?'
    . http_build_query($params)
);

exit;
