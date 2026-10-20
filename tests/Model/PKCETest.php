<?php

declare(strict_types=1);

namespace Gadget\Oauth\Tests\Model;

use Gadget\Oauth\Model\PKCE;
use PHPUnit\Framework\TestCase;

final class PKCETest extends TestCase
{
    public function testUsesTheS256ChallengeMethod(): void
    {
        self::assertSame('S256', (new PKCE())->mode);
    }


    public function testTheVerifierLengthIsWithinTheRfc7636Bounds(): void
    {
        $length = strlen((new PKCE())->verifier);

        self::assertGreaterThanOrEqual(43, $length);
        self::assertLessThanOrEqual(128, $length);
    }


    public function testTheVerifierUsesOnlyUnreservedCharacters(): void
    {
        self::assertMatchesRegularExpression('/^[A-Za-z0-9\-._~]+$/', (new PKCE())->verifier);
    }


    public function testTheChallengeIsTheBase64UrlSha256OfTheVerifier(): void
    {
        $pkce = new PKCE();

        $expected = rtrim(strtr(base64_encode(hash('sha256', $pkce->verifier, true)), '+/', '-_'), '=');

        self::assertSame($expected, $pkce->challenge);
        self::assertSame(43, strlen($pkce->challenge));
    }


    public function testEachInstanceGetsItsOwnVerifier(): void
    {
        self::assertNotSame((new PKCE())->verifier, (new PKCE())->verifier);
    }
}
