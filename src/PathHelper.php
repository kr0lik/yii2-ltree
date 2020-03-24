<?php
declare(strict_types=1);

namespace kr0lik\ltree;

use Yii;
use yii\db\Expression;

class PathHelper
{
    public static function getlevel(string $path): int
    {
        return substr_count($path, Ql::SEPORATOR)+1;
    }

    public static function generateParentPath(string $path): string
    {
        $octants = explode(Ql::SEPORATOR, $path);
        unset($octants[array_key_last($octants)]);

        return join(Ql::SEPORATOR, $octants);
    }

    public static function generateChildrenPath(string $path): string
    {
        return $path . Ql::SEPORATOR . self::generateOctant(1);
    }

    public static function generatePreviousPath(string $path): string
    {
        $octants = explode(Ql::SEPORATOR, $path);
        $lastOctant = $octants[array_key_last($octants)];
        $num = ltrim($lastOctant, '0') - 1;
        $num = $num < 1 ? 1 : $num;
        $lastOctant = self::generateOctant($num);
        $octants[array_key_last($octants)] = $lastOctant;

        return join('.', $octants);
    }

    public static function generateNextPath(string $path): string
    {
        $octants = explode(Ql::SEPORATOR, $path);
        $lastOctant = $octants[array_key_last($octants)];
        $lastOctant = self::generateOctant(ltrim($lastOctant, '0') + 1);
        $octants[array_key_last($octants)] = $lastOctant;

        return join('.', $octants);
    }

    public static function generateNearLquery(string $path): string
    {
        $octants = explode(Ql::SEPORATOR, $path);
        $octants[array_key_last($octants)] = '*';

        return join(Ql::SEPORATOR, $octants);
    }

    public static function generatePostLquery(string $path): string
    {
        return $path . Ql::SEPORATOR . '*';
    }

    public static function generateOctant(int $num): string
    {
        return sprintf("%0".Ql::OCTANT_LENGTH."d", $num);
    }


    public static function moveOctantsDown(string $table, string $pathField, string $path): int
    {
        $sql = new Expression(Ql::queryMoveOctantsDown($table, $pathField, $path));

        return Yii::$app->db->createCommand($sql)->execute();
    }

    public static function moveOctantsUp(string $table, string $pathField, string $path): int
    {
        $sql = new Expression(Ql::queryMoveOctantsUp($table, $pathField, $path));

        return Yii::$app->db->createCommand($sql)->execute();
    }

    public static function changePrePath(string $table, string $pathField, string $targetPath, string $prePath): int
    {
        $sql = new Expression(Ql::queryChangePrePath($table, $pathField, $targetPath, $prePath));

        return Yii::$app->db->createCommand($sql)->execute();
    }
}