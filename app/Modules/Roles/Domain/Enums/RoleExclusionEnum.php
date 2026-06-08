<?php

namespace App\Modules\Roles\Domain\Enums;

enum RoleExclusionEnum: string
{
    case TEACHER = 'teacher';
    case STUDENT = 'student';
    case TUTOR = 'tutor';

    /**
     * Return slugs that cannot coexist with this role for the same user in the same school.
     *
     * @return array<string>
     */
    public function incompatibleWith(): array
    {
        return match ($this) {
            self::TEACHER => ['student', 'tutor'],
            self::STUDENT => ['teacher', 'tutor'],
            self::TUTOR   => ['teacher', 'student'],
        };
    }

    /**
     * Return the list of role slugs that are incompatible with the given slug.
     * Returns an empty array when the slug has no mutual exclusions.
     *
     * @return array<string>
     */
    public static function getIncompatible(string $slug): array
    {
        foreach (self::cases() as $case) {
            if ($case->value === $slug) {
                return $case->incompatibleWith();
            }
        }

        return [];
    }
}
