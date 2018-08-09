<?php

namespace App\Support;

final class Arr
{
    public static function head($subject, $default = null)
    {
        $array = self::wrap($subject);
        if (empty($array)) {
            return $default;
        }

        return $array[0];
    }

    public static function map(array $subject, callable $callback): array
    {
        $array = self::wrap($subject);
        if (empty($array)) {
            return [];
        }

        return array_map($callback, $array);
    }

    public static function wrap($subject): array
    {
        if (!isset($subject)) {
            return [];
        }

        if (is_array($subject)) {
            return $subject;
        }

        return [$subject];
    }
}
