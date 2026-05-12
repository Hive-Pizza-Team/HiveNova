<?php

namespace HiveNova\Core;

/**
 * Builds before/after rows for admin log detail views from serialized config snapshots.
 * Handles asymmetric keys (PHP 8: missing keys must not trigger notices).
 */
final class AdminLogDetailRows
{
    /**
     * @param array<string, mixed> $conf_before
     * @param array<string, mixed> $conf_after
     * @param array<string, string> $wrapper
     * @param array<string, mixed> $LNG
     * @return list<array{Element: string, old: string, new: string}>
     */
    public static function build(array $conf_before, array $conf_after, array $wrapper, array $LNG): array
    {
        $rows = [];
        foreach ($conf_before as $key => $val) {
            if ($key === 'universe') {
                continue;
            }

            if (isset($LNG['tech'][$key])) {
                $elementLabel = $LNG['tech'][$key];
            } elseif (isset($LNG['se_'.$key])) {
                $elementLabel = $LNG['se_'.$key];
            } elseif (isset($LNG[$key])) {
                $elementLabel = $LNG[$key];
            } elseif (isset($wrapper[$key])) {
                $elementLabel = $wrapper[$key];
            } else {
                $elementLabel = $key;
            }

            $afterVal = array_key_exists($key, $conf_after) ? $conf_after[$key] : null;

            $rows[] = [
                'Element' => $elementLabel,
                'old' => self::formatValueForColumn($key, $val, $LNG),
                'new' => self::formatValueForColumn($key, $afterVal, $LNG),
            ];
        }

        return $rows;
    }

    private static function formatValueForColumn(string $key, mixed $raw, array $LNG): string
    {
        if ($key === 'urlaubs_until') {
            return _date($LNG['php_tdformat'], (int) ($raw ?? 0));
        }
        if ($raw !== null && $raw !== '' && is_numeric($raw)) {
            return pretty_number($raw);
        }
        if ($raw === null) {
            return '';
        }

        return is_scalar($raw) ? (string) $raw : '';
    }
}
