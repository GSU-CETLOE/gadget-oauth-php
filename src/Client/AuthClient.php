<?php

declare(strict_types=1);

namespace Gadget\Oauth\Client;

use Gadget\Http\Client\ApiClient;
use Gadget\Http\Client\Client;
use Gadget\Oauth\Exception\AuthException;
use Gadget\Oauth\Message\AuthCodeHandler;
use Gadget\Oauth\Message\TokenHandler;
use Gadget\Oauth\Model\AuthCode;
use Gadget\Oauth\Model\AuthCodeRequest;
use Gadget\Oauth\Model\PKCE;
use Gadget\Oauth\Model\Token;
use Gadget\Oauth\Model\TokenRequest;

class AuthClient extends ApiClient
{
    /**
     * @param Client $client
     */
    public function __construct(
        Client $client,
        private string|null $authUri = null,
        private string|null $tokenUri = null,
        private string|null $clientId = null,
        private string|null $clientSecret = null,
        private string|null $scope = null,
        private string|null $redirectUri = null
    ) {
        parent::__construct($client);
    }


    /**
     * @param string|null $state
     * @param PKCE|null $pkce
     * @param string|null $authUri
     * @param string|null $clientId
     * @param string|null $redirectUri
     * @param string|null $scope
     * @return AuthCode
     */
    public function createAuthCode(
        string|null $state = null,
        PKCE|null $pkce = null,
        string|null $authUri = null,
        string|null $clientId = null,
        string|null $redirectUri = null,
        string|null $scope = null
    ): AuthCode {
        $authUri ??= $this->authUri ?? throw new AuthException();
        $clientId ??= $this->clientId ?? throw new AuthException();
        $redirectUri ??= $this->redirectUri ?? throw new AuthException();
        $scope ??= $this->scope ?? throw new AuthException();

        return $this->invoke(new AuthCodeHandler(new AuthCodeRequest(
            authUri: $authUri,
            responseType: 'code',
            clientId: $clientId,
            redirectUri: $redirectUri,
            scope: $scope,
            state: $state,
            pkce: $pkce
        )));
    }


    /**
     * @param string $code
     * @param PKCE|null $pkce
     * @param string|null $tokenUri
     * @param string|null $clientId
     * @param string|null $clientSecret
     * @param string|null $redirectUri
     * @return Token
     */
    public function createToken(
        string $code,
        PKCE|null $pkce = null,
        string|null $tokenUri = null,
        string|null $clientId = null,
        string|null $clientSecret = null,
        string|null $redirectUri = null,
    ): Token {
        $tokenUri ??= $this->tokenUri ?? throw new AuthException();
        $clientId ??= $this->clientId ?? throw new AuthException();
        $clientSecret ??= $this->clientSecret ?? throw new AuthException();
        $redirectUri ??= $this->redirectUri ?? throw new AuthException();

        return $this->invoke(new TokenHandler(new TokenRequest(
            tokenUri: $tokenUri,
            grantType: 'authorization_code',
            clientId: $clientId,
            clientSecret: $clientSecret,
            code: $code,
            redirectUri: $redirectUri,
            pkce: $pkce
        )));
    }


    /**
     * @param string $refreshToken
     * @param string|null $tokenUri
     * @param string|null $clientId
     * @param string|null $clientSecret
     * @return Token
     */
    public function refreshToken(
        string $refreshToken,
        string|null $tokenUri = null,
        string|null $clientId = null,
        string|null $clientSecret = null,
    ): Token {
        $tokenUri ??= $this->tokenUri ?? throw new AuthException();
        $clientId ??= $this->clientId ?? throw new AuthException();
        $clientSecret ??= $this->clientSecret ?? throw new AuthException();

        return $this->invoke(new TokenHandler(new TokenRequest(
            tokenUri: $tokenUri,
            grantType: 'refresh_token',
            clientId: $clientId,
            clientSecret: $clientSecret,
            refreshToken: $refreshToken
        )));
    }
}
