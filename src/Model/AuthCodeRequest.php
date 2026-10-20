<?php

declare(strict_types=1);

namespace Gadget\Oauth\Model;

class AuthCodeRequest
{
    /** @var string $state */
    public string $state;


    /**
     * @param string $authUri
     * @param string $responseType
     * @param string $clientId
     * @param string $redirectUri
     * @param string $scope
     * @param string|null $state
     * @param PKCE|null $pkce
     * @param string|null $responseMode
     * @param string|null $nonce
     * @param string|null $display
     * @param string|null $prompt
     */
    public function __construct(
        public string $authUri,
        public string $responseType,
        public string $clientId,
        public string $redirectUri,
        public string $scope,
        string|null $state = null,
        public PKCE|null $pkce = null,
        public string|null $responseMode = null,
        public string|null $nonce = null,
        public string|null $display = null,
        public string|null $prompt = null
    ) {
        $this->state = $state ?? bin2hex(random_bytes(32));
        $this->nonce ??= $this->responseType === 'id_token'
            ? bin2hex(random_bytes(32))
            : null;
    }


    /**
     * @return array<string,bool|float|int|string|null>
     */
    public function getQueryParams(): array
    {
        return [
            'response_type' => $this->responseType,
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => $this->scope,
            'state' => $this->state,
            'code_challenge' => $this->pkce?->challenge,
            'code_challenge_method' => $this->pkce?->mode,
            'response_mode' => $this->responseMode,
            'nonce' => $this->nonce,
            'display' => $this->display,
            'prompt' => $this->prompt
        ];
    }
}
