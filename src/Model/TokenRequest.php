<?php

declare(strict_types=1);

namespace Gadget\Oauth\Model;

class TokenRequest
{
    /**
     * @param string $tokenUri
     * @param string $grantType
     * @param string $clientId
     * @param string $clientSecret
     * @param string|null $code
     * @param string|null $redirectUri
     * @param PKCE|null $pkce
     * @param string|null $refreshToken
     */
    public function __construct(
        public string $tokenUri,
        public string $grantType,
        public string $clientId,
        public string $clientSecret,
        public string|null $code = null,
        public string|null $redirectUri = null,
        public PKCE|null $pkce = null,
        public string|null $refreshToken = null
    ) {
    }


    /**
     * @return array<string,mixed>
     */
    public function getBody(): array
    {
        return [
            'grant_type' => $this->grantType,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret
        ] + match ($this->grantType) {
            'authorization_code' => [
                'redirect_uri' => $this->redirectUri,
                'code' => $this->code,
                'code_verifier' => $this->pkce?->verifier
            ],
            'refresh_token' => [
                'refresh_token' => $this->refreshToken
            ],
            default => []
        };
    }
}
