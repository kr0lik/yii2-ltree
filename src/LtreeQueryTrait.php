<?php
namespace kr0lik\ltree;

use yii\db\Expression;
use yii\db\ActiveQuery;

trait LtreeQueryTrait
{
    /**
     * @var string
     */
    private $ltreePathField = 'lpath';

    /**
     * Sort by path
     */
    public function sorted(int $sort = SORT_ASC): ActiveQuery
    {
        return $this->orderBy([Ql::pathField($this->getPrimaryTableName(), $this->ltreePathField) => $sort]);
    }

    /**
     * Get all without root
     */
    public function notRoot(): ActiveQuery
    {
        return $this->andWhere(['>', new Expression(Ql::nlevel($this->getPrimaryTableName(), $this->ltreePathField)), 1]);
    }

    /**
     * Get root only
     */
    public function root(): ActiveQuery
    {
        return $this->andWhere(['=', new Expression(Ql::nlevel($this->getPrimaryTableName(), $this->ltreePathField)), 1]);
    }

    /**
     * Get models by $path
     * If $recursive == true then get all models where path field value starts from $path(with all childrens)
     */
    public function byPath(string $path, bool $recursive = true): ActiveQuery
    {
        return $this->andWhere([
            new Expression(Ql::operator($recursive ? '<@' : '=')),
            new Expression(Ql::pathField($this->getPrimaryTableName(), $this->ltreePathField)),
            $path
        ]);
    }
    
    /**
     * Not equal path
     */
    public function not(string $path): ActiveQuery
    {
        return $this->andWhere([
            new Expression(Ql::operator('<>')),
            new Expression(Ql::pathField($this->getPrimaryTableName(), $this->ltreePathField)),
            $path
        ]);
    }

    /**
     * Join parents
     * $level = 0 - get all parents
     * $level = n - get n levels of parents start from $this level
     */
    public function joinParents(int $level = 0, string $joinType = 'LEFT JOIN'): ActiveQuery
    {
        return $this->joinWith(['parents' => function ($query) use ($level) {
            $query->from(['parents' => $this->getPrimaryTableName()])
                ->onCondition([
                    new Expression(Ql::operator('@>')),
                    new Expression(Ql::pathField('parents', $this->ltreePathField)),
                    new Expression(Ql::pathField($this->getPrimaryTableName(), $this->ltreePathField)),
                ])
                ->andOnCondition([
                    new Expression(Ql::operator('<>')),
                    new Expression(Ql::pathField('parents', $this->ltreePathField)),
                    new Expression(Ql::pathField($this->getPrimaryTableName(), $this->ltreePathField)),
                ])
                ->where(false);

            $query->link = [];

            if ($level) {
                $query->andOnCondition([
                    '>=',
                    new Expression(Ql::nlevel('parents')),
                    new Expression(Ql::nlevelDown($this->getPrimaryTableName(), $this->ltreePathField, $level)),
                ]);
            }
        }], false, $joinType);
    }

    /**
     * Join childrens
     * $level = 0 - get all childrens
     * $level = n - get n levels of childrens start from $this level
     */
    public function joinChildrens(int $level = 0, string $joinType = 'LEFT JOIN'): ActiveQuery
    {
        return $this->joinWith(['childrens' => function ($query) use ($level) {
            $query->from(['childrens' => $this->getPrimaryTableName()])
                ->onCondition([
                    new Expression(Ql::operator('<@')),
                    new Expression(Ql::pathField('childrens', $this->ltreePathField)),
                    new Expression(Ql::pathField($this->getPrimaryTableName(), $this->ltreePathField)),
                ])
                ->andOnCondition([
                    new Expression(Ql::operator('<>')),
                    new Expression(Ql::pathField('childrens', $this->ltreePathField)),
                    new Expression(Ql::pathField($this->getPrimaryTableName(), $this->ltreePathField)),
                ])
                ->where(false);

            $query->link = [];

            if ($level) {
                $query->andOnCondition([
                    '>=',
                    new Expression(Ql::nlevel('childrens', $this->ltreePathField)),
                    new Expression(Ql::nlevelUp($this->getPrimaryTableName(), $this->ltreePathField, $level)),
                ]);
            }
        }], false, $joinType);
    }

    /**
     * Set start level
     */
    public function startLevel(int $level): ActiveQuery
    {
        return $this->andWhere([
            '>=',
            new Expression(Ql::nlevel($this->getPrimaryTableName(), $this->ltreePathField)),
            $level
        ]);
    }

    /**
     * Set end level
     */
    public function endLevel(int $level): ActiveQuery
    {
        return $this->andWhere([
            '<=',
            new Expression(Ql::nlevel($this->getPrimaryTableName(), $this->ltreePathField)),
            $level
        ]);
    }

    /**
     * Set level
     */
    public function level(int $level): ActiveQuery
    {
        return $this->andWhere([
            '=',
            new Expression(Ql::nlevel($this->getPrimaryTableName(), $this->ltreePathField)),
            $level
        ]);
    }

    /**
     * Get all as tree
     *
     * @return array<int, mixed>
     */
    public function tree(): array
    {
        /** @var LtreeActiveRecordTrait[] $categories */
        $categories = $this->all();

        $tmpTree = [];
        foreach ($categories as $category) {
            if (is_array($category)) {
                $path = $category[$this->ltreePathField];
                $level = PathHelper::getlevel($path);
            } else {
                $path = $category->getLPath();
                $level = $category->getLevel();
            }

            $tmpTree[$level][$path] = $category;
            ksort($tmpTree);
        }
        unset($categories);

        $tree = [];
        for ($level = count($tmpTree); $level >= 0; $level--) {
            if (array_key_exists($level, $tmpTree)) {
                $levelData = $tmpTree[$level];
                ksort($levelData);
                foreach ($levelData as $path => $data) {
                    if ($level > min(array_keys($tmpTree))) {
                        $levelDown = $level-1;
                        if (array_key_exists($level-1, $tmpTree)) {
                            foreach ($tmpTree[$levelDown] as $parentPath => $parentData) {
                                if ($path > $parentPath && strpos($path, $parentPath) !== false) {
                                    if (is_array($tmpTree[$levelDown][$parentPath])) {
                                        $tmpTree[$levelDown][$parentPath]['children'][] = $data;
                                    } else {
                                        $tmpTree[$levelDown][$parentPath]->children[] = $data;
                                    }
                                }
                            }
                        }
                    } else {
                        $tree[] = $data;
                    }
                }
            }
        }

        return $tree;
    }
}
