# Session Authentication Tutorial

This tutorial walks you through implementing traditional session-based authentication for web applications, including login forms, remember me functionality, and secure session management.

## What You'll Learn

- How session-based authentication works
- Setting up login and registration forms
- Implementing "Remember Me" functionality
- Session security best practices
- Password reset flow
- Account verification

## Prerequisites

- Luminor framework installed
- A database with a users table
- Basic HTML/CSS for forms

---

## Understanding Session Authentication

Session authentication stores user state on the server:

```
┌──────────┐     ┌──────────────┐     ┌──────────────┐
│  Browser │     │  Your App    │     │  Session     │
│          │     │  (Luminor)   │     │  Storage     │
└────┬─────┘     └──────┬───────┘     └──────┬───────┘
     │                  │                    │
     │ 1. POST /login   │                    │
     │  (credentials)   │                    │
     │─────────────────>│                    │
     │                  │                    │
     │                  │ 2. Validate        │
     │                  │    credentials     │
     │                  │                    │
     │                  │ 3. Create session  │
     │                  │───────────────────>│
     │                  │                    │
     │                  │ 4. Session ID      │
     │                  │<───────────────────│
     │                  │                    │
     │ 5. Set-Cookie:   │                    │
     │    session_id=X  │                    │
     │<─────────────────│                    │
     │                  │                    │
     │ 6. Subsequent    │                    │
     │    requests with │                    │
     │    Cookie        │                    │
     │─────────────────>│                    │
     │                  │                    │
     │                  │ 7. Verify session  │
     │                  │───────────────────>│
     │                  │                    │
     │                  │ 8. User data       │
     │                  │<───────────────────│
```

---

## Step 1: Database Setup

Create a users table migration:

```php
<?php

use Luminor\Database\Migration;
use Luminor\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        $this->schema->create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password_hash');
            $table->boolean('email_verified')->default(false);
            $table->string('email_verification_token')->nullable();
            $table->string('password_reset_token')->nullable();
            $table->timestamp('password_reset_expires_at')->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->schema->drop('users');
    }
};
```

---

## Step 2: User Entity

```php
<?php

declare(strict_types=1);

namespace App\Domain\User;

use Luminor\DDD\Domain\Abstractions\Entity;
use Luminor\Auth\AuthenticatableInterface;

final class User extends Entity implements AuthenticatableInterface
{
    private string $name;
    private string $email;
    private string $passwordHash;
    private bool $emailVerified;
    private ?string $emailVerificationToken;
    private ?string $passwordResetToken;
    private ?\DateTimeImmutable $passwordResetExpiresAt;
    private ?string $rememberToken;
    private ?\DateTimeImmutable $lastLoginAt;

    public static function create(
        string $name,
        string $email,
        string $password,
    ): self {
        $user = new self(self::generateId());
        $user->name = $name;
        $user->email = strtolower($email);
        $user->passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $user->emailVerified = false;
        $user->emailVerificationToken = bin2hex(random_bytes(32));

        return $user;
    }

    public function getAuthIdentifier(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->passwordHash);
    }

    public function setPassword(string $password): void
    {
        $this->passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $this->passwordResetToken = null;
        $this->passwordResetExpiresAt = null;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    public function verifyEmail(): void
    {
        $this->emailVerified = true;
        $this->emailVerificationToken = null;
    }

    public function getEmailVerificationToken(): ?string
    {
        return $this->emailVerificationToken;
    }

    public function createPasswordResetToken(): string
    {
        $this->passwordResetToken = bin2hex(random_bytes(32));
        $this->passwordResetExpiresAt = new \DateTimeImmutable('+1 hour');

        return $this->passwordResetToken;
    }

    public function validatePasswordResetToken(string $token): bool
    {
        if (!$this->passwordResetToken || !$this->passwordResetExpiresAt) {
            return false;
        }

        if (!hash_equals($this->passwordResetToken, $token)) {
            return false;
        }

        return $this->passwordResetExpiresAt > new \DateTimeImmutable();
    }

    public function setRememberToken(string $token): void
    {
        $this->rememberToken = $token;
    }

    public function getRememberToken(): ?string
    {
        return $this->rememberToken;
    }

    public function recordLogin(): void
    {
        $this->lastLoginAt = new \DateTimeImmutable();
    }
}
```

---

## Step 3: Authentication Service

```php
<?php

declare(strict_types=1);

namespace App\Auth;

use Luminor\Auth\Session\SessionAuthenticationProvider;
use Luminor\Auth\AuthenticationException;
use Luminor\Auth\RateLimit\RateLimiter;
use Luminor\Session\Session;
use App\Domain\User\User;
use App\Domain\User\UserRepository;

final class SessionAuthService
{
    private SessionAuthenticationProvider $authProvider;

    public function __construct(
        private Session $session,
        private UserRepository $userRepository,
        private RateLimiter $rateLimiter,
    ) {
        $this->authProvider = new SessionAuthenticationProvider(
            $session,
            fn($id, $byEmail) => $byEmail
                ? $this->userRepository->findByEmail($id)
                : $this->userRepository->find($id)
        );
    }

    /**
     * Register a new user
     */
    public function register(string $name, string $email, string $password): User
    {
        // Check if email exists
        if ($this->userRepository->findByEmail($email)) {
            throw new AuthenticationException('Email already registered');
        }

        // Create user
        $user = User::create($name, $email, $password);
        $this->userRepository->save($user);

        // Log the user in
        $this->login($user);

        return $user;
    }

    /**
     * Attempt login with credentials
     */
    public function attempt(string $email, string $password, bool $remember = false): User
    {
        $rateLimitKey = 'login:' . strtolower($email);

        // Check rate limit
        if ($this->rateLimiter->tooManyAttempts($rateLimitKey, 5)) {
            $retryAfter = $this->rateLimiter->availableIn($rateLimitKey);
            throw new AuthenticationException(
                "Too many login attempts. Please try again in {$retryAfter} seconds."
            );
        }

        try {
            $user = $this->authProvider->attempt([
                'email' => $email,
                'password' => $password,
            ], $remember);

            // Clear rate limit on success
            $this->rateLimiter->clear($rateLimitKey);

            // Record login
            $user->recordLogin();
            $this->userRepository->save($user);

            return $user;

        } catch (AuthenticationException $e) {
            // Increment failed attempts
            $this->rateLimiter->hit($rateLimitKey);
            throw $e;
        }
    }

    /**
     * Log in a user directly (without password)
     */
    public function login(User $user, bool $remember = false): void
    {
        $this->authProvider->login($user, $remember);

        $user->recordLogin();
        $this->userRepository->save($user);
    }

    /**
     * Log out the current user
     */
    public function logout(): void
    {
        $this->authProvider->logout();
    }

    /**
     * Get current authenticated user
     */
    public function user(): ?User
    {
        return $this->authProvider->user();
    }

    /**
     * Check if user is authenticated
     */
    public function check(): bool
    {
        return $this->authProvider->check();
    }

    /**
     * Initiate password reset
     */
    public function initiatePasswordReset(string $email): ?string
    {
        $user = $this->userRepository->findByEmail($email);

        if (!$user) {
            // Don't reveal if email exists
            return null;
        }

        $token = $user->createPasswordResetToken();
        $this->userRepository->save($user);

        return $token;
    }

    /**
     * Reset password with token
     */
    public function resetPassword(string $email, string $token, string $newPassword): void
    {
        $user = $this->userRepository->findByEmail($email);

        if (!$user || !$user->validatePasswordResetToken($token)) {
            throw new AuthenticationException('Invalid or expired reset token');
        }

        $user->setPassword($newPassword);
        $this->userRepository->save($user);

        // Log out all sessions (optional)
        $this->session->regenerate();
    }

    /**
     * Verify email address
     */
    public function verifyEmail(string $email, string $token): void
    {
        $user = $this->userRepository->findByEmail($email);

        if (!$user) {
            throw new AuthenticationException('User not found');
        }

        if ($user->getEmailVerificationToken() !== $token) {
            throw new AuthenticationException('Invalid verification token');
        }

        $user->verifyEmail();
        $this->userRepository->save($user);
    }
}
```

---

## Step 4: Authentication Controller

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Luminor\DDD\Infrastructure\Http\ApiController;
use Luminor\Auth\AuthenticationException;
use App\Auth\SessionAuthService;
use App\Mail\PasswordResetMail;
use App\Mail\VerificationMail;
use Utopia\Http\Request;
use Utopia\Http\Response;

final class AuthController extends ApiController
{
    public function __construct(
        private SessionAuthService $auth,
    ) {}

    /**
     * GET /register
     */
    public function showRegisterForm(Request $request, Response $response): Response
    {
        return $this->view($response, 'auth/register');
    }

    /**
     * POST /register
     */
    public function register(Request $request, Response $response): Response
    {
        $payload = $request->getPayload();

        // Validate
        $errors = $this->validate($payload, [
            'name' => 'required|string|min:2|max:100',
            'email' => 'required|email',
            'password' => 'required|string|min:8',
            'password_confirmation' => 'required|same:password',
            'terms' => 'accepted',
        ]);

        if (!empty($errors)) {
            return $this->view($response, 'auth/register', [
                'errors' => $errors,
                'old' => $payload,
            ]);
        }

        try {
            $user = $this->auth->register(
                name: $payload['name'],
                email: $payload['email'],
                password: $payload['password']
            );

            // Send verification email
            $this->sendVerificationEmail($user);

            // Redirect to dashboard with success message
            $this->flash('success', 'Registration successful! Please verify your email.');

            return $this->redirectTo($response, '/dashboard');

        } catch (AuthenticationException $e) {
            return $this->view($response, 'auth/register', [
                'errors' => ['email' => [$e->getMessage()]],
                'old' => $payload,
            ]);
        }
    }

    /**
     * GET /login
     */
    public function showLoginForm(Request $request, Response $response): Response
    {
        return $this->view($response, 'auth/login');
    }

    /**
     * POST /login
     */
    public function login(Request $request, Response $response): Response
    {
        $payload = $request->getPayload();

        $errors = $this->validate($payload, [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (!empty($errors)) {
            return $this->view($response, 'auth/login', [
                'errors' => $errors,
                'old' => ['email' => $payload['email'] ?? ''],
            ]);
        }

        try {
            $this->auth->attempt(
                email: $payload['email'],
                password: $payload['password'],
                remember: isset($payload['remember'])
            );

            // Redirect to intended URL or dashboard
            $intended = $request->getParam('redirect', '/dashboard');

            return $this->redirectTo($response, $intended);

        } catch (AuthenticationException $e) {
            return $this->view($response, 'auth/login', [
                'errors' => ['credentials' => [$e->getMessage()]],
                'old' => ['email' => $payload['email'] ?? ''],
            ]);
        }
    }

    /**
     * POST /logout
     */
    public function logout(Request $request, Response $response): Response
    {
        $this->auth->logout();

        $this->flash('success', 'You have been logged out.');

        return $this->redirectTo($response, '/login');
    }

    /**
     * GET /forgot-password
     */
    public function showForgotPasswordForm(Request $request, Response $response): Response
    {
        return $this->view($response, 'auth/forgot-password');
    }

    /**
     * POST /forgot-password
     */
    public function sendPasswordResetLink(Request $request, Response $response): Response
    {
        $email = $request->getPayload()['email'] ?? '';

        $errors = $this->validate(['email' => $email], [
            'email' => 'required|email',
        ]);

        if (!empty($errors)) {
            return $this->view($response, 'auth/forgot-password', [
                'errors' => $errors,
            ]);
        }

        $token = $this->auth->initiatePasswordReset($email);

        if ($token) {
            $this->sendPasswordResetEmail($email, $token);
        }

        // Always show success (don't reveal if email exists)
        $this->flash('success', 'If your email is registered, you will receive a password reset link.');

        return $this->view($response, 'auth/forgot-password');
    }

    /**
     * GET /reset-password/{token}
     */
    public function showResetPasswordForm(Request $request, Response $response, string $token): Response
    {
        return $this->view($response, 'auth/reset-password', [
            'token' => $token,
            'email' => $request->getParam('email', ''),
        ]);
    }

    /**
     * POST /reset-password
     */
    public function resetPassword(Request $request, Response $response): Response
    {
        $payload = $request->getPayload();

        $errors = $this->validate($payload, [
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8',
            'password_confirmation' => 'required|same:password',
        ]);

        if (!empty($errors)) {
            return $this->view($response, 'auth/reset-password', [
                'errors' => $errors,
                'token' => $payload['token'] ?? '',
                'email' => $payload['email'] ?? '',
            ]);
        }

        try {
            $this->auth->resetPassword(
                email: $payload['email'],
                token: $payload['token'],
                newPassword: $payload['password']
            );

            $this->flash('success', 'Password reset successful. Please login with your new password.');

            return $this->redirectTo($response, '/login');

        } catch (AuthenticationException $e) {
            return $this->view($response, 'auth/reset-password', [
                'errors' => ['token' => [$e->getMessage()]],
                'token' => $payload['token'] ?? '',
                'email' => $payload['email'] ?? '',
            ]);
        }
    }

    /**
     * GET /verify-email/{token}
     */
    public function verifyEmail(Request $request, Response $response, string $token): Response
    {
        $email = $request->getParam('email', '');

        try {
            $this->auth->verifyEmail($email, $token);

            $this->flash('success', 'Email verified successfully!');

            return $this->redirectTo($response, '/dashboard');

        } catch (AuthenticationException $e) {
            $this->flash('error', 'Invalid or expired verification link.');

            return $this->redirectTo($response, '/login');
        }
    }

    private function redirectTo(Response $response, string $url): Response
    {
        $response->addHeader('Location', $url);
        $response->setStatusCode(302);
        return $response;
    }

    private function flash(string $key, string $message): void
    {
        $_SESSION['flash'][$key] = $message;
    }

    private function sendVerificationEmail($user): void
    {
        // Use your mail service
        // Mail::send(new VerificationMail($user));
    }

    private function sendPasswordResetEmail(string $email, string $token): void
    {
        // Use your mail service
        // Mail::send(new PasswordResetMail($email, $token));
    }
}
```

---

## Step 5: View Templates

### Login Form

```html
<!-- views/auth/login.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link rel="stylesheet" href="/css/auth.css">
</head>
<body>
    <div class="auth-container">
        <h1>Login</h1>

        <?php if (isset($errors['credentials'])): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($errors['credentials'][0]) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['flash']['success'])): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($_SESSION['flash']['success']) ?>
            </div>
            <?php unset($_SESSION['flash']['success']); ?>
        <?php endif; ?>

        <form method="POST" action="/login">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

            <div class="form-group">
                <label for="email">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                    required
                    autofocus
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                >
            </div>

            <div class="form-group">
                <label class="checkbox">
                    <input type="checkbox" name="remember" value="1">
                    Remember me
                </label>
            </div>

            <button type="submit" class="btn btn-primary">Login</button>

            <div class="auth-links">
                <a href="/forgot-password">Forgot your password?</a>
                <a href="/register">Don't have an account? Register</a>
            </div>
        </form>

        <!-- SSO Options -->
        <div class="sso-divider">
            <span>or continue with</span>
        </div>

        <div class="sso-buttons">
            <a href="/auth/google" class="btn btn-google">
                Google
            </a>
            <a href="/auth/azure" class="btn btn-microsoft">
                Microsoft
            </a>
        </div>
    </div>
</body>
</html>
```

### Registration Form

```html
<!-- views/auth/register.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
    <link rel="stylesheet" href="/css/auth.css">
</head>
<body>
    <div class="auth-container">
        <h1>Create Account</h1>

        <form method="POST" action="/register">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

            <div class="form-group">
                <label for="name">Full Name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    value="<?= htmlspecialchars($old['name'] ?? '') ?>"
                    required
                    autofocus
                >
                <?php if (isset($errors['name'])): ?>
                    <span class="error"><?= $errors['name'][0] ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                    required
                >
                <?php if (isset($errors['email'])): ?>
                    <span class="error"><?= $errors['email'][0] ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    minlength="8"
                    required
                >
                <?php if (isset($errors['password'])): ?>
                    <span class="error"><?= $errors['password'][0] ?></span>
                <?php endif; ?>
                <small>Minimum 8 characters</small>
            </div>

            <div class="form-group">
                <label for="password_confirmation">Confirm Password</label>
                <input
                    type="password"
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                >
            </div>

            <div class="form-group">
                <label class="checkbox">
                    <input type="checkbox" name="terms" value="1" required>
                    I agree to the <a href="/terms" target="_blank">Terms of Service</a>
                </label>
            </div>

            <button type="submit" class="btn btn-primary">Create Account</button>

            <div class="auth-links">
                <a href="/login">Already have an account? Login</a>
            </div>
        </form>
    </div>
</body>
</html>
```

---

## Step 6: Configure Routes

```php
<?php

// routes/web.php

use App\Http\Controllers\AuthController;
use Luminor\Auth\RateLimit\RateLimitMiddleware;

// Guest-only routes (redirect if authenticated)
$router->group(['middleware' => $guestMiddleware], function ($router) use ($rateLimiter) {

    $router->get('/login', [AuthController::class, 'showLoginForm']);
    $router->post('/login', [AuthController::class, 'login'])
        ->middleware(new RateLimitMiddleware($rateLimiter, 5));

    $router->get('/register', [AuthController::class, 'showRegisterForm']);
    $router->post('/register', [AuthController::class, 'register'])
        ->middleware(new RateLimitMiddleware($rateLimiter, 10));

    $router->get('/forgot-password', [AuthController::class, 'showForgotPasswordForm']);
    $router->post('/forgot-password', [AuthController::class, 'sendPasswordResetLink'])
        ->middleware(new RateLimitMiddleware($rateLimiter, 3));

    $router->get('/reset-password/{token}', [AuthController::class, 'showResetPasswordForm']);
    $router->post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Auth routes (require authentication)
$router->group(['middleware' => $authMiddleware], function ($router) {
    $router->post('/logout', [AuthController::class, 'logout']);
});

// Email verification (can be accessed by anyone with token)
$router->get('/verify-email/{token}', [AuthController::class, 'verifyEmail']);
```

---

## Step 7: Session Configuration

Configure session settings in your bootstrap:

```php
<?php

use Luminor\Session\Session;

// Session configuration
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', $_ENV['APP_ENV'] === 'production' ? '1' : '0');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');
ini_set('session.gc_maxlifetime', '7200'); // 2 hours

// Start session
$session = Session::start([
    'name' => 'luminor_session',
    'lifetime' => 7200,
    'path' => '/',
    'domain' => $_ENV['SESSION_DOMAIN'] ?? null,
    'secure' => $_ENV['APP_ENV'] === 'production',
    'httponly' => true,
    'samesite' => 'Lax',
]);

$container->set(Session::class, $session);
```

---

## Security Best Practices

### 1. Password Hashing

Always use `password_hash()` with bcrypt or argon2:

```php
// Hash password
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// Or with Argon2 (PHP 7.2+)
$hash = password_hash($password, PASSWORD_ARGON2ID);

// Verify password
if (password_verify($password, $hash)) {
    // Valid
}
```

### 2. Session Regeneration

Regenerate session ID after login to prevent session fixation:

```php
public function login(User $user): void
{
    $this->session->regenerate(); // Important!
    $this->session->set('user_id', $user->getId());
}
```

### 3. CSRF Protection

Include CSRF tokens in all forms:

```php
// Generate token
$csrfToken = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;

// Verify in controller
$submittedToken = $request->getPayload()['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
    throw new SecurityException('CSRF token mismatch');
}
```

### 4. Account Lockout

Lock accounts after too many failed attempts:

```php
if ($this->rateLimiter->tooManyAttempts($key, 5)) {
    // Optional: Lock the account
    $user->lockUntil(new \DateTimeImmutable('+30 minutes'));
    $this->userRepository->save($user);

    throw new AuthenticationException('Account temporarily locked');
}
```

### 5. Secure Remember Me

Use secure random tokens for remember me:

```php
public function setRememberToken(User $user): void
{
    $token = bin2hex(random_bytes(32));
    $hashedToken = hash('sha256', $token);

    $user->setRememberToken($hashedToken);
    $this->userRepository->save($user);

    // Set secure cookie
    setcookie('remember_token', $token, [
        'expires' => time() + (86400 * 30), // 30 days
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
```

---

## Next Steps

- [Add Multi-Factor Authentication](./mfa-authentication.md)
- [Implement JWT for API Authentication](./jwt-authentication.md)
- [Set Up Single Sign-On](./openid-connect-sso.md)
