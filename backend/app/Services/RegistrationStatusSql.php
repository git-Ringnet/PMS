<?php

namespace App\Services;

final class RegistrationStatusSql
{
    /**
     * Rewrite joins against the registration status catalogue from its legacy
     * primary key to the stable business status code.
     *
     * The procedure cutover is deliberately strict. A routine that still has
     * a legacy comparison after rewriting would be recreated with mixed
     * semantics, so an unsupported comparison is reported instead of being
     * silently left in place.
     */
    public static function useBusinessCodes(string $sql): string
    {
        $aliases = self::registrationStatusAliases($sql);

        foreach ($aliases as $alias) {
            $sql = self::replaceLegacyComparison($sql, $alias, false);
            $sql = self::replaceLegacyComparison($sql, $alias, true);
        }

        $unsupported = self::findLegacyBookingStatusReferences($sql, $aliases);
        if ($unsupported !== []) {
            throw new \RuntimeException(sprintf(
                'Unsupported legacy registration status reference in procedure SQL: %s',
                implode('; ', $unsupported),
            ));
        }

        return $sql;
    }

    /**
     * Return legacy status comparisons that the converter cannot safely
     * rewrite. Passing aliases avoids treating unrelated tables' id columns as
     * registration status references.
     *
     * @return list<string>
     */
    public static function findLegacyBookingStatusReferences(string $sql, ?array $aliases = null): array
    {
        $aliases ??= self::registrationStatusAliases($sql);
        $references = [];

        foreach ($aliases as $alias) {
            $registrationId = self::qualifiedColumn($alias, 'id');
            $bookingStatusId = self::qualifiedColumn(null, 'registration_status_id');
            $operator = '(?:<=>|<>|!=|<=|>=|=|<|>)';
            $patterns = [
                '/\(*\s*'.$registrationId.'\s*\)*\s*'.$operator.'\s*'.$bookingStatusId.'/i',
                '/'.$bookingStatusId.'\s*'.$operator.'\s*\(*\s*'.$registrationId.'\s*\)*/i',
            ];

            foreach ($patterns as $pattern) {
                preg_match_all($pattern, $sql, $matches);
                foreach ($matches[0] ?? [] as $match) {
                    $references[] = trim((string) preg_replace('/\s+/', ' ', $match));
                }
            }
        }

        return array_values(array_unique($references));
    }

    /**
     * @return list<string>
     */
    private static function registrationStatusAliases(string $sql): array
    {
        $identifier = self::identifierPattern();
        $aliases = [];
        $patterns = [
            '/\bJOIN\s+(?:'.$identifier.'\s*\.\s*)?`?registration_statuses`?'
                .'(?:(?:\s+AS\s+(?<as>'.$identifier.'))|(?:\s+(?<bare>'.$identifier.')(?=\s+ON\b)))?\s+ON\b/i',
            '/\bFROM\s+(?:'.$identifier.'\s*\.\s*)?`?registration_statuses`?'
                .'(?:(?:\s+AS\s+(?<as>'.$identifier.'))|(?:\s+(?<bare>'.$identifier.')(?=\s*(?:,|WHERE\b|GROUP\b|ORDER\b|HAVING\b|LIMIT\b|JOIN\b|LEFT\b|RIGHT\b|INNER\b|OUTER\b))))?/i',
            '/,\s*(?:'.$identifier.'\s*\.\s*)?`?registration_statuses`?'
                .'(?:(?:\s+AS\s+(?<as>'.$identifier.'))|(?:\s+(?<bare>'.$identifier.')(?=\s*(?:,|WHERE\b|GROUP\b|ORDER\b|HAVING\b|LIMIT\b|JOIN\b|LEFT\b|RIGHT\b|INNER\b|OUTER\b))))?/i',
        ];

        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $sql, $matches, PREG_UNMATCHED_AS_NULL);
            foreach (($matches['as'] ?? []) as $index => $asAlias) {
                $alias = $asAlias ?: (($matches['bare'][$index] ?? null) ?: 'registration_statuses');
                $alias = self::unquoteIdentifier((string) $alias);
                $key = strtolower($alias);
                if ($alias !== '' && ! in_array($key, array_map('strtolower', $aliases), true)) {
                    $aliases[] = $alias;
                }
            }
        }

        return $aliases;
    }

    private static function replaceLegacyComparison(string $sql, string $alias, bool $reverse): string
    {
        $registrationId = self::qualifiedColumn($alias, 'id');
        $bookingStatusId = self::qualifiedColumn(null, 'registration_status_id');
        $operator = '(?:<=>|=)';

        if ($reverse) {
            $pattern = '/(?<booking>'.$bookingStatusId.')(?<operator>\s*'.$operator.'\s*)'
                .'(?<opening>\(*\s*)(?<alias>'.$registrationId.')(?<closing>\s*\)*)/i';
        } else {
            $pattern = '/(?<opening>\(*\s*)(?<alias>'.$registrationId.')(?<closing>\s*\)*)'
                .'(?<operator>\s*'.$operator.'\s*)(?<booking>'.$bookingStatusId.')/i';
        }

        return (string) preg_replace_callback($pattern, static function (array $match): string {
            $legacy = $match['alias'];
            $replacement = preg_match('/^.*`id`$/i', $legacy) === 1
                ? preg_replace('/`id`$/i', '`booking_status_id`', $legacy)
                : preg_replace('/id$/i', 'booking_status_id', $legacy);

            if ($replacement === null) {
                return $match[0];
            }

            if (isset($match['booking'])) {
                return $match['opening'].$replacement.$match['closing'].$match['operator'].$match['booking'];
            }

            return $match['booking'].$match['operator'].$match['opening'].$replacement.$match['closing'];
        }, $sql);
    }

    private static function qualifiedColumn(?string $alias, string $column): string
    {
        $qualifiedAlias = $alias === null ? self::identifierPattern() : self::quotedIdentifier($alias);

        return $qualifiedAlias.'\s*\.\s*`?'.preg_quote($column, '/').'`?';
    }

    private static function quotedIdentifier(string $identifier): string
    {
        return '(?:`'.preg_quote($identifier, '/').'`|'.preg_quote($identifier, '/').')';
    }

    private static function identifierPattern(): string
    {
        return '(?:`[^`]+`|[A-Za-z_][A-Za-z0-9_$]*)';
    }

    private static function unquoteIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        if (str_starts_with($identifier, '`') && str_ends_with($identifier, '`')) {
            return str_replace('``', '`', substr($identifier, 1, -1));
        }

        return $identifier;
    }
}
