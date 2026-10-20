<?php

declare(strict_types=1);

namespace Gadget\Oauth\Tests\Client;

use Gadget\Oauth\Client\AuthClient;
use Gadget\Oauth\Exception\AuthException;
use Gadget\Oauth\Model\PKCE;
use Gadget\Oauth\Tests\Support\OauthTestCase;

final class AuthClientTest extends OauthTestCase
{
    private const AUTH_URI = 'https://login.example.com/authorize';
    private const TOKEN_URI = 'https://login.example.com/token';
    private const REDIRECT_URI = 'https://app.example.com/callback';


    /**
     * @param array<string,string|null> $overrides
     * @return AuthClient
     */
    private function configuredClient(array $overrides = []): AuthClient
    {
        $config = $overrides + [
            'authUri' => self::AUTH_URI,
            'tokenUri' => self::TOKEN_URI,
            'clientId' => 'client-id',
            'clientSecret' => 'client-secret',
            'scope' => 'openid profile',
            'redirectUri' => self::REDIRECT_URI,
        ];

        return new AuthClient(
            $this->createClient(),
            authUri: $config['authUri'],
            tokenUri: $config['tokenUri'],
            clientId: $config['clientId'],
            clientSecret: $config['clientSecret'],
            scope: $config['scope'],
            redirectUri: $config['redirectUri']
        );
    }


    /** @return array<string,mixed> */
    private static function tokenBody(): array
    {
        return [
            'token_type' => 'Bearer',
            'scope' => 'openid profile',
            'expires_in' => 3600,
            'access_token' => 'the-access-token',
            'refresh_token' => 'the-refresh-token',
        ];
    }


    public function testCreateAuthCodeUsesTheConfiguredSettings(): void
    {
        $this->transport->append(self::redirectTo(self::REDIRECT_URI . '?code=the-code&state=xyz'));

        $authCode = $this->configuredClient()->createAuthCode(state: 'xyz');

        self::assertSame('the-code', $authCode->code);
        self::assertSame([
            'response_type' => 'code',
            'client_id' => 'client-id',
            'redirect_uri' => self::REDIRECT_URI,
            'scope' => 'openid profile',
            'state' => 'xyz',
        ], self::queryOf($this->sentRequest()));
    }


    public function testCreateAuthCodeGeneratesAStateWhenNoneIsGiven(): void
    {
        $this->transport->append(self::redirectTo(self::REDIRECT_URI . '?code=the-code&state=unknown'));

        try {
            $this->configuredClient()->createAuthCode();
            self::fail('A generated state should not match the server response');
        } catch (\Throwable $e) {
            $query = self::queryOf($this->sentRequest());
            self::assertIsString($query['state']);
            self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $query['state']);
        }
    }


    public function testPerCallArgumentsOverrideTheConfiguredSettings(): void
    {
        $this->transport->append(self::redirectTo('https://other.example.com/cb?code=the-code&state=xyz'));
        $pkce = new PKCE();

        $authCode = $this->configuredClient()->createAuthCode(
            state: 'xyz',
            pkce: $pkce,
            authUri: 'https://other.example.com/authorize',
            clientId: 'other-client',
            redirectUri: 'https://other.example.com/cb',
            scope: 'other-scope'
        );

        $sent = $this->sentRequest();
        self::assertSame('other.example.com', $sent->getUri()->getHost());
        self::assertSame('/authorize', $sent->getUri()->getPath());
        self::assertSame([
            'response_type' => 'code',
            'client_id' => 'other-client',
            'redirect_uri' => 'https://other.example.com/cb',
            'scope' => 'other-scope',
            'state' => 'xyz',
            'code_challenge' => $pkce->challenge,
            'code_challenge_method' => 'S256',
        ], self::queryOf($sent));
        self::assertSame($pkce, $authCode->pkce);
    }


    /** @return iterable<string,array{string}> */
    public static function authorizationSettings(): iterable
    {
        yield 'authUri' => ['authUri'];
        yield 'clientId' => ['clientId'];
        yield 'redirectUri' => ['redirectUri'];
        yield 'scope' => ['scope'];
    }


    /** @dataProvider authorizationSettings */
    public function testCreateAuthCodeRequiresEachAuthorizationSetting(string $missing): void
    {
        $client = $this->configuredClient([$missing => null]);

        $this->expectException(AuthException::class);

        try {
            $client->createAuthCode(state: 'xyz');
        } finally {
            self::assertSame([], $this->sentRequests, 'Nothing should reach the transport');
        }
    }


    public function testCreateTokenPostsTheAuthorizationCode(): void
    {
        $this->transport->append(self::jsonResponse(self::tokenBody()));
        $pkce = new PKCE();

        $token = $this->configuredClient()->createToken('the-code', $pkce);

        self::assertSame('the-access-token', $token->accessToken);
        $sent = $this->sentRequest();
        self::assertSame('POST', $sent->getMethod());
        self::assertSame(self::TOKEN_URI, (string) $sent->getUri());
        self::assertSame([
            'grant_type' => 'authorization_code',
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'redirect_uri' => self::REDIRECT_URI,
            'code' => 'the-code',
            'code_verifier' => $pkce->verifier,
        ], self::formBodyOf($sent));
    }


    public function testCreateTokenAcceptsPerCallOverrides(): void
    {
        $this->transport->append(self::jsonResponse(self::tokenBody()));

        $this->configuredClient()->createToken(
            'the-code',
            tokenUri: 'https://other.example.com/token',
            clientId: 'other-client',
            clientSecret: 'other-secret',
            redirectUri: 'https://other.example.com/cb'
        );

        $sent = $this->sentRequest();
        self::assertSame('https://other.example.com/token', (string) $sent->getUri());
        self::assertSame([
            'grant_type' => 'authorization_code',
            'client_id' => 'other-client',
            'client_secret' => 'other-secret',
            'redirect_uri' => 'https://other.example.com/cb',
            'code' => 'the-code',
        ], self::formBodyOf($sent));
    }


    /** @return iterable<string,array{string}> */
    public static function tokenSettings(): iterable
    {
        yield 'tokenUri' => ['tokenUri'];
        yield 'clientId' => ['clientId'];
        yield 'clientSecret' => ['clientSecret'];
        yield 'redirectUri' => ['redirectUri'];
    }


    /** @dataProvider tokenSettings */
    public function testCreateTokenRequiresEachTokenSetting(string $missing): void
    {
        $client = $this->configuredClient([$missing => null]);

        $this->expectException(AuthException::class);

        try {
            $client->createToken('the-code');
        } finally {
            self::assertSame([], $this->sentRequests, 'Nothing should reach the transport');
        }
    }


    public function testRefreshTokenPostsTheRefreshToken(): void
    {
        $this->transport->append(self::jsonResponse(self::tokenBody()));

        $token = $this->configuredClient()->refreshToken('the-refresh-token');

        self::assertSame('the-access-token', $token->accessToken);
        self::assertSame([
            'grant_type' => 'refresh_token',
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'refresh_token' => 'the-refresh-token',
        ], self::formBodyOf($this->sentRequest()));
    }


    public function testRefreshTokenDoesNotNeedARedirectUri(): void
    {
        $this->transport->append(self::jsonResponse(self::tokenBody()));

        $token = $this->configuredClient(['redirectUri' => null])->refreshToken('the-refresh-token');

        self::assertSame('the-access-token', $token->accessToken);
    }


    /** @return iterable<string,array{string}> */
    public static function refreshSettings(): iterable
    {
        yield 'tokenUri' => ['tokenUri'];
        yield 'clientId' => ['clientId'];
        yield 'clientSecret' => ['clientSecret'];
    }


    /** @dataProvider refreshSettings */
    public function testRefreshTokenRequiresEachTokenSetting(string $missing): void
    {
        $client = $this->configuredClient([$missing => null]);

        $this->expectException(AuthException::class);

        try {
            $client->refreshToken('the-refresh-token');
        } finally {
            self::assertSame([], $this->sentRequests, 'Nothing should reach the transport');
        }
    }


    public function testTheAuthCodeFlowCarriesThePkceVerifierIntoTheTokenRequest(): void
    {
        $this->transport->append(
            self::redirectTo(self::REDIRECT_URI . '?code=the-code&state=xyz'),
            self::jsonResponse(self::tokenBody())
        );
        $client = $this->configuredClient();
        $pkce = new PKCE();

        $authCode = $client->createAuthCode(state: 'xyz', pkce: $pkce);
        $token = $client->createToken($authCode->code, $authCode->pkce);

        self::assertSame('the-access-token', $token->accessToken);
        self::assertSame($pkce->challenge, self::queryOf($this->sentRequest(0))['code_challenge']);
        self::assertSame($pkce->verifier, self::formBodyOf($this->sentRequest(1))['code_verifier']);
    }
}
