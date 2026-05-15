<?php

namespace App\Support;

use App\Models\Assignment;

class AssignmentResolver
{
    private static ?string $fieldLabel = null;

    public static function fieldLabel(): string
    {
        if (self::$fieldLabel !== null) {
            return self::$fieldLabel;
        }

        $label = Assignment::query()
            ->whereRaw('LOWER(code) = ?', ['field'])
            ->value('label');

        self::$fieldLabel = trim((string) ($label ?: 'Field'));
        return self::$fieldLabel;
    }

    public static function isField(?string $assignmentType): bool
    {
        return strcasecmp(trim((string) $assignmentType), self::fieldLabel()) === 0;
    }
}

