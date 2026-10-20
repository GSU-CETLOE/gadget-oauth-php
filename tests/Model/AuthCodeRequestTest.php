<?php

declare(strict_types=1);

namespace Gadget\Oauth\Tests\Model;

use Gadget\Oauth\Model\AuthCodeRequest;
use Gadget\Oauth\Model\PKCE;
use PHPUnit\Framework\TestCase;

final class AuthCodeRequestTest extends TestCase
{
    private static function request(string $responseType = 'code', string|null $state = null): AuthCodeRequest
    {
        return new AuthCodeRequest(
            authUri: 'https://login.example.com/authorize',
            responseType: $responseType,
            clientId: 'client-id',
            redirectUri: 'https://app.example.com/callback',
            scope: 'openid profile',
            state: $state
        );
    }


    public function testGeneratesAStateWhenNoneIsGiven(): void
    {
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', self::request()->state);
    }


    public function testKeepsAGivenState(): void
    {
        self::assertSame('given-state', self::request(state: 'given-state')->state);
    }


    public function testGeneratesANonceOnlyForIdTokenResponses(): void
    {
        self::assertNull(self::request('code')->nonce);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', self::request('id_token')->nonce ?? '');
    }


    public function testKeepsAGivenNonce(): void
    {
        $request = new AuthCodeRequest(
            authUri: 'https://login.example.com/authorize',
            responseType: 'id_token',
            clientId: 'client-id',
            redirectUri: 'https://app.example.com/callback',
            scope: 'openid',
            nonce: 'given-nonce'
        );

        self::assertSame('given-nonce', $request->nonce);
    }


    public function testQueryParamsCarryTheRequestFieldsAndLeaveTheRestNull(): void
    {
        $request = self::request(state: 'xyz');

        self::assertSame([
            'response_type' => 'code',
            'client_id' => 'client-id',
            'redirect_uri' => 'https://app.example.com/callback',
            'scope' => 'openid profile',
            'state' => 'xyz',
            'code_challenge' => null,
            'code_challenge_method' => null,
            'response_mode' => null,
            'nonce' => null,
            'display' => null,
            'prompt' => null,
        ], $request->getQueryParams());
    }


    public function testQueryParamsIncludeThePkceChallengeAndTheOptionalFields(): void
    {
        $pkce = new PKCE();
        $request = new AuthCodeRequest(
            authUri: 'https://login.example.com/authorize',
            responseType: 'code',
            clientId: 'client-id',
            redirectUri: 'https://app.example.com/callback',
            scope: 'openid',
            state: 'xyz',
            pkce: $pkce,
            responseMode: 'query',
            nonce: 'n',
            display: 'page',
            prompt: 'login'
        );

        $params = $request->getQueryParams();

        self::assertSame($pkce->challenge, $params['code_challenge']);
        self::assertSame('S256', $params['code_challenge_method']);
        self::assertSame('query', $params['response_mode']);
        self::assertSame('n', $params['nonce']);
        self::assertSame('page', $params['display']);
        self::assertSame('login', $params['prompt']);
    }
}
