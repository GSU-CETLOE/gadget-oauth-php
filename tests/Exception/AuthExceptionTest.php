<?php

declare(strict_types=1);

namespace Gadget\Oauth\Tests\Exception;

use Gadget\Exception\Exception as GadgetException;
use Gadget\Oauth\Exception\AuthException;
use PHPUnit\Framework\TestCase;

final class AuthExceptionTest extends TestCase
{
    public function testDescendsFromTheGadgetBaseException(): void
    {
        self::assertInstanceOf(GadgetException::class, new AuthException());
    }


    public function testTheDefaultMessageIsEmpty(): void
    {
        self::assertSame('', (new AuthException())->getMessage());
    }


    public function testTheMessageCanBeASprintfTemplateWithArguments(): void
    {
        $previous = new \RuntimeException('root cause');

        $exception = new AuthException(['%s mismatch: %s', ['State', 'xyz']], $previous);

        self::assertSame('State mismatch: xyz', $exception->getMessage());
        self::assertSame($previous, $exception->getPrevious());
    }
}
