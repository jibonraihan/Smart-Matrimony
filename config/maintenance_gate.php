<?php

/**
 * Central maintenance-mode state and access helpers.
 * The state is kept in a PHP file so the switch does not depend on MySQL.
 */

if (!function_exists('smart_maintenance_state_file')) {
    function smart_maintenance_state_file(): string
    {
        return __DIR__ . '/maintenance_state.php';
    }
}

if (!function_exists('smart_get_maintenance_state')) {
    function smart_get_maintenance_state(): array
    {
        $state_file = smart_maintenance_state_file();
        if (!is_file($state_file)) {
            return ['enabled' => false, 'expires_at' => null];
        }

        $state = include $state_file;
        if (is_bool($state)) {
            return ['enabled' => $state, 'expires_at' => null];
        }
        if (!is_array($state)) {
            return ['enabled' => false, 'expires_at' => null];
        }

        $enabled = !empty($state['enabled']);
        $expires_at = isset($state['expires_at']) && is_numeric($state['expires_at'])
            ? (int) $state['expires_at']
            : null;

        if ($enabled && $expires_at !== null && $expires_at <= time()) {
            return ['enabled' => false, 'expires_at' => $expires_at];
        }

        return ['enabled' => $enabled, 'expires_at' => $expires_at];
    }
}

if (!function_exists('smart_is_maintenance_mode')) {
    function smart_is_maintenance_mode(): bool
    {
        return smart_get_maintenance_state()['enabled'];
    }
}

if (!function_exists('smart_set_maintenance_mode')) {
    function smart_set_maintenance_mode(bool $enabled, ?int $expires_at = null): bool
    {
        $state_file = smart_maintenance_state_file();
        $temp_file = $state_file . '.tmp';
        $contents = "<?php\n\nreturn [\n"
            . "    'enabled' => " . ($enabled ? 'true' : 'false') . ",\n"
            . "    'expires_at' => " . ($expires_at !== null ? (string) $expires_at : 'null') . ",\n"
            . "];\n";

        if (@file_put_contents($temp_file, $contents, LOCK_EX) === false) {
            return false;
        }

        if (!@rename($temp_file, $state_file)) {
            @unlink($temp_file);
            return false;
        }

        return true;
    }
}

if (!function_exists('smart_maintenance_staff_bypass')) {
    function smart_maintenance_staff_bypass(): bool
    {
        $session_name = session_name();

        if ($session_name === 'SMART_ADMIN_SESSION') {
            return !empty($_SESSION['admin_user_id']);
        }

        if ($session_name === 'SMART_MANAGER_SESSION') {
            return !empty($_SESSION['user_id']);
        }

        if ($session_name === 'SMART_AUTH_SESSION') {
            return !empty($_SESSION['authenticator_user_id']);
        }

        return false;
    }
}

if (!function_exists('smart_maintenance_login_allowed')) {
    function smart_maintenance_login_allowed(): bool
    {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        return (bool) preg_match('~/(admin|manager|authenticator)/login\.php$~i', $script);
    }
}

if (!function_exists('smart_enforce_maintenance_mode')) {
    function smart_enforce_maintenance_mode(): void
    {
        $state = smart_get_maintenance_state();
        if (!$state['enabled']) {
            return;
        }

        if (smart_maintenance_staff_bypass() || smart_maintenance_login_allowed()) {
            return;
        }

        $remaining = $state['expires_at'] !== null
            ? max(60, $state['expires_at'] - time())
            : 3600;

        http_response_code(503);
        header('Retry-After: ' . $remaining);
        require dirname(__DIR__) . '/maintenance.php';
        exit;
    }
}
