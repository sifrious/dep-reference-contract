<?php
declare(strict_types=1);
namespace Sifrious\ReferenceContract;

enum ReferenceResolutionStatus: string
{
    case Available = 'available';
    case Unavailable = 'unavailable';
    case Tombstoned = 'tombstoned';
    case Superseded = 'superseded';
    case Unauthorized = 'unauthorized';
}
