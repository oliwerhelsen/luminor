---
title: Multi-Factor Authentication (MFA) Tutorial
layout: default
parent: Authentication
nav_order: 4
description: "Learn how to implement TOTP-based multi-factor authentication for enhanced security"
---

# Multi-Factor Authentication (MFA) Tutorial

This tutorial walks you through implementing Time-based One-Time Password (TOTP) multi-factor authentication, compatible with Google Authenticator, Authy, and other authenticator apps.

## What You'll Learn

- How TOTP-based MFA works
- Enabling MFA for user accounts
- Generating and displaying QR codes
- Verifying MFA codes during login
- Implementing recovery codes
- Managing MFA settings

## Prerequisites

- Luminor framework installed
- User authentication already set up
- Basic understanding of 2FA concepts

---

## Understanding TOTP

TOTP (Time-based One-Time Password) generates a 6-digit code that changes every 30 seconds:

```
┌─────────────┐     ┌─────────────┐
│ User's App  │     │ Your Server │
│ (Authy/GA)  │     │             │
└──────┬──────┘     └──────┬──────┘
       │                   │
       │  Same secret key  │
       │<─────────────────>│
       │                   │
       │ Generate code     │
       │ using:            │
       │ - Secret          │
       │ - Current time    │
       │ - HMAC-SHA1       │
       │                   │
       │    "123456"       │
       │                   │
       │ User enters code  │
       │───────────────────>
       │                   │
       │                   │ Verify using
       │                   │ same algorithm
       │                   │
       │    ✓ Valid        │
       │<──────────────────│
```

---

## Step 1: Database Setup

Add MFA columns to users table:

```php
<?php

use Luminor\Database\Migration;
use Luminor\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        $this->schema->table('users', function (Blueprint $table) {
            $table->string('mfa_secret', 64)->nullable();
            $table->boolean('mfa_enabled')->default(false);
            $table->json('mfa_recovery_codes')->nullable();
            $table->timestamp('mfa_enabled_at')->nullable();
        });
    }

    public function down(): void
    {
        $this->schema->table('users', function (Blueprint $table) {
            $table->dropColumn(['mfa_secret', 'mfa_enabled', 'mfa_recovery_codes', 'mfa_enabled_at']);
        });
    }
};
```

---

## Step 2: TOTP Service

Create a service to handle TOTP operations:

```php
<?php

declare(strict_types=1);

namespace App\Auth\Mfa;

final class TotpService
{
    private const SECRET_LENGTH = 20; // 160 bits
    private const CODE_LENGTH = 6;
    private const TIME_STEP = 30; // seconds
    private const WINDOW = 1; // Allow 1 step before/after

    /**
     * Generate a new secret key
     */
    public function generateSecret(): string
    {
        $secret = random_bytes(self::SECRET_LENGTH);
        return $this->base32Encode($secret);
    }

    /**
     * Generate current TOTP code
     */
    public function generateCode(string $secret): string
    {
        $timestamp = $this->getTimestamp();
        return $this->generateCodeForTimestamp($secret, $timestamp);
    }

    /**
     * Verify a TOTP code
     */
    public function verify(string $code, string $secret): bool
    {
        $timestamp = $this->getTimestamp();

        // Check current time step and window
        for ($i = -self::WINDOW; $i <= self::WINDOW; $i++) {
            $expectedCode = $this->generateCodeForTimestamp($secret, $timestamp + $i);

            if (hash_equals($expectedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate otpauth:// URI for QR code
     */
    public function getQrCodeUri(string $secret, string $userEmail, string $issuer): string
    {
        $issuer = rawurlencode($issuer);
        $email = rawurlencode($userEmail);

        return "otpauth://totp/{$issuer}:{$email}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
    }

    /**
     * Get timestamp counter
     */
    private function getTimestamp(): int
    {
        return (int) floor(time() / self::TIME_STEP);
    }

    /**
     * Generate code for a specific timestamp
     */
    private function generateCodeForTimestamp(string $secret, int $timestamp): string
    {
        // Decode secret
        $key = $this->base32Decode($secret);

        // Pack timestamp as 64-bit big-endian
        $time = pack('J', $timestamp);

        // Generate HMAC-SHA1
        $hash = hash_hmac('sha1', $time, $key, true);

        // Dynamic truncation
        $offset = ord($hash[19]) & 0x0F;
        $binary = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        $otp = $binary % (10 ** self::CODE_LENGTH);

        return str_pad((string) $otp, self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * Base32 encode
     */
    private function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';

        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($binary, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $encoded .= $alphabet[bindec($chunk)];
        }

        return $encoded;
    }

    /**
     * Base32 decode
     */
    private function base32Decode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $data = strtoupper($data);
        $binary = '';

        foreach (str_split($data) as $char) {
            $pos = strpos($alphabet, $char);
            if ($pos !== false) {
                $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
            }
        }

        $decoded = '';
        foreach (str_split($binary, 8) as $byte) {
            if (strlen($byte) === 8) {
                $decoded .= chr(bindec($byte));
            }
        }

        return $decoded;
    }
}
```

---

## Step 3: MFA Service

```php
<?php

declare(strict_types=1);

namespace App\Auth\Mfa;

use Luminor\Auth\AuthenticationException;
use App\Domain\User\User;
use App\Domain\User\UserRepository;

final class MfaService
{
    private const RECOVERY_CODE_LENGTH = 10;
    private const RECOVERY_CODES_COUNT = 8;

    public function __construct(
        private TotpService $totpService,
        private UserRepository $userRepository,
    ) {}

    /**
     * Initialize MFA setup (before confirmation)
     */
    public function initialize(User $user): array
    {
        if ($this->isEnabled($user)) {
            throw new AuthenticationException('MFA is already enabled');
        }

        // Generate new secret
        $secret = $this->totpService->generateSecret();

        // Store temporarily (not enabled yet)
        $user->setMfaSecret($secret);
        $this->userRepository->save($user);

        // Generate QR code URI
        $qrCodeUri = $this->totpService->getQrCodeUri(
            $secret,
            $user->getEmail(),
            $_ENV['APP_NAME'] ?? 'Luminor'
        );

        return [
            'secret' => $secret,
            'qr_code_uri' => $qrCodeUri,
        ];
    }

    /**
     * Confirm and enable MFA
     */
    public function confirm(User $user, string $code): array
    {
        $secret = $user->getMfaSecret();

        if (!$secret) {
            throw new AuthenticationException('MFA setup not initialized');
        }

        // Verify the code
        if (!$this->totpService->verify($code, $secret)) {
            throw new AuthenticationException('Invalid verification code');
        }

        // Generate recovery codes
        $recoveryCodes = $this->generateRecoveryCodes();

        // Enable MFA
        $user->enableMfa($recoveryCodes);
        $this->userRepository->save($user);

        return [
            'recovery_codes' => $recoveryCodes,
        ];
    }

    /**
     * Verify MFA code during login
     */
    public function verify(User $user, string $code): bool
    {
        if (!$this->isEnabled($user)) {
            return true; // MFA not enabled
        }

        $secret = $user->getMfaSecret();

        return $this->totpService->verify($code, $secret);
    }

    /**
     * Verify recovery code
     */
    public function verifyRecoveryCode(User $user, string $code): bool
    {
        if (!$this->isEnabled($user)) {
            return false;
        }

        $recoveryCodes = $user->getMfaRecoveryCodes();
        $code = strtoupper(trim($code));

        foreach ($recoveryCodes as $index => $storedCode) {
            if (hash_equals($storedCode['code'], $code) && !$storedCode['used']) {
                // Mark code as used
                $user->markRecoveryCodeUsed($index);
                $this->userRepository->save($user);

                return true;
            }
        }

        return false;
    }

    /**
     * Check if MFA is enabled for user
     */
    public function isEnabled(User $user): bool
    {
        return $user->isMfaEnabled();
    }

    /**
     * Disable MFA
     */
    public function disable(User $user, string $password): void
    {
        // Verify password for security
        if (!$user->verifyPassword($password)) {
            throw new AuthenticationException('Invalid password');
        }

        $user->disableMfa();
        $this->userRepository->save($user);
    }

    /**
     * Regenerate recovery codes
     */
    public function regenerateRecoveryCodes(User $user, string $code): array
    {
        if (!$this->isEnabled($user)) {
            throw new AuthenticationException('MFA is not enabled');
        }

        // Verify current MFA code
        if (!$this->verify($user, $code)) {
            throw new AuthenticationException('Invalid MFA code');
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $user->setRecoveryCodes($recoveryCodes);
        $this->userRepository->save($user);

        return $recoveryCodes;
    }

    /**
     * Get remaining recovery codes count
     */
    public function getRemainingRecoveryCodesCount(User $user): int
    {
        $codes = $user->getMfaRecoveryCodes();

        return count(array_filter($codes, fn($code) => !$code['used']));
    }

    /**
     * Generate recovery codes
     */
    private function generateRecoveryCodes(): array
    {
        $codes = [];

        for ($i = 0; $i < self::RECOVERY_CODES_COUNT; $i++) {
            $codes[] = [
                'code' => strtoupper(bin2hex(random_bytes(self::RECOVERY_CODE_LENGTH / 2))),
                'used' => false,
            ];
        }

        return $codes;
    }
}
```

---

## Step 4: User Entity MFA Methods

Add these methods to your User entity:

```php
<?php

// In User entity

private ?string $mfaSecret = null;
private bool $mfaEnabled = false;
private array $mfaRecoveryCodes = [];
private ?\DateTimeImmutable $mfaEnabledAt = null;

public function getMfaSecret(): ?string
{
    return $this->mfaSecret;
}

public function setMfaSecret(string $secret): void
{
    $this->mfaSecret = $secret;
}

public function isMfaEnabled(): bool
{
    return $this->mfaEnabled;
}

public function enableMfa(array $recoveryCodes): void
{
    $this->mfaEnabled = true;
    $this->mfaRecoveryCodes = $recoveryCodes;
    $this->mfaEnabledAt = new \DateTimeImmutable();
}

public function disableMfa(): void
{
    $this->mfaEnabled = false;
    $this->mfaSecret = null;
    $this->mfaRecoveryCodes = [];
    $this->mfaEnabledAt = null;
}

public function getMfaRecoveryCodes(): array
{
    return $this->mfaRecoveryCodes;
}

public function setRecoveryCodes(array $codes): void
{
    $this->mfaRecoveryCodes = $codes;
}

public function markRecoveryCodeUsed(int $index): void
{
    if (isset($this->mfaRecoveryCodes[$index])) {
        $this->mfaRecoveryCodes[$index]['used'] = true;
    }
}
```

---

## Step 5: MFA Controller

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Luminor\Infrastructure\Http\ApiController;
use Luminor\Auth\CurrentUser;
use Luminor\Auth\AuthenticationException;
use App\Auth\Mfa\MfaService;
use Luminor\Http\Request;
use Luminor\Http\Response;

final class MfaController extends ApiController
{
    public function __construct(
        private MfaService $mfaService,
    ) {}

    /**
     * GET /account/mfa/status
     */
    public function status(Request $request, Response $response): Response
    {
        $user = CurrentUser::get();

        return $this->success($response, [
            'mfa_enabled' => $this->mfaService->isEnabled($user),
            'recovery_codes_remaining' => $this->mfaService->getRemainingRecoveryCodesCount($user),
        ]);
    }

    /**
     * POST /account/mfa/setup
     * Initialize MFA setup
     */
    public function setup(Request $request, Response $response): Response
    {
        $user = CurrentUser::get();

        try {
            $data = $this->mfaService->initialize($user);

            return $this->success($response, [
                'secret' => $data['secret'],
                'qr_code_uri' => $data['qr_code_uri'],
                'message' => 'Scan the QR code with your authenticator app, then enter the code to confirm.',
            ]);

        } catch (AuthenticationException $e) {
            return $this->error($response, $e->getMessage(), 400);
        }
    }

    /**
     * POST /account/mfa/confirm
     * Confirm and enable MFA
     */
    public function confirm(Request $request, Response $response): Response
    {
        $payload = $request->getPayload();

        $errors = $this->validate($payload, [
            'code' => 'required|string|size:6',
        ]);

        if (!empty($errors)) {
            return $this->validationError($response, $errors);
        }

        $user = CurrentUser::get();

        try {
            $data = $this->mfaService->confirm($user, $payload['code']);

            return $this->success($response, [
                'message' => 'MFA enabled successfully. Save your recovery codes in a secure location.',
                'recovery_codes' => array_map(fn($c) => $c['code'], $data['recovery_codes']),
            ]);

        } catch (AuthenticationException $e) {
            return $this->error($response, $e->getMessage(), 400);
        }
    }

    /**
     * POST /account/mfa/disable
     */
    public function disable(Request $request, Response $response): Response
    {
        $payload = $request->getPayload();

        $errors = $this->validate($payload, [
            'password' => 'required|string',
        ]);

        if (!empty($errors)) {
            return $this->validationError($response, $errors);
        }

        $user = CurrentUser::get();

        try {
            $this->mfaService->disable($user, $payload['password']);

            return $this->success($response, [
                'message' => 'MFA has been disabled',
            ]);

        } catch (AuthenticationException $e) {
            return $this->error($response, $e->getMessage(), 400);
        }
    }

    /**
     * POST /account/mfa/recovery-codes
     * Regenerate recovery codes
     */
    public function regenerateRecoveryCodes(Request $request, Response $response): Response
    {
        $payload = $request->getPayload();

        $errors = $this->validate($payload, [
            'code' => 'required|string|size:6',
        ]);

        if (!empty($errors)) {
            return $this->validationError($response, $errors);
        }

        $user = CurrentUser::get();

        try {
            $codes = $this->mfaService->regenerateRecoveryCodes($user, $payload['code']);

            return $this->success($response, [
                'message' => 'New recovery codes generated. Save them in a secure location.',
                'recovery_codes' => array_map(fn($c) => $c['code'], $codes),
            ]);

        } catch (AuthenticationException $e) {
            return $this->error($response, $e->getMessage(), 400);
        }
    }
}
```

---

## Step 6: Login Flow with MFA

Update your login controller:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Luminor\Infrastructure\Http\ApiController;
use Luminor\Auth\AuthenticationException;
use App\Auth\SessionAuthService;
use App\Auth\Mfa\MfaService;
use Luminor\Session\Session;
use Luminor\Http\Request;
use Luminor\Http\Response;

final class AuthController extends ApiController
{
    public function __construct(
        private SessionAuthService $auth,
        private MfaService $mfaService,
        private Session $session,
    ) {}

    /**
     * POST /login
     */
    public function login(Request $request, Response $response): Response
    {
        $payload = $request->getPayload();

        // Validate credentials
        $errors = $this->validate($payload, [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (!empty($errors)) {
            return $this->validationError($response, $errors);
        }

        try {
            // Authenticate (don't create session yet)
            $user = $this->auth->validateCredentials(
                $payload['email'],
                $payload['password']
            );

            // Check if MFA is enabled
            if ($this->mfaService->isEnabled($user)) {
                // Store user ID temporarily for MFA verification
                $this->session->set('mfa_user_id', $user->getId());
                $this->session->set('mfa_remember', $payload['remember'] ?? false);

                return $this->success($response, [
                    'requires_mfa' => true,
                    'message' => 'Please enter your 2FA code',
                ], 200);
            }

            // No MFA, complete login
            $this->auth->login($user, $payload['remember'] ?? false);

            return $this->success($response, [
                'message' => 'Login successful',
                'user' => [
                    'id' => $user->getId(),
                    'name' => $user->getName(),
                    'email' => $user->getEmail(),
                ],
            ]);

        } catch (AuthenticationException $e) {
            return $this->unauthorized($response, 'Invalid credentials');
        }
    }

    /**
     * POST /login/mfa
     */
    public function verifyMfa(Request $request, Response $response): Response
    {
        $payload = $request->getPayload();

        // Check for pending MFA verification
        $userId = $this->session->get('mfa_user_id');

        if (!$userId) {
            return $this->error($response, 'No pending MFA verification', 400);
        }

        $errors = $this->validate($payload, [
            'code' => 'required|string',
        ]);

        if (!empty($errors)) {
            return $this->validationError($response, $errors);
        }

        $user = $this->auth->getUserById($userId);

        if (!$user) {
            $this->session->remove('mfa_user_id');
            return $this->error($response, 'Session expired', 400);
        }

        $code = $payload['code'];
        $isRecoveryCode = strlen($code) === 10; // Recovery codes are 10 chars

        // Verify code
        $valid = $isRecoveryCode
            ? $this->mfaService->verifyRecoveryCode($user, $code)
            : $this->mfaService->verify($user, $code);

        if (!$valid) {
            return $this->error($response, 'Invalid code', 400);
        }

        // Clear MFA session and complete login
        $remember = $this->session->get('mfa_remember', false);
        $this->session->remove('mfa_user_id');
        $this->session->remove('mfa_remember');

        $this->auth->login($user, $remember);

        // Warn if using recovery code
        $warning = $isRecoveryCode
            ? 'You used a recovery code. Consider regenerating your codes.'
            : null;

        return $this->success($response, [
            'message' => 'Login successful',
            'warning' => $warning,
            'recovery_codes_remaining' => $this->mfaService->getRemainingRecoveryCodesCount($user),
            'user' => [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
            ],
        ]);
    }
}
```

---

## Step 7: Routes

```php
<?php

// routes/web.php or routes/api.php

use App\Http\Controllers\MfaController;

// MFA routes (require authentication)
$router->group(['prefix' => '/account/mfa', 'middleware' => $authMiddleware], function ($router) {
    $router->get('/status', [MfaController::class, 'status']);
    $router->post('/setup', [MfaController::class, 'setup']);
    $router->post('/confirm', [MfaController::class, 'confirm']);
    $router->post('/disable', [MfaController::class, 'disable']);
    $router->post('/recovery-codes', [MfaController::class, 'regenerateRecoveryCodes']);
});

// MFA verification during login
$router->post('/login/mfa', [AuthController::class, 'verifyMfa']);
```

---

## Step 8: Frontend Implementation

### MFA Setup UI

```html
<div id="mfa-setup">
  <h2>Two-Factor Authentication</h2>

  <!-- Status -->
  <div id="mfa-status" class="status-card"></div>

  <!-- Setup Section (if not enabled) -->
  <div id="setup-section" style="display: none;">
    <button onclick="initSetup()" class="btn btn-primary">
      Enable Two-Factor Authentication
    </button>
  </div>

  <!-- QR Code Section -->
  <div id="qr-section" style="display: none;">
    <h3>Step 1: Scan QR Code</h3>
    <p>
      Scan this QR code with your authenticator app (Google Authenticator,
      Authy, etc.)
    </p>

    <div id="qr-code"></div>

    <details>
      <summary>Can't scan? Enter code manually</summary>
      <code id="manual-secret"></code>
    </details>

    <h3>Step 2: Enter Verification Code</h3>
    <form onsubmit="confirmMfa(event)">
      <input
        type="text"
        id="verification-code"
        placeholder="000000"
        pattern="[0-9]{6}"
        maxlength="6"
        required
        autofocus
      />
      <button type="submit" class="btn btn-primary">Verify & Enable</button>
    </form>
  </div>

  <!-- Recovery Codes Section -->
  <div id="recovery-section" style="display: none;">
    <h3>Recovery Codes</h3>
    <p class="warning">
      Save these codes in a secure location. Each code can only be used once.
    </p>

    <div id="recovery-codes" class="codes-grid"></div>

    <button onclick="downloadCodes()" class="btn btn-secondary">
      Download Codes
    </button>
    <button onclick="copyAllCodes()" class="btn btn-secondary">Copy All</button>

    <button onclick="finishSetup()" class="btn btn-primary">
      I've Saved My Codes
    </button>
  </div>

  <!-- Management Section (if enabled) -->
  <div id="manage-section" style="display: none;">
    <h3>Manage Two-Factor Authentication</h3>

    <div class="info-card">
      <p>Recovery codes remaining: <strong id="codes-remaining">0</strong></p>
    </div>

    <button onclick="showRegenerateCodes()" class="btn btn-secondary">
      Regenerate Recovery Codes
    </button>

    <button onclick="showDisableMfa()" class="btn btn-danger">
      Disable 2FA
    </button>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.1/build/qrcode.min.js"></script>
<script>
  let currentSecret = "";
  let recoveryCodes = [];

  async function loadStatus() {
    const response = await fetch("/account/mfa/status", {
      headers: { Authorization: `Bearer ${getToken()}` },
    });
    const data = await response.json();

    if (data.mfa_enabled) {
      document.getElementById("setup-section").style.display = "none";
      document.getElementById("manage-section").style.display = "block";
      document.getElementById("codes-remaining").textContent =
        data.recovery_codes_remaining;
    } else {
      document.getElementById("setup-section").style.display = "block";
      document.getElementById("manage-section").style.display = "none";
    }
  }

  async function initSetup() {
    const response = await fetch("/account/mfa/setup", {
      method: "POST",
      headers: {
        Authorization: `Bearer ${getToken()}`,
        "Content-Type": "application/json",
      },
    });

    const data = await response.json();

    if (response.ok) {
      currentSecret = data.secret;

      // Generate QR code
      QRCode.toCanvas(document.getElementById("qr-code"), data.qr_code_uri, {
        width: 200,
      });

      document.getElementById("manual-secret").textContent = data.secret;
      document.getElementById("setup-section").style.display = "none";
      document.getElementById("qr-section").style.display = "block";
    } else {
      alert("Error: " + data.message);
    }
  }

  async function confirmMfa(event) {
    event.preventDefault();

    const code = document.getElementById("verification-code").value;

    const response = await fetch("/account/mfa/confirm", {
      method: "POST",
      headers: {
        Authorization: `Bearer ${getToken()}`,
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ code }),
    });

    const data = await response.json();

    if (response.ok) {
      recoveryCodes = data.recovery_codes;
      displayRecoveryCodes(recoveryCodes);

      document.getElementById("qr-section").style.display = "none";
      document.getElementById("recovery-section").style.display = "block";
    } else {
      alert("Error: " + data.message);
    }
  }

  function displayRecoveryCodes(codes) {
    const container = document.getElementById("recovery-codes");
    container.innerHTML = codes
      .map((code) => `<code class="recovery-code">${code}</code>`)
      .join("");
  }

  function downloadCodes() {
    const text = recoveryCodes.join("\n");
    const blob = new Blob([text], { type: "text/plain" });
    const url = URL.createObjectURL(blob);

    const a = document.createElement("a");
    a.href = url;
    a.download = "recovery-codes.txt";
    a.click();

    URL.revokeObjectURL(url);
  }

  function copyAllCodes() {
    navigator.clipboard.writeText(recoveryCodes.join("\n"));
    alert("Recovery codes copied to clipboard!");
  }

  function finishSetup() {
    document.getElementById("recovery-section").style.display = "none";
    loadStatus();
  }

  loadStatus();
</script>

<style>
  .codes-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
    margin: 16px 0;
  }

  .recovery-code {
    background: #f5f5f5;
    padding: 8px 12px;
    border-radius: 4px;
    font-family: monospace;
    font-size: 14px;
  }

  .warning {
    background: #fff3cd;
    padding: 12px;
    border-radius: 4px;
    color: #856404;
  }
</style>
```

### Login with MFA

```html
<div id="login-form">
  <h2>Login</h2>

  <!-- Credentials Form -->
  <form id="credentials-form" onsubmit="submitCredentials(event)">
    <div class="form-group">
      <label>Email</label>
      <input type="email" name="email" required />
    </div>

    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" required />
    </div>

    <div class="form-group">
      <label>
        <input type="checkbox" name="remember" />
        Remember me
      </label>
    </div>

    <button type="submit" class="btn btn-primary">Login</button>
  </form>

  <!-- MFA Form -->
  <form id="mfa-form" style="display: none;" onsubmit="submitMfa(event)">
    <h3>Two-Factor Authentication</h3>
    <p>Enter the 6-digit code from your authenticator app</p>

    <div class="form-group">
      <input
        type="text"
        name="code"
        placeholder="000000"
        maxlength="10"
        autocomplete="one-time-code"
        required
        autofocus
      />
      <small>Or enter a recovery code</small>
    </div>

    <button type="submit" class="btn btn-primary">Verify</button>

    <button type="button" onclick="cancelMfa()" class="btn btn-link">
      Cancel
    </button>
  </form>
</div>

<script>
  async function submitCredentials(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);

    const response = await fetch("/login", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        email: formData.get("email"),
        password: formData.get("password"),
        remember: formData.get("remember") === "on",
      }),
    });

    const data = await response.json();

    if (response.ok) {
      if (data.requires_mfa) {
        // Show MFA form
        document.getElementById("credentials-form").style.display = "none";
        document.getElementById("mfa-form").style.display = "block";
      } else {
        // Login complete
        window.location.href = "/dashboard";
      }
    } else {
      alert("Error: " + data.message);
    }
  }

  async function submitMfa(event) {
    event.preventDefault();

    const form = event.target;
    const code = form.code.value;

    const response = await fetch("/login/mfa", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ code }),
    });

    const data = await response.json();

    if (response.ok) {
      if (data.warning) {
        alert(data.warning);
      }
      window.location.href = "/dashboard";
    } else {
      alert("Error: " + data.message);
    }
  }

  function cancelMfa() {
    document.getElementById("credentials-form").style.display = "block";
    document.getElementById("mfa-form").style.display = "none";
  }
</script>
```

---

## Security Best Practices

1. **Rate limit MFA attempts** - Prevent brute force on 6-digit codes
2. **Secure secret storage** - Encrypt MFA secrets at rest
3. **Require password for disabling** - Prevent unauthorized disabling
4. **Limit recovery code display** - Only show once at generation
5. **Track recovery code usage** - Alert users when codes are running low
6. **Time sync tolerance** - Allow ±30 seconds for clock drift
7. **Require MFA for sensitive operations** - Password changes, etc.

---

## Next Steps

- [Implement JWT Authentication](./jwt-authentication.md)
- [Set Up Authorization Policies](./authorization-rbac.md)
- [Testing Authentication](./testing-authentication.md)
