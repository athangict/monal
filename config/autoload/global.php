<?php

/**
 * Global Configuration Override
 *
 * You can use this file for overriding configuration values from modules, etc.
 * You would place values in here that are agnostic to the environment and not
 * sensitive to security.
 *
 * NOTE: In practice, this file will typically be INCLUDED in your source
 * control, so do not include passwords or other sensitive information in this
 * file.
 */
use Laminas\Db\Adapter\AdapterAbstractServiceFactory;
use Laminas\Session\Storage\SessionArrayStorage;
use Laminas\Session\Validator\RemoteAddr;
use Laminas\Session\Validator\HttpUserAgent;

$env = static function (string $key, $default = null) {
    $value = getenv($key);
    return ($value === false || $value === '') ? $default : $value;
};

$appName = preg_replace('/[^A-Za-z0-9]+/', '', (string) $env('APP_NAME', 'MONAL'));
$erpTag = preg_replace('/[^A-Za-z0-9]+/', '', (string) $env('ERP_TAG', 'ERP'));
$client = preg_replace('/[^A-Za-z0-9]+/', '', (string) $env('APL_CLIENT', 'DEFAULT'));
$appEnv = strtolower(trim((string) $env('APP_ENV', 'local')));
$staticSaltOverride = trim((string) $env('STATIC_SALT', ''));
$legacyStaticSalt = trim((string) $env('LEGACY_STATIC_SALT', ''));
$initialDefaultPassword = trim((string) $env('INITIAL_DEFAULT_PASSWORD', ''));
$weakInitialPasswords = array_values(array_filter(array_map(
    'trim',
    explode(',', strtolower((string) $env('WEAK_INITIAL_PASSWORDS', '')))
), static function ($password) {
    return $password !== '';
}));

if ($initialDefaultPassword === '') {
    throw new \RuntimeException('INITIAL_DEFAULT_PASSWORD must be set in environment configuration.');
}

if ($appEnv === 'production') {
    if (! empty($weakInitialPasswords) && in_array(strtolower($initialDefaultPassword), $weakInitialPasswords, true)) {
        throw new \RuntimeException('Weak INITIAL_DEFAULT_PASSWORD is not allowed in production. Set a strong value in environment configuration.');
    }

    $isStrongInitialPassword = (bool) preg_match(
        '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{12,}$/',
        $initialDefaultPassword
    );

    if (! $isStrongInitialPassword) {
        throw new \RuntimeException('INITIAL_DEFAULT_PASSWORD in production must be at least 12 characters and include uppercase, lowercase, number, and symbol.');
    }
}

$dynamicStaticSalt = sprintf('%s-%s@APL_%s', $appName, $erpTag, $client);
$dynamicSessionName = sprintf('%sSession_%s', $appName, $client);
    
return [
    'session_validators' => [
        RemoteAddr::class,
        HttpUserAgent::class,
    ],
    'session_config' => [
        'remember_me_seconds' => 1209600, // 2 weeks
		//'cache_expire' =>5,
        'use_cookies' => true,
        'cookie_lifetime' => 1209600, // 2 weeks
        'name' => $dynamicSessionName,
    ],
    'session_storage' => [
        'type' => SessionArrayStorage::class,
    ],
    'static_salt' => ($staticSaltOverride !== '') ? $staticSaltOverride : $dynamicStaticSalt,
    'legacy_static_salt' => $legacyStaticSalt,
    'initial_default_password' => $initialDefaultPassword,
];