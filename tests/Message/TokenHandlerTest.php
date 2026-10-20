<?php

declare(strict_types=1);

namespace Gadget\Oauth\Tests\Message;

use Gadget\Http\Exception\ResponseException;
use Gadget\Oauth\Exception\AuthException;
use Gadget\Oauth\Message\TokenHandler;
use Gadget\Oauth\Model\PKCE;
use Gadget\Oauth\Model\TokenRequest;
use Gadget\Oauth\Tests\Support\OauthTestCase;
use GuzzleHttp\Psr7\Response;

final class TokenHandlerTest extends OauthTestCase
{
    private const TOKEN_URI = 'https://login.example.com/token';


    private static function codeRequest(PKCE|null $pkce = null): TokenRequest
    {
        return new TokenRequest(
            tokenUri: self::TOKEN_URI,
            grantType: 'authorization_code',
            clientId: 'client-id',
            clientSecret: 'client-secret',
            code: 'the-code',
            redirectUri: 'https://app.example.com/callback',
            pkce: $pkce
        );
    }


    /** @return array<string,mixed> */
    private static function tokenBody(): array
    {
        return [
            'token_type' => 'Bearer',
            'scope' => 'openid profile',
            'created_on' => 1700000000,
            'expires_in' => 3600,
            'access_token' => 'the-access-token',
            'id_token' => 'the-id-token',
            'refresh_token' => 'the-refresh-token',
        ];
    }


    public function testPostsTheFormEncodedTokenRequest(): void
    {
        $pkce = new PKCE();
        $this->transport->append(self::jsonResponse(self::tokenBody()));

        (new TokenHandler(self::codeRequest($pkce)))->invoke($this->createClient());

        $sent = $this->sentRequest();
        self::assertSame('POST', $sent->getMethod());
        self::assertSame(self::TOKEN_URI, (string) $sent->getUri());
        self::assertSame('application/x-www-form-urlencoded', $sent->getHeaderLine('Content-Type'));
        self::assertSame([
            'grant_type' => 'authorization_code',
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'redirect_uri' => 'https://app.example.com/callback',
            'code' => 'the-code',
            'code_verifier' => $pkce->verifier,
        ], self::formBodyOf($sent));
    }


    public function testBuildsATokenFromTheJsonResponse(): void
    {
        $this->transport->append(self::jsonResponse(self::tokenBody()));

        $token = (new TokenHandler(self::codeRequest()))->invoke($this->createClient());

        self::assertSame('Bearer', $token->type);
        self::assertSame('openid profile', $token->scope);
        self::assertSame(1700000000, $token->createdOn);
        self::assertSame(3600, $token->expiresIn);
        self::assertSame('the-access-token', $token->accessToken);
        self::assertSame('the-id-token', $token->idToken);
        self::assertSame('the-refresh-token', $token->refreshToken);
    }


    public function testCreatedOnDefaultsToNowWhenTheServerDoesNotSendIt(): void
    {
        $body = self::tokenBody();
        unset($body['created_on']);
        $this->transport->append(self::jsonResponse($body));

        $before = time();
        $token = (new TokenHandler(self::codeRequest()))->invoke($this->createClient());
        $after = time();

        self::assertGreaterThanOrEqual($before, $token->createdOn);
        self::assertLessThanOrEqual($after, $token->createdOn);
    }


    public function testTheOptionalTokensAreNullWhenAbsent(): void
    {
        $this->transport->append(self::jsonResponse([
            'token_type' => 'Bearer',
            'scope' => 'openid',
        ]));

        $token = (new TokenHandler(self::codeRequest()))->invoke($this->createClient());

        self::assertSame(0, $token->expiresIn);
        self::assertNull($token->accessToken);
        self::assertNull($token->idToken);
        self::assertNull($token->refreshToken);
    }


    public function testANonSuccessStatusIsAResponseExceptionCausedByAnAuthException(): void
    {
        $this->transport->append(
            new Response(400, ['Content-Type' => 'application/json'], '{"error":"invalid_grant"}')
        );

        try {
            (new TokenHandler(self::codeRequest()))->invoke($this->createClient());
            self::fail('Expected a ResponseException');
        } catch (ResponseException $e) {
            self::assertSame('Error handling response: POST https://login.example.com/token => 400', $e->getMessage());
            self::assertInstanceOf(AuthException::class, $e->getPrevious());
        }
    }


    public function testAMalformedBodyIsAResponseException(): void
    {
        $this->transport->append(new Response(200, [], 'not json'));

        try {
            (new TokenHandler(self::codeRequest()))->invoke($this->createClient());
            self::fail('Expected a ResponseException');
        } catch (ResponseException $e) {
            self::assertInstanceOf(\JsonException::class, $e->getPrevious());
        }
    }


    public function testARefreshRequestPostsTheRefreshToken(): void
    {
        $this->transport->append(self::jsonResponse(self::tokenBody()));
        $request = new TokenRequest(
            tokenUri: self::TOKEN_URI,
            grantType: 'refresh_token',
            clientId: 'client-id',
            clientSecret: 'client-secret',
            refreshToken: 'the-refresh-token'
        );

        (new TokenHandler($request))->invoke($this->createClient());

        self::assertSame([
            'grant_type' => 'refresh_token',
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'refresh_token' => 'the-refresh-token',
        ], self::formBodyOf($this->sentRequest()));
    }
}
