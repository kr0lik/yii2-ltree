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
     * Name of schema where ltree extension installed
     *
     * @var string
     */
    private $schema = 'public';

    /**
     * Sort by path
     *
     * @param int $sort
     * @return ActiveQuery
     */
    public function sorted($sort = SORT_ASC)
    {
        $tb = $this->getPrimaryTableName();
        return $this->orderBy(["$tb.{$this->pathName}" => $sort]);
    }

    /**
     * Get all without root
     *
     * @return ActiveQuery
     */
    public function notRoot()
    {
        $tb = $this->getPrimaryTableName();
        return $this->andWhere(['>', "{$this->schema}.nlevel($tb.{$this->pathName})", 1]);
    }

    /**
     * Get root only
     *
     * @return ActiveQuery
     */
    public function root()
    {
        $tb = $this->getPrimaryTableName();
        return $this->andWhere(["{$this->schema}.nlevel($tb.{$this->pathName})" => 1]);
    }

    /**
     * Get models by $path
     * If $recursive == true then get all models where path field value starts from $path(with all childrens)
     *
     * @param string $path
     * @param boolean $recursive
     * @return ActiveQuery
     */
    public function byPath($path, $recursive = true)
    {
        $tb = $this->getPrimaryTableName();
        return $this->andWhere([$recursive ? "operator({$this->schema}.<@)" : "operator({$this->schema}.=)", "$tb.{$this->pathName}", $path]);
    }
    
    /**
     * Not equal path
     * @param string $path
     *
     * @return ActiveQuery
     */
    public function not($path)
    {
        $tb = $this->getPrimaryTableName();
        return $this->andWhere(["operator({$this->schema}.<>)", "$tb.{$this->pathName}", $path]);
    }

    /**
     * Join parents
     * $level = 0 - get all parents
     * $level = n - get n levels of parents start from $this level
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
                ->onCondition(["operator({$this->schema}.@>)", new Expression("\"parents\".{$this->pathName}"), new Expression("$tb.{$this->pathName}")])
                ->andOnCondition(["operator({$this->schema}.<>)", new Expression("\"parents\".{$this->pathName}"), new Expression("$tb.{$this->pathName}")])
                ->where(false);

            $query->link = [];

            if ($level) $query->andOnCondition([">=", new Expression("{$this->schema}.nlevel(\"parents\".{$this->pathName})"),  new Expression("{$this->schema}.nlevel($tb.{$this->pathName}) - :level")], ['level' => $level]);
        }], false, $joinType);
    }

    /**
     * Join childrens
     * $level = 0 - get all childrens
     * $level = n - get n levels of childrens start from $this level
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
                ->onCondition(["operator({$this->schema}.<@)", new Expression("\"childrens\".{$this->pathName}"), new Expression("$tb.{$this->pathName}")])
                ->andOnCondition(["operator({$this->schema}.<>)", new Expression("\"childrens\".{$this->pathName}"), new Expression("$tb.{$this->pathName}")])
                ->where(false);

            $query->link = [];

            if ($level) $query->andOnCondition([">=", new Expression("{$this->schema}.nlevel(\"childrens\".{$this->pathName})"),  new Expression("{$this->schema}.nlevel($tb.{$this->pathName}) + :level")], ['level' => $level]);
        }], false, $joinType);
    }
    
    /**
     * Set start level
     *
     * @param int $level
     * @return ActiveQuery
     */
    public function startLevel(int $level)
    {
        return $this->andWhere([">=", new Expression("{$this->schema}.nlevel({$this->getPrimaryTableName()}.{$this->pathName})"),  $level]);
    }

    /**
     * Set end level
     *
     * @param int $level
     * @return mixed
     */
    public function endLevel(int $level)
    {
        return $this->andWhere(["<=", new Expression("{$this->schema}.nlevel({$this->getPrimaryTableName()}.{$this->pathName})"),  $level]);
    }
}
