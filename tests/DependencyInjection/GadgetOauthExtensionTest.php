<?php

declare(strict_types=1);

namespace Gadget\Oauth\Tests\DependencyInjection;

use Gadget\Cache\CacheItemPool;
use Gadget\Http\Client\Client;
use Gadget\Http\DependencyInjection\GadgetHttpExtension;
use Gadget\Oauth\Client\AuthClient;
use Gadget\Oauth\DependencyInjection\GadgetOauthExtension;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class GadgetOauthExtensionTest extends TestCase
{
    private static function loadedContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new GadgetOauthExtension())->load([], $container);
        return $container;
    }


    public function testRegistersTheAuthClientAsAnAutowiredService(): void
    {
        $container = self::loadedContainer();

        self::assertTrue($container->hasDefinition(AuthClient::class));
        $definition = $container->getDefinition(AuthClient::class);
        self::assertTrue($definition->isAutowired());
        self::assertTrue($definition->isAutoconfigured());
    }


    public function testCompiledContainerBuildsAnAuthClientOnTopOfTheHttpBundle(): void
    {
        $container = self::loadedContainer();
        (new GadgetHttpExtension())->load([], $container);
        $container->register(ClientInterface::class, GuzzleClient::class);
        $container->register(HttpFactory::class, HttpFactory::class);
        foreach (
            [
                RequestFactoryInterface::class,
                ResponseFactoryInterface::class,
                ServerRequestFactoryInterface::class,
                StreamFactoryInterface::class,
                UploadedFileFactoryInterface::class,
                UriFactoryInterface::class,
            ] as $factoryInterface
        ) {
            $container->setAlias($factoryInterface, HttpFactory::class);
        }
        $container->register(CacheItemPoolInterface::class, ArrayAdapter::class);
        $container->register(CacheItemPool::class, CacheItemPool::class)->setAutowired(true);
        $container->getDefinition(AuthClient::class)->setPublic(true);
        $container->getDefinition(Client::class)->setPublic(true);

        $container->compile();

        $authClient = $container->get(AuthClient::class);
        self::assertInstanceOf(AuthClient::class, $authClient);
        self::assertInstanceOf(Client::class, $container->get(Client::class));
    }
}
