<?php

declare(strict_types=1);

namespace Gadget\Oauth\Tests\Message;

use Gadget\Http\Exception\ResponseException;
use Gadget\Oauth\Exception\AuthException;
use Gadget\Oauth\Message\AuthCodeHandler;
use Gadget\Oauth\Model\AuthCodeRequest;
use Gadget\Oauth\Model\PKCE;
use Gadget\Oauth\Tests\Support\OauthTestCase;
use GuzzleHttp\Psr7\Response;

final class AuthCodeHandlerTest extends OauthTestCase
{
    private const AUTH_URI = 'https://login.example.com/authorize';
    private const REDIRECT_URI = 'https://app.example.com/callback';


    private static function request(PKCE|null $pkce = null): AuthCodeRequest
    {
        return new AuthCodeRequest(
            authUri: self::AUTH_URI,
            responseType: 'code',
            clientId: 'client-id',
            redirectUri: self::REDIRECT_URI,
            scope: 'openid profile',
            state: 'xyz',
            pkce: $pkce
        );
    }


    public function testSendsTheAuthorizationRequestAsAGetWithTheQueryParams(): void
    {
        $pkce = new PKCE();
        $this->transport->append(self::redirectTo(self::REDIRECT_URI . '?code=the-code&state=xyz'));

        $authCode = (new AuthCodeHandler(self::request($pkce)))->invoke($this->createClient());

        $sent = $this->sentRequest();
        self::assertSame('GET', $sent->getMethod());
        self::assertSame('login.example.com', $sent->getUri()->getHost());
        self::assertSame('/authorize', $sent->getUri()->getPath());
        self::assertSame([
            'response_type' => 'code',
            'client_id' => 'client-id',
            'redirect_uri' => self::REDIRECT_URI,
            'scope' => 'openid profile',
            'state' => 'xyz',
            'code_challenge' => $pkce->challenge,
            'code_challenge_method' => 'S256',
        ], self::queryOf($sent));
        self::assertSame('the-code', $authCode->code);
        self::assertSame('xyz', $authCode->state);
        self::assertSame($pkce, $authCode->pkce);
    }


    public function testFollowsIntermediateRedirectsUntilTheRedirectUri(): void
    {
        $this->transport->append(
            self::redirectTo('https://login.example.com/step-two'),
            self::redirectTo('https://sso.example.com/step-three?x=1'),
            self::redirectTo(self::REDIRECT_URI . '?code=the-code&state=xyz')
        );

        $authCode = (new AuthCodeHandler(self::request()))->invoke($this->createClient());

        self::assertCount(3, $this->sentRequests);
        self::assertSame('https://login.example.com/step-two', (string) $this->sentRequest(1)->getUri());
        self::assertSame('https://sso.example.com/step-three?x=1', (string) $this->sentRequest(2)->getUri());
        self::assertSame('the-code', $authCode->code);
    }


    public function testCarriesCookiesAcrossTheRedirectHops(): void
    {
        $this->transport->append(
            self::redirectTo('https://login.example.com/step-two', ['Set-Cookie' => 'session=abc; Path=/']),
            self::redirectTo(self::REDIRECT_URI . '?code=the-code&state=xyz')
        );

        (new AuthCodeHandler(self::request()))->invoke($this->createClient());

        self::assertSame('session=abc', $this->sentRequest(1)->getHeaderLine('Cookie'));
    }


    public function testANonRedirectResponseIsAResponseExceptionCausedByAnAuthException(): void
    {
        $this->transport->append(new Response(200, [], '<html>login form</html>'));

        try {
            (new AuthCodeHandler(self::request()))->invoke($this->createClient());
            self::fail('Expected a ResponseException');
        } catch (ResponseException $e) {
            self::assertStringStartsWith(
                'Error handling response: GET https://login.example.com/authorize?',
                $e->getMessage()
            );
            self::assertStringEndsWith(' => 200', $e->getMessage());
            self::assertInstanceOf(AuthException::class, $e->getPrevious());
        }
    }


    public function testARedirectChainThatEndsAwayFromTheRedirectUriIsRejected(): void
    {
        $this->transport->append(
            self::redirectTo('https://login.example.com/step-two'),
            new Response(200, [], '<html>error page</html>')
        );

        $this->expectException(ResponseException::class);

        (new AuthCodeHandler(self::request()))->invoke($this->createClient());
    }


    public function testARedirectToTheRedirectUriWithTheWrongStateIsRejected(): void
    {
        $this->transport->append(self::redirectTo(self::REDIRECT_URI . '?code=the-code&state=other'));

        try {
            (new AuthCodeHandler(self::request()))->invoke($this->createClient());
            self::fail('Expected a ResponseException');
        } catch (ResponseException $e) {
            $cause = $e->getPrevious();
            self::assertInstanceOf(AuthException::class, $cause);
            self::assertSame('State mismatch: Expected => xyz, Actual => other', $cause->getMessage());
        }
    }


    public function testOnlyA302IsTreatedAsARedirect(): void
    {
        $this->transport->append(new Response(303, ['Location' => self::REDIRECT_URI . '?code=the-code&state=xyz']));

        $this->expectException(ResponseException::class);

        (new AuthCodeHandler(self::request()))->invoke($this->createClient());
    }
}
