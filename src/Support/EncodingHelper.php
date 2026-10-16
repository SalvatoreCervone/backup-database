<?php

namespace SalvatoreCervone\BackupDatabase\Support;

class EncodingHelper
{
    /**
     * Ensure a string or array of strings is valid UTF-8.
     * Converts Windows console encodings (e.g., CP850, Windows-1252, ISO-8859-1) to UTF-8
     * and sanitizes any invalid byte sequences to prevent JSON encoding failures.
     */
    public static function toUtf8(mixed $value): mixed
    {
        if (is_array($value)) {
            $cleaned = [];
            foreach ($value as $k => $v) {
                $cleanKey = is_string($k) ? self::toUtf8($k) : $k;
                $cleaned[$cleanKey] = self::toUtf8($v);
            }

            return $cleaned;
        }

        if (! is_string($value)) {
            return $value;
        }

        if ($value === '') {
            return '';
        }

        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        // Try common Windows CLI encodings: CP850 (Western European OEM), Windows-1252, ISO-8859-1
        $converted = @mb_convert_encoding($value, 'UTF-8', ['CP850', 'Windows-1252', 'ISO-8859-1']);
        if (mb_check_encoding($converted, 'UTF-8')) {
            return $converted;
        }

        // Fallback: discard or substitute invalid UTF-8 bytes
        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }
}
