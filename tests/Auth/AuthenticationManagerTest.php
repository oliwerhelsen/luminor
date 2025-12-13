<?php

declare(strict_types=1);

namespace Luminor\Tests\Auth;

use Luminor\Auth\AuthenticatableInterface;
use Luminor\Auth\AuthenticationManager;
use Luminor\Auth\AuthenticationProvider;
use Luminor\Http\Request;
use PHPUnit\Framework\TestCase;

class AuthenticationManagerTest extends TestCase
{
    private AuthenticationManager $authManager;

    protected function setUp(): void
    {
        $this->authManager = new AuthenticationManager();
    }

    public function testRegisterProvider(): void
    {
        $provider = $this->createMockProvider('test-provider');
        $this->authManager->register($provider);

        $this->assertSame($provider, $this->authManager->getProvider('test-provider'));
    }

    public function testAuthenticateWithSupportedProvider(): void
    {
        $user = $this->createMockUser();
        $provider = $this->createMockProvider('provider1', true, $user);

        $this->authManager->register($provider);

        $request = $this->createMockRequest();
        $result = $this->authManager->authenticate($request);

        $this->assertSame($user, $result);
    }

    public function testAuthenticateWithMultipleProviders(): void
    {
        $user = $this->createMockUser();

        // First provider doesn't support
        $provider1 = $this->createMockProvider('provider1', false);

        // Second provider supports and returns user
        $provider2 = $this->createMockProvider('provider2', true, $user);

        $this->authManager->register($provider1);
        $this->authManager->register($provider2);

        $request = $this->createMockRequest();
        $result = $this->authManager->authenticate($request);

        $this->assertSame($user, $result);
    }

    public function testAuthenticateWithSpecificProvider(): void
    {
        $user = $this->createMockUser();
        $provider = $this->createMockProvider('specific', true, $user);

        $this->authManager->register($provider);

        $request = $this->createMockRequest();
        $result = $this->authManager->authenticateWith('specific', $request);

        $this->assertSame($user, $result);
    }

    public function testSetDefaultProvider(): void
    {
        $provider1 = $this->createMockProvider('provider1');
        $provider2 = $this->createMockProvider('provider2');

        $this->authManager->register($provider1);
        $this->authManager->register($provider2, true); // Set as default

        $this->authManager->setDefaultProvider('provider1');

        // The default provider should now be provider1
        $this->assertNotNull($this->authManager->getProvider('provider1'));
    }

    private function createMockProvider(
        string $name,
        bool $supports = false,
        ?AuthenticatableInterface $user = null
    ): AuthenticationProvider {
        $provider = $this->createMock(AuthenticationProvider::class);

        $provider->method('getName')->willReturn($name);
        $provider->method('supports')->willReturn($supports);
        $provider->method('authenticate')->willReturn($user);

        return $provider;
    }

    private function createMockUser(): AuthenticatableInterface
    {
        return $this->createMock(AuthenticatableInterface::class);
    }

    private function createMockRequest(): Request
    {
        return $this->createMock(Request::class);
    }
}
