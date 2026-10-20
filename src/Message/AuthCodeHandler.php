<?php

declare(strict_types=1);

namespace Gadget\Oauth\Message;

use Gadget\Http\Client\Client;
use Gadget\Http\Message\MessageHandler;
use Gadget\Http\Message\RequestBuilder;
use Gadget\Oauth\Exception\AuthException;
use Gadget\Oauth\Model\AuthCodeRequest;
use Gadget\Oauth\Model\AuthCode;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** @extends MessageHandler<AuthCode> */
class AuthCodeHandler extends MessageHandler
{
    /**
     * @param AuthCodeRequest $authCodeRequest
     */
    public function __construct(private AuthCodeRequest $authCodeRequest)
    {
    }


    /**
     * @param RequestBuilder $requestBuilder
     * @return ServerRequestInterface
     */
    protected function createRequest(RequestBuilder $requestBuilder): ServerRequestInterface
    {
        return $requestBuilder
            ->setMethod('GET')
            ->setUri($this->authCodeRequest->authUri)
            ->setQueryParams($this->authCodeRequest->getQueryParams())
            ->getRequest();
    }


    /**
     * @param Client $client
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    protected function sendRequest(
        Client $client,
        ServerRequestInterface $request
    ): ResponseInterface {
        $response = parent::sendRequest($client, $request);
        $uri = ($response->getStatusCode() === 302)
            ? ($response->getHeader('Location')[0] ?? null)
            : null;

        while ($uri !== null && !str_starts_with($uri, $this->authCodeRequest->redirectUri)) {
            $response = $client->sendRequest(
                $client->getMessageFactory()->createServerRequest('GET', $uri)
            );

            $uri = ($response->getStatusCode() === 302)
                ? ($response->getHeader('Location')[0] ?? null)
                : null;
        }

        return $response;
    }


    /**
     * @param ResponseInterface $response
     * @param ServerRequestInterface $request
     * @return AuthCode
     */
    protected function handleResponse(
        ResponseInterface $response,
        ServerRequestInterface $request
    ): mixed {
        $uri = ($response->getStatusCode() === 302)
            ? ($response->getHeader('Location')[0] ?? null)
            : null;

        return ($uri !== null && str_starts_with($uri, $this->authCodeRequest->redirectUri))
            ? AuthCode::createFromUri(
                $this->authCodeRequest,
                $uri
            )
            : throw new AuthException();
    }
}
