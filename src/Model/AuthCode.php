<?php

declare(strict_types=1);

namespace Gadget\Oauth\Model;

use Gadget\Oauth\Exception\AuthException;
use Psr\Http\Message\UriInterface;

class AuthCode
{
    /**
     * @param AuthCodeRequest $authRequest
     * @param UriInterface|string $uri
     * @return self
     */
    public static function createFromUri(
        AuthCodeRequest $authRequest,
        UriInterface|string $uri
    ): self {
        if (!is_string($uri)) {
            $uri = $uri->__toString();
        }

        list($redirectUri, $query) = [...explode("?", $uri, 2), '', ''];
        /** @var array<string,string> $queryParams */
        $queryParams = array_map(
            urldecode(...),
            array_column(array_map(fn($v) => [...explode('=', $v, 2), ''], explode("&", $query)), 1, 0)
        );

        $state = $queryParams['state'] ?? null;
        $code = match ($authRequest->responseType) {
            'code' => $queryParams['code'] ?? null,
            'id_token' => $queryParams['id_token'] ?? null,
            default => null
        };

        if (!str_starts_with($redirectUri, $authRequest->redirectUri)) {
            throw new AuthException([
                "Redirect URI mismatch: Expected => %s, Actual => %s",
                [
                    $authRequest->redirectUri,
                    $redirectUri
                ]
            ]);
        }

        if ($state !== $authRequest->state) {
            throw new AuthException([
                "State mismatch: Expected => %s, Actual => %s",
                [
                    $authRequest->state,
                    $state ?? 'NULL'
                ]
            ]);
        }

        if ($code === null) {
            throw new AuthException("Missing response code");
        }

        return new self(
            redirectUri: $redirectUri,
            state: $state,
            type: $authRequest->responseType,
            code: $code,
            nonce: $authRequest->nonce,
            pkce: $authRequest->pkce
        );
    }


    /**
     * @param string $redirectUri
     * @param string $state
     * @param string $type
     * @param string $code
     * @param string $nonce
     * @param PKCE|null $pkce
     */
    public function __construct(
        public string $redirectUri,
        public string $state,
        public string $type,
        public string $code,
        public string|null $nonce,
        public PKCE|null $pkce
    ) {
    }
}
