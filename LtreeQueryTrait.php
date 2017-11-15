<?php
namespace app\components\Ltree;

use yii\db\Expression;

trait LtreeQueryTrait
{
    private $pathName = 'path';

    public function sorted($sort = SORT_ASC)
    {
        $tb = ($this->modelClass)::tableName();
        return $this->orderBy(["$tb.path" => $sort]);
    }

    public function notRoot()
    {
        $tb = ($this->modelClass)::tableName();
        return $this->andWhere(['>', "nlevel($tb.path)", 1]);
    }

    public function root()
    {
        $tb = ($this->modelClass)::tableName();
        return $this->andWhere(["nlevel($tb.path)" => 1]);
    }

    public function byPath($path, $recursive = true)
    {
        $tb = ($this->modelClass)::tableName();
        return $this->andWhere([$recursive ? '<@' : '=', "$tb.path", $path]);
    }

    public function joinParents(int $level = 0, string $joinType = 'LEFT JOIN')
    {
        return $this->joinWith(['parents' => function ($query) use ($level) {
            $table = ($this->modelClass)::tableName();

            $query->from(['parents' => $table])
                ->onCondition(['@>', new Expression("\"parents\".{$this->pathName}"), new Expression("$table.{$this->pathName}")])
                ->andOnCondition(['<>', new Expression("\"parents\".{$this->pathName}"), new Expression("$table.{$this->pathName}")])
                ->where(false);

            $query->link = [];

            if ($level) $query->andOnCondition(['>=', new Expression("nlevel(\"parents\".{$this->pathName})"),  new Expression("nlevel($table.{$this->pathName}) - :level")], ['level' => $level]);
        }], false, $joinType);
    }

    public function joinChildrens(int $level = 0, string $joinType = 'LEFT JOIN')
    {
        return $this->joinWith(['childrens' => function ($query) use ($level) {
            $table = ($this->modelClass)::tableName();

            $query->from(['childrens' => $table])
                ->onCondition(['<@', new Expression("\"childrens\".{$this->pathName}"), new Expression("$table.{$this->pathName}")])
                ->andOnCondition(['<>', new Expression("\"childrens\".{$this->pathName}"), new Expression("$table.{$this->pathName}")])
                ->where(false);

            $query->link = [];

            if ($level) $query->andOnCondition(['>=', new Expression("nlevel(\"childrens\".{$this->pathName})"),  new Expression("nlevel($table.{$this->pathName}) + :level")], ['level' => $level]);
        }], false, $joinType);
    }
}