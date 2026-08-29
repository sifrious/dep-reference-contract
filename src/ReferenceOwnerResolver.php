<?php
declare(strict_types=1);
namespace Sifrious\ReferenceContract;

interface ReferenceOwnerResolver
{
    public function owner(): string;
    public function resolveBatch(ReferenceBatch $batch, ReferenceAccess $access): ReferenceResolutionSet;
}
