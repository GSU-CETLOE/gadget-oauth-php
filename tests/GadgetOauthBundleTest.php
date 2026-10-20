<?php

declare(strict_types=1);

namespace Gadget\Oauth\Tests;

use Gadget\Oauth\DependencyInjection\GadgetOauthExtension;
use Gadget\Oauth\GadgetOauthBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class GadgetOauthBundleTest extends TestCase
{
    public function testIsASymfonyBundle(): void
    {
        self::assertInstanceOf(Bundle::class, new GadgetOauthBundle());
    }


    public function testExposesTheGadgetOauthExtension(): void
    {
        $extension = (new GadgetOauthBundle())->getContainerExtension();

        self::assertInstanceOf(GadgetOauthExtension::class, $extension);
        self::assertSame('gadget_oauth', $extension->getAlias());
    }
}
