<?php

declare(strict_types=1);

namespace Gadget\Oauth\Model;

class PKCE
{
    /** @var string */
    private const VERIFIER_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~';

    public readonly string $mode;
    public readonly string $verifier;
    public readonly string $challenge;


    public function __construct()
    {
        $size = random_int(43, 128);
        /** @var int[]|false $bytes */
        $bytes = unpack("C{$size}", random_bytes($size));

        $this->mode = 'S256';
        $this->verifier = join(
            array_map(
                fn (int $v): string => substr(
                    self::VERIFIER_CHARS,
                    $v % strlen(self::VERIFIER_CHARS),
                    1
                ),
                is_array($bytes)
                    ? $bytes
                    : throw new \Random\RandomError("Unable to generate random number to use in PKCE verifier")
            )
        );
        $this->challenge = base64_encode(hash('SHA256', $this->verifier, true));
    }
}
