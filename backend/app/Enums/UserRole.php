<?php

namespace App\Enums;

enum UserRole: string
{
    case User       = 'user';
    case Admin      = 'admin';
    case SuperAdmin = 'super_admin';

    public function isAdmin(): bool
    {
        return in_array($this, [self::Admin, self::SuperAdmin], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::User       => 'User',
            self::Admin      => 'Admin',
            self::SuperAdmin => 'Super Admin',
        };
    }
}
