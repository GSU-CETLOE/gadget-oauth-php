<?php

declare(strict_types=1);

namespace Gadget\Oauth\Model;

class Token
{
    /**
     * @param string $type
     * @param string $scope
     * @param int $createdOn
     * @param int $expiresIn
     * @param string|null $accessToken
     * @param string|null $idToken
     * @param string|null $refreshToken
     */
    public function __construct(
        public string $type,
        public string $scope,
        public int $createdOn,
        public int $expiresIn,
        public string|null $accessToken = null,
        public string|null $idToken = null,
        public string|null $refreshToken = null
    ) {
    }
}
