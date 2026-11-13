<?php

namespace App\Tax;

final readonly class VatIdResult
{
    public function __construct(
        public string $vatId,
        public string $country,
        public bool $valid,
        public bool $serviceAvailable = true,
    ) {}

    public static function invalid(string $vatId, string $country = ''): self
    {
        return new self($vatId, $country, false);
    }

    public static function unavailable(string $vatId, string $country): self
    {
        return new self($vatId, $country, false, false);
    }
}
