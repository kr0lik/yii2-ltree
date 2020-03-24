<?php
declare(strict_types=1);

namespace kr0lik\ltree;

class Ql
{
    public const SEPORATOR = '.';
    public const OCTANT_LENGTH = 4;

    /**
     * @var string
     */
    public static $ltreeSchema = 'public';

    public static function pathField(string $table, string $pathField): string
    {
        return sprintf(
            '"%s".%s',
            $table,
            $pathField
        );
    }

    public static function nlevel(string $table, string $pathField): string
    {
        return sprintf(
            '%s.nlevel(%s)',
            self::$ltreeSchema,
            self::pathField($table, $pathField)
        );
    }

    public static function nlevelDown(string $table, string $pathField, int $level): string
    {
        return sprintf(
            '%s - %d',
            self::nlevel($table, $pathField),
            $level
        );
    }

    public static function nlevelUp(string $table, string $pathField, int $level): string
    {
        return sprintf(
            '%s + %d',
            self::nlevel($table, $pathField),
            $level
        );
    }

    public static function operator(string $op): string
    {
        return sprintf(
            'operator(%s.%s)',
            self::$ltreeSchema,
            $op
        );
    }

    public static function nearPath(string $table, string $pathField): Expression
    {
        return sprintf(
            "regexp_replace(%s::text, '([0-9]*)$', '', 'g')||'*'",
            self::pathField($table, $pathField)
        );
    }

    public static function prePath(string $table, string $pathField, int $level): string
    {
        return sprintf(
            '%s.subpath(%s, 0, %d-1)',
            self::$ltreeSchema,
            self::pathField($table, $pathField),
            $level
        );
    }

    public static function postPath(string $table, string $pathField, int $level): string
    {
        return sprintf(
            'CASE WHEN %2$s > %4$d THEN %1$s.subpath(%3$s, %4$d) ELSE \'\' END',
            self::$ltreeSchema,
            self::nlevel($table, $pathField),
            self::pathField($table, $pathField),
            $level
        );
    }

    public static function octant(string $table, string $pathField, int $level): string
    {
        return sprintf(
            'CASE WHEN %2$s > %4$d-1 THEN %1$s.subpath(%3$s, %4$d-1, 1) ELSE \'\' END',
            self::$ltreeSchema,
            self::nlevel($table, $pathField),
            self::pathField($table, $pathField),
            $level
        );
    }

    public static function octantDown(string $table, string $pathField, int $level, int $num = 1): string
    {
        return sprintf('btrim(
                concat(
                    %4$s,
                    \'%1$s\', 
                    lpad(
                        (ltrim(%5$s::text, \'0\')::int - %7$d)::text, 
                        %2$d, 
                        \'0\'
                    ), 
                    \'%1$s\',
                    %6$s
                ), 
            \'%1$s\')::%3$s.ltree',
            self::SEPORATOR,
            self::OCTANT_LENGTH,
            self::$ltreeSchema,
            self::prePath($table, $pathField, $level),
            self::octant($table, $pathField, $level),
            self::postPath($table, $pathField, $level),
            $num
        );
    }

    public static function octantUp(string $table, string $pathField, int $level, int $num = 1): string
    {
        return sprintf('btrim(
                concat(
                    %4$s, 
                    \'%1$s\', 
                    lpad(
                        (ltrim(%5$s::text, \'0\')::int + %7$d)::text, 
                        %2$d, 
                        \'0\'
                    ), 
                    \'%1$s\', 
                    %6$s
                ),
             \'%1$s\')::%3$s.ltree',
            self::SEPORATOR,
            self::OCTANT_LENGTH,
            self::$ltreeSchema,
            self::prePath($table, $pathField, $level),
            self::octant($table, $pathField, $level),
            self::postPath($table, $pathField, $level),
            $num
        );
    }

    public static function changePrePath(string $table, string $pathField, string $prePath, int $level): string
    {
        return sprintf(
            'btrim(concat(\'%4$s\', \'%1$s\', %3$s), \'%1$s\')::%2$s.ltree',
            self::SEPORATOR,
            self::$ltreeSchema,
            self::postPath($table, $pathField, $level),
            $prePath,
        );
    }

    public static function queryMoveOctantsDown(string $table, string $pathField, string $path): string
    {
        return sprintf('
            UPDATE %1$s SET %2$s = %3$s
            FROM (
                SELECT %2$s FROM %1$s 
                WHERE %2$s %4$s \'%5$s\' AND %2$s %6$s \'%7$s\'
                ORDER BY %2$s ASC
            ) AS sub
            WHERE %8$s %9$s %10$s
            ',
            $table,
            $pathField,
            self::octantDown($table, $pathField, PathHelper::getLevel($path)),
            self::operator('~'),
            PathHelper::generateNearLquery($path),
            self::operator('>='),
            $path,
            self::pathField($table, $pathField),
            self::operator('='),
            self::pathField('sub', $pathField)
        );

        return Yii::$app->db->createCommand($sql)->execute();
    }

    public static function queryMoveOctantsUp(string $table, string $pathField, string $path): string
    {
        return sprintf('
            UPDATE %1$s SET %2$s = %3$s
            FROM (
                SELECT %2$s FROM %1$s 
                WHERE %2$s %4$s \'%5$s\' AND %2$s %6$s \'%7$s\'
                ORDER BY %2$s DESC
            ) AS sub
            WHERE %8$s %9$s %10$s
            ',
            $table,
            $pathField,
            self::octantUp($table, $pathField, PathHelper::getLevel($path)),
            self::operator('~'),
            PathHelper::generateNearLquery($path),
            self::operator('>='),
            $path,
            self::pathField($table, $pathField),
            self::operator('='),
            self::pathField('sub', $pathField)
        );
    }

    public static function queryChangePrePath(string $table, string $pathField, string $targetPath, string $prePath): string
    {
        return sprintf('
            UPDATE %1$s SET %2$s = %3$s
            FROM (
                SELECT %2$s FROM %1$s 
                WHERE %2$s %4$s \'%5$s\' AND %2$s %6$s \'%7$s\'
                ORDER BY %2$s ASC
            ) AS sub
            WHERE %8$s %9$s %10$s
            ',
            $table,
            $pathField,
            self::changePrePath($table, $pathField, $prePath, PathHelper::getlevel($targetPath)-1),
            self::operator('~'),
            PathHelper::generatePostLquery($targetPath),
            self::operator('>='),
            $targetPath,
            self::pathField($table, $pathField),
            self::operator('='),
            self::pathField('sub', $pathField)
        );
    }
}