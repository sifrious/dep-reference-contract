<?php
declare(strict_types=1);
namespace Sifrious\ReferenceContract;

use InvalidArgumentException;

final readonly class ReferenceSnapshot
{
    /** @param array<string,mixed> $attributes */
    public function __construct(public CrossPackageReference $reference, public string $label, public array $attributes = [])
    {
        if (trim($label) === '') { throw new InvalidArgumentException('Reference snapshot labels must be non-empty.'); }
        json_encode($attributes, JSON_THROW_ON_ERROR);
    }
}
