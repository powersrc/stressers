<?php

namespace App\Support;

final class Path
{
    private static $myBaseNameMap = [];

    public static function getBaseNameOf(string $path): string
    {
        if (isset(self::$myBaseNameMap[$path])) {
            return self::$myBaseNameMap[$path];
        }

        $basename = basename($path);
        self::$myBaseNameMap[$path] = $basename;
        return $basename;
    }
}
