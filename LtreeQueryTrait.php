<?php
namespace kr0lik\ltree;

use yii\db\Expression;

trait LtreeQueryTrait
{
    /**
     * Name filed of ltree path
     *
     * @var string
     */
    private $pathName = 'path';

    /**
     * Sort by path
     * 
     * @param int $sort
     * @return ActiveQuery
     */
    public function sorted($sort = SORT_ASC)
    {
        $tb = $this->getPrimaryTableName();
        return $this->orderBy(["$tb.path" => $sort]);
    }

    /**
     * Get all without root
     *
     * @return ActiveQuery
     */
    public function notRoot()
    {
        $tb = $this->getPrimaryTableName();
        return $this->andWhere(['>', "nlevel($tb.path)", 1]);
    }

    /**
     * Get root only
     *
     * @return ActiveQuery
     */
    public function root()
    {
        $tb = $this->getPrimaryTableName();
        return $this->andWhere(["nlevel($tb.path)" => 1]);
    }

    /**
     * Get models by $path
     *
     * @param string $path
     * @param boolean $recursive
     * If $recursive == true then get all models where path field value starts from $path(with all childrens)
     * 
     * @return ActiveQuery
     */
    public function byPath($path, $recursive = true)
    {
        $tb = $this->getPrimaryTableName();
        return $this->andWhere([$recursive ? '<@' : '=', "$tb.path", $path]);
    }

    /**
     * Join parents
     *
     * @param int $level
     * @param string $joinType
     * @return ActiveQuery
     */
    public function joinParents(int $level = 0, string $joinType = 'LEFT JOIN')
    {
        $tb = $this->getPrimaryTableName();
        return $this->joinWith(['parents' => function ($query) use ($level, $tb) {
            $query->from(['parents' => $tb])
                ->onCondition(['@>', new Expression("\"parents\".{$this->pathName}"), new Expression("$tb.{$this->pathName}")])
                ->andOnCondition(['<>', new Expression("\"parents\".{$this->pathName}"), new Expression("$tb.{$this->pathName}")])
                ->where(false);

            $query->link = [];

            if ($level) $query->andOnCondition(['>=', new Expression("nlevel(\"parents\".{$this->pathName})"),  new Expression("nlevel($tb.{$this->pathName}) - :level")], ['level' => $level]);
        }], false, $joinType);
    }

    /**
     * Join childrens
     *
     * @param int $level
     * @param string $joinType
     * @return ActiveQuery
     */
    public function joinChildrens(int $level = 0, string $joinType = 'LEFT JOIN')
    {
        $tb = $this->getPrimaryTableName();
        return $this->joinWith(['childrens' => function ($query) use ($level, $tb) {
            $query->from(['childrens' => $tb])
                ->onCondition(['<@', new Expression("\"childrens\".{$this->pathName}"), new Expression("$tb.{$this->pathName}")])
                ->andOnCondition(['<>', new Expression("\"childrens\".{$this->pathName}"), new Expression("$tb.{$this->pathName}")])
                ->where(false);

            $query->link = [];

            if ($level) $query->andOnCondition(['>=', new Expression("nlevel(\"childrens\".{$this->pathName})"),  new Expression("nlevel($tb.{$this->pathName}) + :level")], ['level' => $level]);
        }], false, $joinType);
    }
}
