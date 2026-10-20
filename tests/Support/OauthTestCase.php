<?php

declare(strict_types=1);

namespace Gadget\Oauth\Tests\Support;

use Gadget\Cache\CacheItemPool;
use Gadget\Http\Client\Client;
use Gadget\Http\Message\MessageFactory;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Builds the gadget-http Client on a real Guzzle client whose transport is a MockHandler queue.
 * Guzzle is told not to follow redirects and not to throw on 4xx/5xx, so the OAuth handlers see
 * every raw response exactly as an authorization server would send it. Every request Guzzle
 * would have put on the wire is recorded in $sentRequests.
 */
abstract class OauthTestCase extends TestCase
{
    protected MockHandler $transport;

    /** @var RequestInterface[] */
    protected array $sentRequests = [];

    protected GuzzleClient $guzzle;


    protected function setUp(): void
    {
        $this->transport = new MockHandler();
        $this->sentRequests = [];

        $stack = HandlerStack::create($this->transport);
        $stack->push(fn(callable $next): callable => function (RequestInterface $request, array $options) use ($next) {
            $this->sentRequests[] = $request;
            return $next($request, $options);
        });

        $this->guzzle = new GuzzleClient([
            'handler' => $stack,
            'allow_redirects' => false,
            'http_errors' => false,
        ]);
    }


    /**
     * Builds a Client over the test transport with an in-memory cookie jar cache.
     *
     * @return Client
     */
    protected function createClient(): Client
    {
        $factory = new HttpFactory();
        return new Client(
            $this->guzzle,
            new MessageFactory($factory, $factory, $factory, $factory, $factory, $factory),
            new CacheItemPool(new ArrayAdapter())
        );
    }


    /**
     * Builds the 302 response an authorization server sends to move the browser along.
     *
     * @param string $location
     * @param array<string,string> $headers
     * @return Response
     */
    protected static function redirectTo(string $location, array $headers = []): Response
    {
        return new Response(302, ['Location' => $location] + $headers);
    }


    /**
     * Builds a 200 JSON response.
     *
     * @param mixed[] $body
     * @return Response
     */
    protected static function jsonResponse(array $body): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR));
    }


    /**
     * Returns the request that reached the transport at the given position.
     *
     * @param int $index
     * @return RequestInterface
     */
    protected function sentRequest(int $index = 0): RequestInterface
    {
        self::assertArrayHasKey($index, $this->sentRequests, "No request reached the transport at position {$index}");
        return $this->sentRequests[$index];
    }


    /**
     * Parses the query string of a request into name => value pairs.
     *
     * @param RequestInterface $request
     * @return array<array-key,mixed>
     */
    protected static function queryOf(RequestInterface $request): array
    {
        parse_str($request->getUri()->getQuery(), $query);
        return $query;
    }


    /**
     * Parses a form-encoded request body into name => value pairs.
     *
     * @param RequestInterface $request
     * @return array<array-key,mixed>
     */
    protected static function formBodyOf(RequestInterface $request): array
    {
        parse_str((string) $request->getBody(), $body);
        return $body;
    }
}
