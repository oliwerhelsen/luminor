---
title: OpenID Connect (SSO) Tutorial
layout: default
parent: Authentication
nav_order: 3
description: "Learn how to implement Single Sign-On (SSO) with OpenID Connect providers"
---

# OpenID Connect (SSO) Tutorial

This tutorial guides you through implementing Single Sign-On (SSO) with OpenID Connect providers like Azure AD, Google, and Okta.

## What You'll Learn

- How OpenID Connect authentication works
- Setting up SSO with Azure AD (Microsoft 365)
- Setting up SSO with Google Workspace
- Setting up SSO with Okta
- Handling the OAuth callback flow
- Managing user accounts from OIDC claims

## Prerequisites

- Luminor framework installed
- An account with your identity provider (Azure AD, Google, or Okta)
- A registered OAuth application with your provider
- HTTPS enabled (required for OAuth callbacks)

---

## Understanding OpenID Connect Flow

```
┌──────────┐     ┌──────────────┐     ┌──────────────────┐
│  User    │     │  Your App    │     │  Identity        │
│  Browser │     │  (Luminor)   │     │  Provider        │
└────┬─────┘     └──────┬───────┘     └────────┬─────────┘
     │                  │                      │
     │ 1. Click Login   │                      │
     │─────────────────>│                      │
     │                  │                      │
     │ 2. Redirect to IdP                      │
     │<─────────────────│                      │
     │                  │                      │
     │ 3. Login at IdP  │                      │
     │─────────────────────────────────────────>
     │                  │                      │
     │ 4. Return with authorization code       │
     │<────────────────────────────────────────│
     │                  │                      │
     │ 5. Send code to callback                │
     │─────────────────>│                      │
     │                  │                      │
     │                  │ 6. Exchange code     │
     │                  │  for tokens          │
     │                  │─────────────────────>│
     │                  │                      │
     │                  │ 7. Receive tokens    │
     │                  │<─────────────────────│
     │                  │                      │
     │                  │ 8. Fetch user info   │
     │                  │─────────────────────>│
     │                  │                      │
     │                  │ 9. Receive user data │
     │                  │<─────────────────────│
     │                  │                      │
     │ 10. Login complete                      │
     │<─────────────────│                      │
```

---

## Part 1: Azure AD (Microsoft 365)

### Step 1.1: Register Application in Azure Portal

1. Go to [Azure Portal](https://portal.azure.com)
2. Navigate to **Azure Active Directory** > **App registrations**
3. Click **New registration**
4. Configure:
   - **Name**: Your app name
   - **Supported account types**: Choose based on your needs
   - **Redirect URI**: `https://yourapp.com/auth/azure/callback`
5. Note the **Application (client) ID** and **Directory (tenant) ID**
6. Go to **Certificates & secrets** > **New client secret**
7. Note the **secret value** (shown only once)

### Step 1.2: Configure Environment Variables

```bash
# .env
AZURE_TENANT_ID="12345678-1234-1234-1234-123456789abc"
AZURE_CLIENT_ID="your-client-id"
AZURE_CLIENT_SECRET="your-client-secret"
AZURE_REDIRECT_URI="https://yourapp.com/auth/azure/callback"
```

### Step 1.3: Create Azure SSO Controller

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Luminor\Infrastructure\Http\ApiController;
use Luminor\Auth\OpenId\OpenIdProvider;
use Luminor\Auth\OpenId\OpenIdService;
use Luminor\Auth\AuthenticationException;
use Luminor\Session\Session;
use App\Domain\User\User;
use App\Domain\User\UserRepository;
use Luminor\Http\Request;
use Luminor\Http\Response;

final class AzureSsoController extends ApiController
{
    private OpenIdService $oidcService;

    public function __construct(
        private Session $session,
        private UserRepository $userRepository,
    ) {
        // Configure Azure AD provider
        $provider = OpenIdProvider::azure(
            tenantId: $_ENV['AZURE_TENANT_ID'],
            clientId: $_ENV['AZURE_CLIENT_ID'],
            clientSecret: $_ENV['AZURE_CLIENT_SECRET'],
            redirectUri: $_ENV['AZURE_REDIRECT_URI']
        );

        $this->oidcService = new OpenIdService($provider);
    }

    /**
     * GET /auth/azure
     * Redirect user to Azure AD login
     */
    public function redirect(Request $request, Response $response): Response
    {
        // Generate state and nonce for security
        $state = bin2hex(random_bytes(16));
        $nonce = bin2hex(random_bytes(16));

        // Store in session to verify on callback
        $this->session->set('oidc_state', $state);
        $this->session->set('oidc_nonce', $nonce);

        // Optional: Store the intended destination
        $returnTo = $request->getParam('return_to', '/dashboard');
        $this->session->set('oidc_return_to', $returnTo);

        // Generate authorization URL
        $authUrl = $this->oidcService->getAuthorizationUrl($state, $nonce);

        return $this->redirect($response, $authUrl);
    }

    /**
     * GET /auth/azure/callback
     * Handle Azure AD callback
     */
    public function callback(Request $request, Response $response): Response
    {
        // Check for errors from Azure
        if ($error = $request->getParam('error')) {
            $errorDescription = $request->getParam('error_description', 'Unknown error');
            return $this->error($response, "Authentication failed: {$errorDescription}", 400);
        }

        // Verify state parameter (CSRF protection)
        $state = $request->getParam('state');
        $savedState = $this->session->get('oidc_state');

        if (!$state || !$savedState || !hash_equals($savedState, $state)) {
            return $this->error($response, 'Invalid state parameter', 400);
        }

        // Get authorization code
        $code = $request->getParam('code');

        if (!$code) {
            return $this->error($response, 'Authorization code missing', 400);
        }

        try {
            // Exchange code for tokens
            $tokens = $this->oidcService->exchangeCode($code);

            // Fetch user info from Azure
            $userInfo = $this->oidcService->getUserInfo($tokens['access_token']);

            // Find or create user
            $user = $this->findOrCreateUser($userInfo, $tokens);

            // Clear OIDC session data
            $this->session->remove('oidc_state');
            $this->session->remove('oidc_nonce');

            // Log the user in (set session)
            $this->session->set('user_id', $user->getId());
            $this->session->regenerate();

            // Redirect to intended destination
            $returnTo = $this->session->get('oidc_return_to', '/dashboard');
            $this->session->remove('oidc_return_to');

            return $this->redirect($response, $returnTo);

        } catch (\Exception $e) {
            return $this->error($response, 'Authentication failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Find existing user or create new one from OIDC claims
     */
    private function findOrCreateUser(array $userInfo, array $tokens): User
    {
        $email = $userInfo['email'] ?? $userInfo['preferred_username'];

        // Try to find existing user
        $user = $this->userRepository->findByEmail($email);

        if ($user) {
            // Update user info from Azure
            $user->setName($userInfo['name'] ?? $user->getName());
            $user->setAzureId($userInfo['sub']);
            $user->setAzureAccessToken($tokens['access_token']);

            if (isset($tokens['refresh_token'])) {
                $user->setAzureRefreshToken($tokens['refresh_token']);
            }

            $this->userRepository->save($user);

            return $user;
        }

        // Create new user
        $user = User::createFromOidc(
            name: $userInfo['name'] ?? $email,
            email: $email,
            azureId: $userInfo['sub'],
            emailVerified: $userInfo['email_verified'] ?? true
        );

        $user->setAzureAccessToken($tokens['access_token']);

        if (isset($tokens['refresh_token'])) {
            $user->setAzureRefreshToken($tokens['refresh_token']);
        }

        $this->userRepository->save($user);

        return $user;
    }

    private function redirect(Response $response, string $url): Response
    {
        $response->addHeader('Location', $url);
        $response->setStatusCode(302);
        return $response;
    }
}
```

### Step 1.4: Configure Routes

```php
// routes/web.php
$router->get('/auth/azure', [AzureSsoController::class, 'redirect']);
$router->get('/auth/azure/callback', [AzureSsoController::class, 'callback']);
```

### Step 1.5: Frontend Integration

```html
<!-- Login page -->
<a href="/auth/azure" class="btn btn-microsoft">
  <svg><!-- Microsoft icon --></svg>
  Sign in with Microsoft
</a>

<!-- Or with JavaScript for SPA -->
<script>
  function loginWithAzure() {
    // Store current URL to return after login
    const returnTo = encodeURIComponent(window.location.pathname);
    window.location.href = `/auth/azure?return_to=${returnTo}`;
  }
</script>
```

---

## Part 2: Google Workspace

### Step 2.1: Create OAuth Credentials

1. Go to [Google Cloud Console](https://console.cloud.google.com)
2. Create a new project or select existing
3. Navigate to **APIs & Services** > **Credentials**
4. Click **Create Credentials** > **OAuth client ID**
5. Configure:
   - **Application type**: Web application
   - **Authorized redirect URIs**: `https://yourapp.com/auth/google/callback`
6. Note the **Client ID** and **Client Secret**

### Step 2.2: Configure Environment

```bash
# .env
GOOGLE_CLIENT_ID="your-client-id.apps.googleusercontent.com"
GOOGLE_CLIENT_SECRET="your-client-secret"
GOOGLE_REDIRECT_URI="https://yourapp.com/auth/google/callback"
```

### Step 2.3: Create Google SSO Controller

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Luminor\Infrastructure\Http\ApiController;
use Luminor\Auth\OpenId\OpenIdProvider;
use Luminor\Auth\OpenId\OpenIdService;
use Luminor\Session\Session;
use App\Domain\User\UserRepository;
use Luminor\Http\Request;
use Luminor\Http\Response;

final class GoogleSsoController extends ApiController
{
    private OpenIdService $oidcService;

    public function __construct(
        private Session $session,
        private UserRepository $userRepository,
    ) {
        $provider = OpenIdProvider::google(
            clientId: $_ENV['GOOGLE_CLIENT_ID'],
            clientSecret: $_ENV['GOOGLE_CLIENT_SECRET'],
            redirectUri: $_ENV['GOOGLE_REDIRECT_URI']
        );

        $this->oidcService = new OpenIdService($provider);
    }

    /**
     * GET /auth/google
     */
    public function redirect(Request $request, Response $response): Response
    {
        $state = bin2hex(random_bytes(16));
        $nonce = bin2hex(random_bytes(16));

        $this->session->set('oidc_state', $state);
        $this->session->set('oidc_nonce', $nonce);

        $authUrl = $this->oidcService->getAuthorizationUrl($state, $nonce);

        $response->addHeader('Location', $authUrl);
        $response->setStatusCode(302);
        return $response;
    }

    /**
     * GET /auth/google/callback
     */
    public function callback(Request $request, Response $response): Response
    {
        // Verify state
        $state = $request->getParam('state');
        $savedState = $this->session->get('oidc_state');

        if (!$state || !hash_equals($savedState, $state)) {
            return $this->error($response, 'Invalid state', 400);
        }

        $code = $request->getParam('code');

        try {
            $tokens = $this->oidcService->exchangeCode($code);
            $userInfo = $this->oidcService->getUserInfo($tokens['access_token']);

            // Create or update user
            $user = $this->userRepository->findByEmail($userInfo['email']);

            if (!$user) {
                $user = User::createFromOidc(
                    name: $userInfo['name'],
                    email: $userInfo['email'],
                    googleId: $userInfo['sub'],
                    picture: $userInfo['picture'] ?? null,
                    emailVerified: $userInfo['email_verified'] ?? false
                );
                $this->userRepository->save($user);
            }

            // Log in
            $this->session->set('user_id', $user->getId());
            $this->session->remove('oidc_state');
            $this->session->remove('oidc_nonce');
            $this->session->regenerate();

            $response->addHeader('Location', '/dashboard');
            $response->setStatusCode(302);
            return $response;

        } catch (\Exception $e) {
            return $this->error($response, 'Authentication failed', 500);
        }
    }
}
```

---

## Part 3: Okta

### Step 3.1: Configure Okta Application

1. Log in to your Okta Admin Console
2. Go to **Applications** > **Create App Integration**
3. Select **OIDC - OpenID Connect**
4. Select **Web Application**
5. Configure:
   - **Sign-in redirect URIs**: `https://yourapp.com/auth/okta/callback`
   - **Sign-out redirect URIs**: `https://yourapp.com`
6. Note the **Client ID** and **Client Secret**
7. Note your **Okta domain** (e.g., `dev-123456.okta.com`)

### Step 3.2: Configure Environment

```bash
# .env
OKTA_DOMAIN="dev-123456.okta.com"
OKTA_CLIENT_ID="your-client-id"
OKTA_CLIENT_SECRET="your-client-secret"
OKTA_REDIRECT_URI="https://yourapp.com/auth/okta/callback"
```

### Step 3.3: Create Okta SSO Controller

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Luminor\Auth\OpenId\OpenIdProvider;
use Luminor\Auth\OpenId\OpenIdService;
use Luminor\Infrastructure\Http\ApiController;
use Luminor\Session\Session;
use App\Domain\User\UserRepository;
use Luminor\Http\Request;
use Luminor\Http\Response;

final class OktaSsoController extends ApiController
{
    private OpenIdService $oidcService;

    public function __construct(
        private Session $session,
        private UserRepository $userRepository,
    ) {
        $provider = OpenIdProvider::okta(
            domain: $_ENV['OKTA_DOMAIN'],
            clientId: $_ENV['OKTA_CLIENT_ID'],
            clientSecret: $_ENV['OKTA_CLIENT_SECRET'],
            redirectUri: $_ENV['OKTA_REDIRECT_URI']
        );

        $this->oidcService = new OpenIdService($provider);
    }

    public function redirect(Request $request, Response $response): Response
    {
        $state = bin2hex(random_bytes(16));
        $nonce = bin2hex(random_bytes(16));

        $this->session->set('oidc_state', $state);
        $this->session->set('oidc_nonce', $nonce);

        $authUrl = $this->oidcService->getAuthorizationUrl($state, $nonce);

        $response->addHeader('Location', $authUrl);
        $response->setStatusCode(302);
        return $response;
    }

    public function callback(Request $request, Response $response): Response
    {
        // Same callback logic as Azure/Google...
        // (abbreviated for brevity)
    }
}
```

---

## Part 4: Custom OIDC Provider

For any OIDC-compliant provider:

```php
<?php

$provider = OpenIdProvider::custom(
    name: 'custom-provider',
    clientId: $_ENV['CUSTOM_CLIENT_ID'],
    clientSecret: $_ENV['CUSTOM_CLIENT_SECRET'],
    redirectUri: $_ENV['CUSTOM_REDIRECT_URI'],
    discoveryUrl: 'https://provider.com/.well-known/openid-configuration'
);

// Or manually configure endpoints
$provider = new OpenIdProvider(
    name: 'custom-provider',
    clientId: $_ENV['CUSTOM_CLIENT_ID'],
    clientSecret: $_ENV['CUSTOM_CLIENT_SECRET'],
    redirectUri: $_ENV['CUSTOM_REDIRECT_URI'],
    authorizationEndpoint: 'https://provider.com/oauth/authorize',
    tokenEndpoint: 'https://provider.com/oauth/token',
    userInfoEndpoint: 'https://provider.com/oauth/userinfo',
    scopes: ['openid', 'profile', 'email']
);
```

---

## Part 5: Multi-Provider Support

Supporting multiple SSO providers:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Luminor\Auth\OpenId\OpenIdProvider;
use Luminor\Auth\OpenId\OpenIdService;
use Luminor\Infrastructure\Http\ApiController;
use Luminor\Http\Request;
use Luminor\Http\Response;

final class SsoController extends ApiController
{
    private array $providers = [];

    public function __construct(
        private Session $session,
        private UserRepository $userRepository,
    ) {
        // Initialize available providers
        if ($_ENV['AZURE_CLIENT_ID'] ?? false) {
            $this->providers['azure'] = OpenIdProvider::azure(
                tenantId: $_ENV['AZURE_TENANT_ID'],
                clientId: $_ENV['AZURE_CLIENT_ID'],
                clientSecret: $_ENV['AZURE_CLIENT_SECRET'],
                redirectUri: $_ENV['AZURE_REDIRECT_URI']
            );
        }

        if ($_ENV['GOOGLE_CLIENT_ID'] ?? false) {
            $this->providers['google'] = OpenIdProvider::google(
                clientId: $_ENV['GOOGLE_CLIENT_ID'],
                clientSecret: $_ENV['GOOGLE_CLIENT_SECRET'],
                redirectUri: $_ENV['GOOGLE_REDIRECT_URI']
            );
        }

        if ($_ENV['OKTA_CLIENT_ID'] ?? false) {
            $this->providers['okta'] = OpenIdProvider::okta(
                domain: $_ENV['OKTA_DOMAIN'],
                clientId: $_ENV['OKTA_CLIENT_ID'],
                clientSecret: $_ENV['OKTA_CLIENT_SECRET'],
                redirectUri: $_ENV['OKTA_REDIRECT_URI']
            );
        }
    }

    /**
     * GET /auth/{provider}
     */
    public function redirect(Request $request, Response $response, string $provider): Response
    {
        if (!isset($this->providers[$provider])) {
            return $this->error($response, 'Unknown provider', 404);
        }

        $oidcService = new OpenIdService($this->providers[$provider]);

        $state = bin2hex(random_bytes(16));
        $nonce = bin2hex(random_bytes(16));

        $this->session->set('oidc_state', $state);
        $this->session->set('oidc_nonce', $nonce);
        $this->session->set('oidc_provider', $provider);

        $authUrl = $oidcService->getAuthorizationUrl($state, $nonce);

        $response->addHeader('Location', $authUrl);
        $response->setStatusCode(302);
        return $response;
    }

    /**
     * GET /auth/{provider}/callback
     */
    public function callback(Request $request, Response $response, string $provider): Response
    {
        $savedProvider = $this->session->get('oidc_provider');

        if ($savedProvider !== $provider || !isset($this->providers[$provider])) {
            return $this->error($response, 'Invalid provider', 400);
        }

        // Continue with callback logic...
    }

    /**
     * Get available providers for frontend
     * GET /auth/providers
     */
    public function providers(Request $request, Response $response): Response
    {
        $available = [];

        foreach (array_keys($this->providers) as $name) {
            $available[] = [
                'name' => $name,
                'url' => "/auth/{$name}",
                'label' => match($name) {
                    'azure' => 'Sign in with Microsoft',
                    'google' => 'Sign in with Google',
                    'okta' => 'Sign in with Okta',
                    default => "Sign in with {$name}",
                },
            ];
        }

        return $this->success($response, ['providers' => $available]);
    }
}
```

### Routes for Multi-Provider

```php
// routes/web.php
$router->get('/auth/providers', [SsoController::class, 'providers']);
$router->get('/auth/{provider}', [SsoController::class, 'redirect']);
$router->get('/auth/{provider}/callback', [SsoController::class, 'callback']);
```

### Frontend with Multiple Providers

```html
<div id="sso-buttons"></div>

<script>
  async function loadSsoProviders() {
    const response = await fetch("/auth/providers");
    const { providers } = await response.json();

    const container = document.getElementById("sso-buttons");

    providers.forEach((provider) => {
      const button = document.createElement("a");
      button.href = provider.url;
      button.className = `btn btn-sso btn-${provider.name}`;
      button.textContent = provider.label;
      container.appendChild(button);
    });
  }

  loadSsoProviders();
</script>
```

---

## Security Best Practices

### 1. Always Verify State Parameter

```php
if (!hash_equals($savedState, $requestState)) {
    throw new AuthenticationException('CSRF detected');
}
```

### 2. Use HTTPS Only

```php
// In bootstrap
if ($_ENV['APP_ENV'] === 'production' && !$request->isSecure()) {
    throw new \RuntimeException('HTTPS required for OAuth');
}
```

### 3. Validate Email Domain (Optional)

```php
private function validateEmailDomain(string $email): void
{
    $allowedDomains = ['yourcompany.com', 'partner.com'];
    $domain = substr(strrchr($email, "@"), 1);

    if (!in_array($domain, $allowedDomains)) {
        throw new AuthenticationException('Email domain not allowed');
    }
}
```

### 4. Store Refresh Tokens Securely

```php
// Encrypt before storing
$encryptedToken = $this->encryption->encrypt($tokens['refresh_token']);
$user->setOidcRefreshToken($encryptedToken);
```

---

## Troubleshooting

### "Invalid redirect_uri" error

- Ensure the callback URL exactly matches what's registered
- Check for trailing slashes
- Verify HTTPS vs HTTP

### "State mismatch" error

- Check session configuration
- Ensure cookies are being set properly
- Verify session storage is working

### "Invalid client" error

- Double-check client ID and secret
- Ensure credentials haven't expired
- Verify the app is properly registered

---

## Next Steps

- [Add MFA after SSO](./mfa-authentication.md)
- [Implement Role Mapping from OIDC Claims](./authorization-rbac.md)
- [Set Up JWT for API Authentication](./jwt-authentication.md)
