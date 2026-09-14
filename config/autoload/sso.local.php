<?php

$env = static function (string $key, $default = null) {
    $value = getenv($key);
    return ($value === false || $value === '') ? $default : $value;
};

$ssoEnabled = filter_var((string) $env('SSO_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN);

return [

    'openid_config'=> [],
    'sso' => [
        'enabled' => $ssoEnabled,
        'client_id' => $env('SSO_CLIENT_ID', 'mythimphu_2026'),
        'client_secret' => $env('SSO_CLIENT_SECRET', ''),
        'redirect_uri' => $env('SSO_REDIRECT_URI', 'http://localhost/monalv8.2/public/auth/callback'),
        'openid_configuration_uri' => $env('SSO_OPENID_CONFIGURATION_URI', 'https://sso.athang.com/api/auth/t/mythimphu/.well-known/openid-configuration'),
        'authorization_endpoint' => $env('SSO_AUTHORIZATION_ENDPOINT', 'https://sso.athang.com/auth/t/mythimphu/protocol/openid-connect/auth'),
        'token_endpoint' => $env('SSO_TOKEN_ENDPOINT', 'https://sso.athang.com/api/auth/t/mythimphu/protocol/openid-connect/token'),
        'userinfo_endpoint' => $env('SSO_USERINFO_ENDPOINT', 'https://sso.athang.com/api/auth/t/mythimphu/protocol/openid-connect/userinfo'),
        'jwks_uri' => $env('SSO_JWKS_URI', 'https://sso.athang.com/api/auth/t/mythimphu/.well-known/jwks.json'),
        'user_create_endpoint' => $env('SSO_USER_CREATE_ENDPOINT', ''),
        'admin_scope' => $env('SSO_ADMIN_SCOPE', ''),
        'user_bearer_token' => $env('SSO_USER_BEARER_TOKEN', ''),
        'user_api_key' => $env('SSO_USER_API_KEY', ''),
        'user_api_key_header' => $env('SSO_USER_API_KEY_HEADER', 'x-api-key'),
        'allow_client_credentials_for_user_create' => filter_var((string) $env('SSO_ALLOW_CLIENT_CREDENTIALS_FOR_USER_CREATE', 'false'), FILTER_VALIDATE_BOOLEAN),
        'logoutUri' => $env('SSO_LOGOUT_URI', 'https://sso.athang.com'),
        'validIssuers' => $env('SSO_VALID_ISSUERS', 'https://sso.athang.com/api/auth/t/mythimphu,https://sso.athang.com/api/console/t/mythimphu,https://sso.athang.com/t/mythimphu'),
        'logoutEndpoint' => $env('SSO_LOGOUT_ENDPOINT', 'https://sso.athang.com/api/auth/t/mythimphu/protocol/openid-connect/logout'),
        'postLogoutRedirectUri' => $env('SSO_POST_LOGOUT_REDIRECT_URI', 'https://admin.one.athang.com'),
    ],
    'session_keys'           => [
        'state'         => 'oidc_state',
        'code_verifier' => 'oidc_code_verifier',
        'access_token'  => 'oidc_access_token',
        'id_token'      => 'oidc_id_token',
        'refresh_token' => 'oidc_refresh_token',
    ],

];