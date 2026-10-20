# Test Plan

Walk through this list before tagging a release or merging a change that touches `composer.json`, the analyzers, or the test suite. Run every command from the repository root.

## Setup

- [ ] `composer install` finishes without errors and without asking to change the lock file.
- [ ] `composer validate` reports the manifest is valid.
- [ ] `working/` exists after the first run and is not shown in `git status`.

## Automated checks

- [ ] `composer phpcs` reports no errors and no warnings for `src/`, `bundle/`, and `tests/`.
- [ ] `composer phpstan` reports `No errors` at level 10 for `src/`, `bundle/`, and `tests/`.
- [ ] `composer test` passes with no warnings, deprecations, or notices in the output.
- [ ] `composer check` runs all three in order and exits 0.

## Functional coverage

Confirm each area has passing tests under `tests/` that cover the listed behaviors.

- [ ] `PKCE`: the method is `S256`; the verifier is 43 to 128 characters drawn only from `A-Z`, `a-z`, `0-9`, `-`, `.`, `_`, `~`; the challenge is the base64url SHA-256 of the verifier with no padding (43 characters); two instances get different verifiers.
- [ ] `AuthCodeRequest`: a missing state becomes 64 hex characters and a given state is kept; a nonce is generated only for `id_token` responses and a given nonce is kept; `getQueryParams()` maps every field to its OAuth parameter name, with the PKCE challenge and method filled in when a `PKCE` is present and `null` for everything not set.
- [ ] `TokenRequest::getBody()`: an `authorization_code` grant carries `redirect_uri`, `code`, and `code_verifier` (`null` without PKCE); a `refresh_token` grant carries `refresh_token`; any other grant carries only `grant_type`, `client_id`, and `client_secret`.
- [ ] `Token`: the three token strings default to `null` and every given value is kept.
- [ ] `AuthCode::createFromUri()`: reads `code` and `state` from a string or a PSR `UriInterface`; URL-decodes the values; reads `id_token` for an `id_token` response and carries the nonce; keeps the `PKCE` object; rejects a redirect to another host with the expected and actual URI in the message; accepts a redirect URI that extends the expected one; rejects a mismatched state with both values in the message and reports a missing state as `NULL`; rejects a missing code; never finds a code for an unknown response type.
- [ ] `AuthException`: extends `Gadget\Exception\Exception`; the default message is empty; the sprintf-style message form works and `getPrevious()` is kept.
- [ ] `AuthCodeHandler`: sends a `GET` to the authorization URI with exactly the OAuth query parameters; follows intermediate `302` redirects across hosts until the `Location` starts with the redirect URI; cookies set on one hop are sent on the next; a non-redirect response is a `ResponseException` whose message names the request and status and whose cause is an `AuthException`; a redirect chain that ends elsewhere is rejected; a redirect to the redirect URI with the wrong state is rejected with the state mismatch as the cause; only `302` counts as a redirect.
- [ ] `TokenHandler`: posts a form-encoded body to the token URI with the grant fields and the PKCE verifier; builds a `Token` from every JSON field; `created_on` defaults to now; the optional tokens are `null` and `expires_in` is `0` when absent; a non-`200` status is a `ResponseException` caused by an `AuthException`; a malformed body is a `ResponseException` caused by a `JsonException`; a refresh request posts the refresh token.
- [ ] `AuthClient`: `createAuthCode()` uses the configured settings, generates a state when none is given, and lets per-call arguments override `authUri`, `clientId`, `redirectUri`, and `scope`; it throws `AuthException` before sending anything when any of those four is missing; `createToken()` posts the code, redirect URI, and verifier and accepts per-call overrides; it throws `AuthException` before sending when `tokenUri`, `clientId`, `clientSecret`, or `redirectUri` is missing; `refreshToken()` posts the refresh token, works without a redirect URI, and throws before sending when `tokenUri`, `clientId`, or `clientSecret` is missing; the PKCE challenge sent with the authorization request matches the verifier sent with the token request.
- [ ] `GadgetOauthBundle`: extends `Bundle` and exposes `GadgetOauthExtension` under the alias `gadget_oauth`.
- [ ] `GadgetOauthExtension`: registers `AuthClient` as an autowired, autoconfigured service; a container compiled with the gadget-http extension, Guzzle under the PSR interfaces, and a PSR-6 pool builds a working `AuthClient`.

## Consuming the package

- [ ] In a scratch project, `composer require gsu-cetloe/gadget-oauth-php` installs `gsu-cetloe/gadget-http-php`, `gsu-cetloe/gadget-core-php`, `gsu-cetloe/gadget-cache-php`, `gsu-cetloe/symfony-bundle`, and Guzzle.
- [ ] Against a real authorization server, with the wrapped Guzzle client built with `allow_redirects => false` and `http_errors => false` and a session cookie already in the jar, `createAuthCode()` returns an `AuthCode` and `createToken()` returns a `Token` with an access token.
- [ ] Against that server, `refreshToken()` with the refresh token from the previous step returns a new `Token`.
- [ ] Against that server, with no session cookie, `createAuthCode()` throws a `ResponseException` whose cause is an `AuthException`.
- [ ] In a scratch Symfony app with the three bundles enabled and the `AuthClient` arguments bound as the README shows, a service that type-hints `Gadget\Oauth\Client\AuthClient` autowires.

## Known gaps

- The transport in the suite is Guzzle's `MockHandler`. The requests Guzzle would send are checked exactly, but nothing goes over a socket and no real authorization server is involved. The manual steps above cover the real wire.
- `TokenHandler` requires `token_type` and `scope` in the token response. RFC 6749 lets a server leave `scope` out when it matches what was requested; such a response is currently treated as malformed. The suite pins the current behavior.
- Only a `302` is treated as a redirect by `AuthCodeHandler`. A server that answers with `303` or `307` is not followed. The suite pins the current behavior.
- `AuthCode::createFromUri()` accepts any URI that starts with the redirect URI, so `https://app.example.com/callback-other` passes for a redirect URI of `https://app.example.com/callback`. The suite pins the current behavior.
- Tests run on whatever PHP is installed locally. Composer resolves dependencies as if PHP were 8.1.0 and PHPStan analyzes for 8.1, but the behavior checked here is that of the local interpreter.
