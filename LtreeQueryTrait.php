<?php
namespace kr0lik\ltree;

use yii\db\Expression;

trait LtreeQueryTrait
{
    private $pathName = 'path';

    public function sorted($sort = SORT_ASC)
    {
        $tb = $this->getPrimaryTableName();
        return $this->orderBy(["$tb.path" => $sort]);
    }

    public function notRoot()
    {
        $tb = $this->getPrimaryTableName();
        return $this->andWhere(['>', "nlevel($tb.path)", 1]);
    }

    public function root()
    {
        $tb = $this->getPrimaryTableName();
        return $this->andWhere(["nlevel($tb.path)" => 1]);
    }

    public function byPath($path, $recursive = true)
    {
        $tb = $this->getPrimaryTableName();
        return $this->andWhere([$recursive ? '<@' : '=', "$tb.path", $path]);
    }

    public function joinParents(int $level = 0, string $joinType = 'LEFT JOIN')
    {
        return $this->joinWith(['parents' => function ($query) use ($level) {
            $tb = $this->getPrimaryTableName();

            $query->from(['parents' => $tb])
                ->onCondition(['@>', new Expression("\"parents\".{$this->pathName}"), new Expression("$tb.{$this->pathName}")])
                ->andOnCondition(['<>', new Expression("\"parents\".{$this->pathName}"), new Expression("$tb.{$this->pathName}")])
                ->where(false);

            $query->link = [];

            if ($level) $query->andOnCondition(['>=', new Expression("nlevel(\"parents\".{$this->pathName})"),  new Expression("nlevel($tb.{$this->pathName}) - :level")], ['level' => $level]);
        }], false, $joinType);
    }

    public function joinChildrens(int $level = 0, string $joinType = 'LEFT JOIN')
    {
        return $this->joinWith(['childrens' => function ($query) use ($level) {
            $tb = $this->getPrimaryTableName();

            $query->from(['childrens' => $tb])
                ->onCondition(['<@', new Expression("\"childrens\".{$this->pathName}"), new Expression("$tb.{$this->pathName}")])
                ->andOnCondition(['<>', new Expression("\"childrens\".{$this->pathName}"), new Expression("$tb.{$this->pathName}")])
                ->where(false);

            $query->link = [];

            if ($level) $query->andOnCondition(['>=', new Expression("nlevel(\"childrens\".{$this->pathName})"),  new Expression("nlevel($tb.{$this->pathName}) + :level")], ['level' => $level]);
        }], false, $joinType);
    }
}
