<?php

declare(strict_types=1);

namespace Gadget\Oauth\Tests\Model;

use Gadget\Oauth\Model\Token;
use PHPUnit\Framework\TestCase;

final class TokenTest extends TestCase
{
    public function testTheTokenValuesDefaultToNull(): void
    {
        $token = new Token(type: 'Bearer', scope: 'openid', createdOn: 1700000000, expiresIn: 3600);

        self::assertSame('Bearer', $token->type);
        self::assertSame('openid', $token->scope);
        self::assertSame(1700000000, $token->createdOn);
        self::assertSame(3600, $token->expiresIn);
        self::assertNull($token->accessToken);
        self::assertNull($token->idToken);
        self::assertNull($token->refreshToken);
    }


    public function testTheTokenValuesAreKept(): void
    {
        $token = new Token(
            type: 'Bearer',
            scope: 'openid',
            createdOn: 1700000000,
            expiresIn: 3600,
            accessToken: 'access',
            idToken: 'id',
            refreshToken: 'refresh'
        );

        self::assertSame('access', $token->accessToken);
        self::assertSame('id', $token->idToken);
        self::assertSame('refresh', $token->refreshToken);
    }
}
