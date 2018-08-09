<?php

namespace App\Support;

final class Type
{
    public static function getClassOf($subject): string
    {
        return get_class($subject);
    }

    public static function getClassNameOf($subject): string
    {
        return Path::getBaseNameOf(self::getClassOf($subject));
    }
}
