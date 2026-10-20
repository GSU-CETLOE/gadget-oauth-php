<?php

declare(strict_types=1);

namespace Gadget\Oauth\Message;

use Gadget\Http\Message\MessageHandler;
use Gadget\Http\Message\RequestBuilder;
use Gadget\Io\Cast;
use Gadget\Io\JSON;
use Gadget\Oauth\Exception\AuthException;
use Gadget\Oauth\Model\TokenRequest;
use Gadget\Oauth\Model\Token;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** @extends MessageHandler<Token> */
class TokenHandler extends MessageHandler
{
    /**
     * @param TokenRequest $tokenRequest
     */
    public function __construct(private TokenRequest $tokenRequest)
    {
    }


    /**
     * @param RequestBuilder $requestBuilder
     * @return ServerRequestInterface
     */
    protected function createRequest(RequestBuilder $requestBuilder): ServerRequestInterface
    {
        return $requestBuilder
            ->setMethod('POST')
            ->setUri($this->tokenRequest->tokenUri)
            ->setBody(
                'application/x-www-form-urlencoded',
                $this->tokenRequest->getBody()
            )
            ->getRequest();
    }


    /**
     * @param ResponseInterface $response
     * @param ServerRequestInterface $request
     * @return Token
     */
    protected function handleResponse(
        ResponseInterface $response,
        ServerRequestInterface $request
    ): mixed {
        return ($response->getStatusCode() === 200)
            ? $this->createToken($response)
            : throw new AuthException();
    }


    /**
     * @param ResponseInterface $response
     * @return Token
     */
    protected function createToken(ResponseInterface $response): Token
    {
        $values = Cast::toArray(JSON::decode($response->getBody()->getContents()));
        return new Token(
            type: Cast::toString($values['token_type'] ?? null),
            scope: Cast::toString($values['scope'] ?? null),
            createdOn: Cast::toInt($values['created_on'] ?? time()),
            expiresIn: Cast::toInt($values['expires_in'] ?? 0),
            accessToken: Cast::toValueOrNull($values['access_token'] ?? null, Cast::toString(...)),
            idToken: Cast::toValueOrNull($values['id_token'] ?? null, Cast::toString(...)),
            refreshToken: Cast::toValueOrNull($values['refresh_token'] ?? null, Cast::toString(...))
        );
    }
}
