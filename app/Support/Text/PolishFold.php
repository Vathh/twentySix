<?php

namespace App\Support\Text;

use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Porównanie nazw bez wielkości liter i bez polskich ogonków.
 * „suwalki” i „suwałki” trafiają w to samo, w obie strony.
 */
final class PolishFold
{
    /** @var array<string, string> */
    private const FOLDS = [
        'ą' => 'a',
        'ć' => 'c',
        'ę' => 'e',
        'ł' => 'l',
        'ń' => 'n',
        'ó' => 'o',
        'ś' => 's',
        'ź' => 'z',
        'ż' => 'z',
    ];

    public static function term(mixed $raw): ?string
    {
        if (! is_string($raw)) {
            return null;
        }

        $term = trim($raw);
        if ($term === '') {
            return null;
        }

        return mb_substr($term, 0, 100);
    }

    public static function fold(string $value): string
    {
        return strtr(mb_strtolower($value, 'UTF-8'), self::FOLDS);
    }

    public static function likePattern(?string $search): ?string
    {
        $term = self::term($search);
        if ($term === null) {
            return null;
        }

        $folded = self::fold($term);
        if ($folded === '') {
            return null;
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $folded);

        return '%'.$escaped.'%';
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function restrict(Builder $query, string $column, ?string $search): void
    {
        $pattern = self::likePattern($search);
        if ($pattern === null) {
            return;
        }

        $query->whereRaw(self::likeSql($column), self::likeBindings($pattern));
    }

    public static function likeSql(string $column): string
    {
        return self::foldedSql($column).' LIKE ? ESCAPE ?';
    }

    /**
     * @return list<string>
     */
    public static function likeBindings(string $pattern): array
    {
        return [$pattern, '\\'];
    }

    public static function matchSummary(int $total, ?string $term, string $one, string $few, string $many): ?string
    {
        if ($term === null || $total === 0) {
            return null;
        }

        return $total.' '.self::word($total, $one, $few, $many).' dla „'.$term.'”';
    }

    public static function word(int $count, string $one, string $few, string $many): string
    {
        $mod10 = $count % 10;
        $mod100 = $count % 100;
        if ($count === 1) {
            return $one;
        }
        if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
            return $few;
        }

        return $many;
    }

    public static function foldedSql(string $column): string
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $column)) {
            throw new InvalidArgumentException('Niedozwolona kolumna wyszukiwania.');
        }

        $sql = 'LOWER('.$column.')';
        foreach (self::FOLDS as $from => $to) {
            $sql = "REPLACE({$sql}, '{$from}', '{$to}')";
        }

        return $sql;
    }

}
