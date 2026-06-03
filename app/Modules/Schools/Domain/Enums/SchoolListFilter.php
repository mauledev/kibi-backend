<?php

namespace App\Modules\Schools\Domain\Enums;

/**
 * Lifecycle filter accepted by the SchoolRepository when listing schools.
 *
 * `Deactivated` and `All` cover the soft-delete dimension — exposing it as a
 * domain concept keeps the persistence detail (`onlyTrashed` / `withTrashed`)
 * encapsulated inside Infrastructure while letting callers express intent in
 * terms of the domain vocabulary.
 */
enum SchoolListFilter: string
{
    case Active = 'active';
    case Deactivated = 'deactivated';
    case All = 'all';
}
