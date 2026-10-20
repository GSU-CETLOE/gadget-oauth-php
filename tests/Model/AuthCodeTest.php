<?php

declare(strict_types=1);

namespace Gadget\Oauth\Tests\Model;

use Gadget\Oauth\Exception\AuthException;
use Gadget\Oauth\Model\AuthCode;
use Gadget\Oauth\Model\AuthCodeRequest;
use Gadget\Oauth\Model\PKCE;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\TestCase;

final class AuthCodeTest extends TestCase
{
    private const REDIRECT_URI = 'https://app.example.com/callback';


    private static function request(
        string $responseType = 'code',
        string $state = 'xyz',
        PKCE|null $pkce = null,
        string|null $nonce = null
    ): AuthCodeRequest {
        return new AuthCodeRequest(
            authUri: 'https://login.example.com/authorize',
            responseType: $responseType,
            clientId: 'client-id',
            redirectUri: self::REDIRECT_URI,
            scope: 'openid',
            state: $state,
            pkce: $pkce,
            nonce: $nonce
        );
    }


    public function testReadsTheCodeAndStateFromTheRedirectUri(): void
    {
        $pkce = new PKCE();

        $authCode = AuthCode::createFromUri(
            self::request(pkce: $pkce),
            self::REDIRECT_URI . '?code=the-code&state=xyz'
        );

        self::assertSame(self::REDIRECT_URI, $authCode->redirectUri);
        self::assertSame('the-code', $authCode->code);
        self::assertSame('xyz', $authCode->state);
        self::assertSame('code', $authCode->type);
        self::assertNull($authCode->nonce);
        self::assertSame($pkce, $authCode->pkce);
    }


    public function testAcceptsAPsrUri(): void
    {
        $authCode = AuthCode::createFromUri(self::request(), new Uri(self::REDIRECT_URI . '?code=the-code&state=xyz'));

        self::assertSame('the-code', $authCode->code);
    }


    public function testUrlDecodesTheQueryValues(): void
    {
        $authCode = AuthCode::createFromUri(
            self::request(state: 'a b/c'),
            self::REDIRECT_URI . '?code=abc%2Bdef%3D&state=a%20b%2Fc'
        );

        self::assertSame('abc+def=', $authCode->code);
        self::assertSame('a b/c', $authCode->state);
    }


    public function testReadsTheIdTokenForAnIdTokenResponse(): void
    {
        $authCode = AuthCode::createFromUri(
            self::request('id_token', nonce: 'the-nonce'),
            self::REDIRECT_URI . '?id_token=the-id-token&state=xyz'
        );

        self::assertSame('id_token', $authCode->type);
        self::assertSame('the-id-token', $authCode->code);
        self::assertSame('the-nonce', $authCode->nonce);
    }


    public function testARedirectToAnotherHostIsRejected(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage(
            'Redirect URI mismatch: Expected => https://app.example.com/callback, '
            . 'Actual => https://evil.example.com/callback'
        );

        AuthCode::createFromUri(self::request(), 'https://evil.example.com/callback?code=the-code&state=xyz');
    }


    public function testARedirectUriThatExtendsTheExpectedOneIsAccepted(): void
    {
        $authCode = AuthCode::createFromUri(self::request(), self::REDIRECT_URI . '/extra?code=the-code&state=xyz');

        self::assertSame(self::REDIRECT_URI . '/extra', $authCode->redirectUri);
    }


    public function testAStateThatDoesNotMatchIsRejected(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('State mismatch: Expected => xyz, Actual => other');

        AuthCode::createFromUri(self::request(), self::REDIRECT_URI . '?code=the-code&state=other');
    }


    public function testAMissingStateIsReportedAsNull(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('State mismatch: Expected => xyz, Actual => NULL');

        AuthCode::createFromUri(self::request(), self::REDIRECT_URI . '?code=the-code');
    }


    public function testAMissingCodeIsRejected(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Missing response code');

        AuthCode::createFromUri(self::request(), self::REDIRECT_URI . '?state=xyz');
    }


    public function testAnUnknownResponseTypeNeverFindsACode(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Missing response code');

        AuthCode::createFromUri(self::request('token'), self::REDIRECT_URI . '?code=the-code&state=xyz');
    }
}
