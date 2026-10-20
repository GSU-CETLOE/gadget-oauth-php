# gadget-oauth-php

An OAuth 2.0 authorization code client for PHP, built on [gadget-http-php](https://github.com/GSU-CETLOE/gadget-http-php). `AuthClient` asks an authorization server for a code, follows the server's redirects until it lands on your redirect URI, exchanges the code for a token, and refreshes tokens later. PKCE is built in. A Symfony bundle registers the client for autowiring.

The flow is driven from PHP, not from a browser. That makes it a fit for scripted and headless integrations where the HTTP client's cookie jar already holds a session, or where the authorization server approves the client without a login page. If the server answers with a login form instead of a redirect, the call fails and tells you so.

CETLOE at Georgia State University maintains this package. It started as `mjfk23/gadget-oauth`, written by Melody Kimball.

## Requirements

- PHP 8.1
- A `Gadget\Http\Client\Client` from gadget-http-php, which in turn needs a PSR-18 client, PSR-17 factories, and a PSR-6 cache pool. The examples below use Guzzle and [symfony/cache](https://symfony.com/doc/current/components/cache.html). Guzzle comes with gadget-http-php; symfony/cache is only a dev dependency here, so add it to your own project if you want it.

## Setup

Install [Composer](https://getcomposer.org/) if you don't have it, then add the package to your project:

```sh
composer require gsu-cetloe/gadget-oauth-php
```

To work on the library itself, clone the repository and install the development dependencies:

```sh
git clone https://github.com/GSU-CETLOE/gadget-oauth-php.git
cd gadget-oauth-php
composer install
```

Your machine does not have to run PHP 8.1. Composer is told to resolve dependencies as if it were PHP 8.1.0 no matter what version runs it, so the lock file always reflects what the library targets. Before a release, run the suite on an actual 8.1 build.

## Development commands

| Command | What it does |
| --- | --- |
| `composer test` | Runs the PHPUnit suite in `tests/`. |
| `composer phpstan` | Runs PHPStan at level 10 over `src/`, `bundle/`, and `tests/`. |
| `composer phpcs` | Checks `src/`, `bundle/`, and `tests/` against PSR-12. |
| `composer check` | Runs all three in order. Run this before you commit. |

Anything generated, such as the PHPStan and PHPUnit caches, lands in `working/`. Git ignores that directory.

## Building the client

`AuthClient` wraps a gadget-http `Client` and holds the settings every call needs: the authorization and token endpoints, the client id and secret, the scope, and the redirect URI. Each of them can be left `null` here and passed per call instead.

```php
use Gadget\Cache\CacheItemPool;
use Gadget\Http\Client\Client;
use Gadget\Http\Message\MessageFactory;
use Gadget\Oauth\Client\AuthClient;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

$factory = new HttpFactory();

$http = new Client(
    new GuzzleClient(['allow_redirects' => false, 'http_errors' => false]),
    new MessageFactory($factory, $factory, $factory, $factory, $factory, $factory),
    new CacheItemPool(new FilesystemAdapter())
);

$auth = new AuthClient(
    $http,
    authUri: 'https://login.example.com/oauth2/authorize',
    tokenUri: 'https://login.example.com/oauth2/token',
    clientId: 'my-client-id',
    clientSecret: 'my-client-secret',
    scope: 'openid profile offline_access',
    redirectUri: 'https://app.example.com/callback'
);
```

Two Guzzle options matter here:

- `allow_redirects => false`. The authorization code step follows redirects itself so it can stop at your redirect URI and read the code off the `Location` header. If Guzzle follows them first, the client never sees that redirect and would try to fetch your callback page instead.
- `http_errors => false`. With Guzzle's default, a `400` from the token endpoint surfaces as a transport failure (`Gadget\Http\Exception\ClientException`) before this library can look at it. With it off, the status check in the token handler runs and you get the `ResponseException` described under Errors.

A persistent cache adapter keeps the cookie jar between PHP processes, so a session cookie set during one run is still sent on the next.

## Getting an authorization code

```php
use Gadget\Oauth\Model\PKCE;

$pkce = new PKCE();
$authCode = $auth->createAuthCode(pkce: $pkce);

$authCode->code;        // the code to exchange
$authCode->state;       // the state the server echoed back
$authCode->redirectUri; // where the server sent us, starting with your redirect URI
$authCode->pkce;        // the same PKCE object, ready for createToken()
```

`createAuthCode()` sends a `GET` to the authorization endpoint with `response_type=code`, the client id, redirect URI, scope, state, and the PKCE challenge when you pass one. It then follows every `302` the server returns, carrying cookies along, until a `Location` starts with your redirect URI. The code and state are read from that URI's query string, the state is checked against the one that was sent, and the result is an `AuthCode`.

A state is generated for you as 64 hex characters if you don't pass one. You can also pass `authUri`, `clientId`, `redirectUri`, and `scope` per call to override the client settings.

`PKCE` makes a fresh random verifier of 43 to 128 unreserved characters and the matching `S256` challenge each time it is constructed. Keep the object and hand it to `createToken()`; the verifier never leaves your process until then.

### Handling the redirect yourself

When a real person signs in through a browser, you build the authorization URL, send them there, and later receive the redirect on your callback. `AuthCodeRequest` and `AuthCode::createFromUri()` cover both ends:

```php
use Gadget\Http\Message\RequestBuilder;
use Gadget\Oauth\Model\AuthCode;
use Gadget\Oauth\Model\AuthCodeRequest;

$request = new AuthCodeRequest(
    authUri: 'https://login.example.com/oauth2/authorize',
    responseType: 'code',
    clientId: 'my-client-id',
    redirectUri: 'https://app.example.com/callback',
    scope: 'openid profile',
    pkce: new PKCE()
);

// Store $request (or at least its state and pkce) in the session, then redirect to:
$url = $request->authUri . '?' . RequestBuilder::createQuery($request->getQueryParams());

// On the callback, with the stored request and the full URL the browser arrived on:
$authCode = AuthCode::createFromUri($request, $callbackUrl);
```

`createFromUri()` rejects a URI that does not start with the request's redirect URI, a state that does not match, and a missing code, each with an `AuthException` that says what was expected and what arrived. For `responseType: 'id_token'` it reads the `id_token` parameter instead of `code`, and a nonce is generated for that response type if you don't supply one.

## Exchanging the code for a token

```php
$token = $auth->createToken($authCode->code, $authCode->pkce);

$token->accessToken;
$token->refreshToken;
$token->idToken;
$token->type;       // e.g. "Bearer"
$token->scope;
$token->expiresIn;  // seconds, as sent by the server
$token->createdOn;  // unix time; the server's created_on if present, otherwise now
```

`createToken()` posts a form-encoded `authorization_code` grant with the client id, client secret, redirect URI, code, and the PKCE verifier when you pass the PKCE object. The response must be a `200` with a JSON body; anything else is an error. `access_token`, `id_token`, and `refresh_token` are `null` when the server leaves them out, and `expires_in` defaults to `0`. The server has to send `token_type` and `scope`; a response without them is treated as malformed.

`tokenUri`, `clientId`, `clientSecret`, and `redirectUri` can be passed per call.

## Refreshing a token

```php
$fresh = $auth->refreshToken($token->refreshToken);
```

This posts a `refresh_token` grant with the client id and secret and returns a new `Token`. It does not need a redirect URI, so a client built only for refreshing can leave that setting out.

## Errors

All of this library's own failures are `Gadget\Oauth\Exception\AuthException`, which extends `Gadget\Exception\Exception` from gadget-core-php. Where you see it depends on when things went wrong:

| What happened | What you catch |
| --- | --- |
| A setting the call needs is `null` on both the client and the call | `AuthException` directly, before any request is sent |
| The authorization server never redirected to your redirect URI, or the state did not match | `Gadget\Http\Exception\ResponseException` with the `AuthException` as `getPrevious()` |
| The token endpoint answered with anything but `200` | `ResponseException` with an `AuthException` as `getPrevious()` |
| The token body was not JSON | `ResponseException` with a `JsonException` as `getPrevious()` |
| The wrapped PSR-18 client threw | `Gadget\Http\Exception\ClientException` |

The `ResponseException` message names the method, URI, and status code, for example `Error handling response: POST https://login.example.com/oauth2/token => 400`.

## Symfony bundle

Add the bundle to `config/bundles.php` along with the gadget-http and gadget-cache bundles it builds on:

```php
return [
    // ...
    Gadget\Cache\GadgetCacheBundle::class => ['all' => true],
    Gadget\Http\GadgetHttpBundle::class => ['all' => true],
    Gadget\Oauth\GadgetOauthBundle::class => ['all' => true],
];
```

The bundle registers the classes under `Gadget\Oauth\` as autowired, autoconfigured services. The one you will inject is `AuthClient`. Its constructor arguments are plain strings, so give it your settings in your own `services.yaml`:

```yaml
services:
    Gadget\Oauth\Client\AuthClient:
        arguments:
            $authUri: '%env(OAUTH_AUTH_URI)%'
            $tokenUri: '%env(OAUTH_TOKEN_URI)%'
            $clientId: '%env(OAUTH_CLIENT_ID)%'
            $clientSecret: '%env(OAUTH_CLIENT_SECRET)%'
            $scope: '%env(OAUTH_SCOPE)%'
            $redirectUri: '%env(OAUTH_REDIRECT_URI)%'
```

The gadget-http README explains how to point the PSR-18 and PSR-17 interfaces at Guzzle, which the `Client` behind `AuthClient` needs. Remember the two Guzzle options from Building the client when you register it.

## Testing

`composer test` runs the suite. The handler and client tests go through a real gadget-http `Client` over Guzzle with a `MockHandler` transport, with redirects and HTTP errors left to this library, so they check the exact requests, the redirect following, the cookies carried between hops, and how errors are wrapped. The extension test compiles a real container on top of the gadget-http extension. [TESTPLAN.md](TESTPLAN.md) has the manual walkthrough.

## License

MIT. See [LICENSE](LICENSE).
