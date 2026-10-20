<?php

declare(strict_types=1);

namespace Gadget\Oauth\Tests\Model;

use Gadget\Oauth\Model\PKCE;
use Gadget\Oauth\Model\TokenRequest;
use PHPUnit\Framework\TestCase;

final class TokenRequestTest extends TestCase
{
    public function testAnAuthorizationCodeBodyCarriesTheCodeRedirectUriAndVerifier(): void
    {
        $pkce = new PKCE();
        $request = new TokenRequest(
            tokenUri: 'https://login.example.com/token',
            grantType: 'authorization_code',
            clientId: 'client-id',
            clientSecret: 'client-secret',
            code: 'the-code',
            redirectUri: 'https://app.example.com/callback',
            pkce: $pkce
        );

        self::assertSame([
            'grant_type' => 'authorization_code',
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'redirect_uri' => 'https://app.example.com/callback',
            'code' => 'the-code',
            'code_verifier' => $pkce->verifier,
        ], $request->getBody());
    }


    public function testAnAuthorizationCodeBodyWithoutPkceHasANullVerifier(): void
    {
        $request = new TokenRequest(
            tokenUri: 'https://login.example.com/token',
            grantType: 'authorization_code',
            clientId: 'client-id',
            clientSecret: 'client-secret',
            code: 'the-code',
            redirectUri: 'https://app.example.com/callback'
        );

        self::assertNull($request->getBody()['code_verifier']);
    }


    public function testARefreshTokenBodyCarriesTheRefreshToken(): void
    {
        $request = new TokenRequest(
            tokenUri: 'https://login.example.com/token',
            grantType: 'refresh_token',
            clientId: 'client-id',
            clientSecret: 'client-secret',
            refreshToken: 'the-refresh-token'
        );

        self::assertSame([
            'grant_type' => 'refresh_token',
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'refresh_token' => 'the-refresh-token',
        ], $request->getBody());
    }


    public function testAnUnknownGrantTypeSendsOnlyTheClientCredentials(): void
    {
        $request = new TokenRequest(
            tokenUri: 'https://login.example.com/token',
            grantType: 'client_credentials',
            clientId: 'client-id',
            clientSecret: 'client-secret'
        );

        self::assertSame([
            'grant_type' => 'client_credentials',
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
        ], $request->getBody());
    }
}
