<?php
namespace Auth\Service;

use Laminas\Http\Client;
use Laminas\Json\Json;
use Laminas\Session\Container;

class SSOProvisioningService
{
    private const ALLOWED_ROLES = [
        'TENANT_ADMIN',
        'TENANT_OPERATOR',
        'TENANT_AUDITOR',
        'MEMBER',
    ];

    private array $config;
    private Client $httpClient;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->httpClient = new Client();
    }

    public function isEnabled(): bool
    {
        if (array_key_exists('enabled', $this->config['sso'] ?? [])) {
            return (bool) $this->config['sso']['enabled'];
        }

        $envValue = getenv('SSO_ENABLED');
        if ($envValue !== false && $envValue !== null && trim((string) $envValue) !== '') {
            return (bool) filter_var((string) $envValue, FILTER_VALIDATE_BOOLEAN);
        }

        return false;
    }

    public function provisionUser(array $payload): array
    {
        if (! $this->isEnabled()) {
            return [
                'success' => true,
                'skipped' => true,
                'message' => 'SSO provisioning is disabled.',
            ];
        }

        $endpoint = $this->resolveUserCreateEndpoint();
        if ($endpoint === '') {
            throw new \RuntimeException('SSO user create endpoint is not configured.');
        }

        $headers = $this->buildProvisioningHeaders($endpoint);
        $requestBody = $this->normalizePayload($payload);

        $this->httpClient->resetParameters(true);
        $this->httpClient->setUri($endpoint);
        $this->httpClient->setMethod('POST');
        $this->httpClient->setHeaders($headers);
        $this->httpClient->setRawBody(Json::encode($requestBody));

        $response = $this->httpClient->send();
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($statusCode === 409) {
            $existingSsoUserId = $this->findExistingSsoUserIdByEmail(
                (string) ($requestBody['email'] ?? ''),
                $headers,
                $endpoint
            );
            return [
                'success' => true,
                'already_exists' => true,
                'sso_user_id' => $existingSsoUserId,
                'status_code' => $statusCode,
                'response' => $body,
            ];
        }

        if (! $response->isSuccess()) {
            throw new \RuntimeException(
                'SSO user creation failed (' . $statusCode . '): ' . $body
            );
        }

        return [
            'success' => true,
            'sso_user_id' => $this->extractSsoUserIdFromResponseBody($body),
            'status_code' => $statusCode,
            'response' => $body,
        ];
    }

    private function extractSsoUserIdFromResponseBody(string $body): string
    {
        if (trim($body) === '') {
            return '';
        }

        try {
            $decoded = Json::decode($body, Json::TYPE_ARRAY);
        } catch (\Throwable $e) {
            return '';
        }

        $candidates = [
            $decoded['id'] ?? null,
            $decoded['user']['id'] ?? null,
            $decoded['data']['id'] ?? null,
            $decoded['sub'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function findExistingSsoUserIdByEmail(string $email, array $headers, string $createEndpoint): string
    {
        $email = trim($email);
        if ($email === '') {
            return '';
        }

        $listEndpoint = $this->resolveUserListEndpoint($createEndpoint);
        if ($listEndpoint === '') {
            return '';
        }

        try {
            $this->httpClient->resetParameters(true);
            $this->httpClient->setUri($listEndpoint);
            $this->httpClient->setMethod('GET');
            $this->httpClient->setHeaders($headers);
            $this->httpClient->setParameterGet([
                'search' => $email,
                'page' => 1,
                'pageSize' => 25,
            ]);

            $response = $this->httpClient->send();
            if (! $response->isSuccess()) {
                return '';
            }

            $decoded = Json::decode($response->getBody(), Json::TYPE_ARRAY);
            $items = $decoded['items'] ?? [];
            if (!is_array($items)) {
                return '';
            }

            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $itemEmail = strtolower(trim((string) ($item['email'] ?? '')));
                if ($itemEmail !== strtolower($email)) {
                    continue;
                }
                $itemId = trim((string) ($item['id'] ?? ''));
                if ($itemId !== '') {
                    return $itemId;
                }
            }
        } catch (\Throwable $e) {
            return '';
        }

        return '';
    }

    private function resolveUserListEndpoint(string $createEndpoint): string
    {
        $createEndpoint = trim($createEndpoint);
        if ($createEndpoint === '') {
            return '';
        }

        if (stripos($createEndpoint, '/external/users') !== false) {
            return preg_replace('#/external/users$#i', '/users', $createEndpoint) ?: '';
        }

        if (stripos($createEndpoint, '/users') !== false) {
            return $createEndpoint;
        }

        return '';
    }

    private function buildProvisioningHeaders(string $endpoint): array
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];

        $isExternalProvisioningEndpoint = (stripos($endpoint, '/external/users') !== false);

        $apiKey = trim((string) ($this->config['sso']['user_api_key'] ?? getenv('SSO_USER_API_KEY') ?: ''));
        $apiKeyHeader = trim((string) ($this->config['sso']['user_api_key_header'] ?? getenv('SSO_USER_API_KEY_HEADER') ?: 'x-api-key'));
        if ($apiKeyHeader === '') {
            $apiKeyHeader = 'x-api-key';
        }

        if ($isExternalProvisioningEndpoint) {
            if ($apiKey === '') {
                throw new \RuntimeException(
                    'SSO external provisioning requires API key. Please set SSO_USER_API_KEY.'
                );
            }
            $headers[$apiKeyHeader] = $apiKey;
            return $headers;
        }

        $configuredBearerToken = trim((string) ($this->config['sso']['user_bearer_token'] ?? getenv('SSO_USER_BEARER_TOKEN') ?: ''));
        if ($configuredBearerToken !== '') {
            $headers['Authorization'] = 'Bearer ' . $configuredBearerToken;
            return $headers;
        }

        if ($apiKey !== '') {
            $headers[$apiKeyHeader] = $apiKey;
            return $headers;
        }

        $userToken = $this->getCurrentSsoUserAccessToken();
        if ($userToken !== '') {
            $headers['Authorization'] = 'Bearer ' . $userToken;
            return $headers;
        }

        // Optional fallback for environments that still allow service-account tokens.
        $allowServiceTokenFallback = (bool) filter_var(
            (string) ($this->config['sso']['allow_client_credentials_for_user_create'] ?? getenv('SSO_ALLOW_CLIENT_CREDENTIALS_FOR_USER_CREATE') ?: 'false'),
            FILTER_VALIDATE_BOOLEAN
        );
        if ($allowServiceTokenFallback) {
            $serviceToken = $this->fetchClientCredentialsAccessToken();
            $headers['Authorization'] = 'Bearer ' . $serviceToken;
            return $headers;
        }

        throw new \RuntimeException(
            'SSO user create requires a user token or API key. Set SSO_USER_BEARER_TOKEN or SSO_USER_API_KEY, or sign in via SSO with a tenant admin/operator account.'
        );
    }

    private function getCurrentSsoUserAccessToken(): string
    {
        $session = new Container('sso_auth');
        $tokenKey = (string) ($this->config['session_keys']['access_token'] ?? 'oidc_access_token');

        $token = '';
        if ($session->offsetExists($tokenKey)) {
            $token = trim((string) $session->offsetGet($tokenKey));
        }
        if ($token === '' && $session->offsetExists('access_token')) {
            $token = trim((string) $session->offsetGet('access_token'));
        }

        return $token;
    }

    private function fetchClientCredentialsAccessToken(): string
    {
        $tokenEndpoint = trim((string) ($this->config['sso']['token_endpoint'] ?? ''));
        $clientId = trim((string) ($this->config['sso']['client_id'] ?? ''));
        $clientSecret = trim((string) ($this->config['sso']['client_secret'] ?? ''));

        if ($tokenEndpoint === '' || $clientId === '' || $clientSecret === '') {
            throw new \RuntimeException('SSO token endpoint/client credentials are not configured.');
        }

        $postParams = [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ];

        $scope = trim((string) ($this->config['sso']['admin_scope'] ?? ''));
        if ($scope !== '') {
            $postParams['scope'] = $scope;
        }

        $this->httpClient->resetParameters(true);
        $this->httpClient->setUri($tokenEndpoint);
        $this->httpClient->setMethod('POST');
        $this->httpClient->setParameterPost($postParams);

        $response = $this->httpClient->send();
        if (! $response->isSuccess()) {
            throw new \RuntimeException('Failed to fetch SSO service token: ' . $response->getBody());
        }

        $tokenResponse = Json::decode($response->getBody(), Json::TYPE_ARRAY);
        $accessToken = trim((string) ($tokenResponse['access_token'] ?? ''));
        if ($accessToken === '') {
            throw new \RuntimeException('SSO service-token response did not include access_token.');
        }

        return $accessToken;
    }

    private function resolveUserCreateEndpoint(): string
    {
        $configured = trim((string) ($this->config['sso']['user_create_endpoint'] ?? ''));
        if ($configured !== '') {
            return $configured;
        }

        $openidConfigUri = trim((string) ($this->config['sso']['openid_configuration_uri'] ?? ''));
        if (preg_match('#^(https?://[^/]+)/api/(?:auth|console)/t/([^/]+)/\.well-known/openid-configuration$#i', $openidConfigUri, $matches)) {
            return $matches[1] . '/api/console/t/' . $matches[2] . '/external/users';
        }

        $authorizationEndpoint = trim((string) ($this->config['sso']['authorization_endpoint'] ?? ''));
        if (preg_match('#^(https?://[^/]+)/auth/t/([^/]+)/#i', $authorizationEndpoint, $matches)) {
            return $matches[1] . '/api/console/t/' . $matches[2] . '/external/users';
        }

        $tokenEndpoint = trim((string) ($this->config['sso']['token_endpoint'] ?? ''));
        if (preg_match('#^(https?://[^/]+)/api/(?:auth|console)/t/([^/]+)/#i', $tokenEndpoint, $matches)) {
            return $matches[1] . '/api/console/t/' . $matches[2] . '/external/users';
        }

        return '';
    }

    private function normalizePayload(array $payload): array
    {
        $email = trim((string) ($payload['email'] ?? ''));
        if ($email === '') {
            throw new \RuntimeException('Email is required for SSO user creation.');
        }

        $username = trim((string) ($payload['username'] ?? ''));
        if ($username === '') {
            $username = strstr($email, '@', true) ?: $email;
        }

        $firstName = trim((string) ($payload['firstName'] ?? ''));
        $lastName = trim((string) ($payload['lastName'] ?? ''));
        $phoneNumber = trim((string) ($payload['phoneNumber'] ?? ''));

        $role = strtoupper(trim((string) ($payload['role'] ?? 'MEMBER')));
        if (! in_array($role, self::ALLOWED_ROLES, true)) {
            $role = 'MEMBER';
        }

        $normalized = [
            'username' => substr($username, 0, 190),
            'email' => substr($email, 0, 320),
            'firstName' => substr($firstName, 0, 120),
            'lastName' => substr($lastName, 0, 120),
            'phoneNumber' => substr($phoneNumber, 0, 40),
            'role' => $role,
        ];

        $password = trim((string) ($payload['password'] ?? ''));
        if ($password !== '') {
            $normalized['password'] = $password;
        }

        return $normalized;
    }
}
