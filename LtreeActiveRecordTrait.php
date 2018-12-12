<?php
namespace kr0lik\ltree;

use Yii;
use yii\db\ActiveQuery;

trait LtreeActiveRecordTrait
{
    /**
     * Length one part of path
     *
     * @var int
     */
    private $pathPartLen = 4;

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
     * Get level of $this category starts from 0
     * 0 - is root
     * -1 - cant get level
     *
     * @return int
     */
    public function level(): int
    {
        return $this->{$this->pathName} !== null ? substr_count($this->{$this->pathName}, '.') : -1;
    }

    /**
     * Check if $this is root(0 level)
     *
     * @return bool
     */
    public function isRoot(): bool
    {
        return $this->level() === 0;
    }

    /**
     * Check if $this is first level(After Root)
     *
     * @return bool
     */
    public function isFirstLevel(): bool
    {
        return $this->level() == 1;
    }

    /**
     * Children of $this category
     *
     * $level = 0 - get all childs
     * $level = n - get n level childs
     *
     * @param int $level
     * @return ActiveQuery
     */
    public function getChildrens(int $level = 0): ActiveQuery
    {
        $tb = static::tableName();
        $query = static::find()
            ->where(["operator({$this->schema}.<@)", "$tb.{$this->pathName}", $this->{$this->pathName}])
            ->not($this->{$this->pathName});

        if ($level) {
            $query->endLevel($this->level() + $level + 1);
        }

        return $query;
    }

    /**
     * Parents of $this category
     *
     * $level = 0 - get all parents
     * $level = n - get n level parents
     *
     * @param int $level
     * @return ActiveQuery
     */
    public function getParents(int $level = 0): ActiveQuery
    {
        $tb = static::tableName();
        $query = static::find()
            ->where(["operator({$this->schema}.@>)", "$tb.{$this->pathName}", $this->{$this->pathName}])
            ->not($this->{$this->pathName});

        if ($level) {
            $query->startLevel($this->level() - $level + 1);
        }

        return $query;
    }

    /**
     * Get Next categories of $this in $this level
     *
     * @param int $count
     * @return ActiveQuery
     */
    public function getNext(int $count = -1): ActiveQuery
    {
        $tb = static::tableName();
        return static::find()
            ->where(["operator({$this->schema}.>)", "$tb.{$this->pathName}", $this->{$this->pathName}])
            ->andWhere(["operator({$this->schema}.~)", "$tb.{$this->pathName}", $this->generatePathParent() ? $this->generatePathParent() . '.*' : '*'])
            ->onLevel($this->level() + 1)
            ->limit($count)
            ->sorted();
    }

    /**
     * Get Previous categories of $this in $this level
     *
     * @param int $count
     * @return ActiveQuery
     */
    public function getPrevious(int $count = -1): ActiveQuery
    {
        $tb = static::tableName();
        return static::find()
            ->where(["operator({$this->schema}.<)", "$tb.{$this->pathName}", $this->{$this->pathName}])
            ->andWhere(["operator({$this->schema}.~)", "$tb.{$this->pathName}", $this->generatePathParent() ? $this->generatePathParent() . '.*' : '*'])
            ->onLevel($this->level() + 1)
            ->limit($count)
            ->sorted(SORT_DESC);
    }

    /**
     * Get categories in $this level
     *
     * @param int $count
     * @return ActiveQuery
     */
    public function getNearest(int $count = -1): ActiveQuery
    {
        $tb = static::tableName();
        return static::find()
            ->andWhere(["operator({$this->schema}.~)", "$tb.{$this->pathName}", $this->generatePathParent() ? $this->generatePathParent() . '.*' : '*'])
            ->onLevel($this->level() + 1)
            ->limit($count)
            ->not($this->{$this->pathName})
            ->sorted();
    }

    /**
     * Remove $this from db
     *
     * @return bool
     * @throws \yii\db\Exception
     */
    public function delete(): bool
    {
        $nextCategory = $this->getNext(1)->one();

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $success = parent::delete();

            if ($success && $nextCategory) {
                $success = $this->afterMoveOutOctantDown();
            }

            if ($success) {
                $transaction->commit();
                return true;
            }
        } catch (Exception $e) {
            $transaction->rollBack();
        }

        $transaction->rollBack();
        return false;
    }

    /**
     * Move/insert $category into $this to the end
     *
     * @param self $category
     * @return bool
     * @throws \yii\db\Exception
     */
    public function append(self $category): bool
    {
        if ($this->{$this->pathName}) {
            $targetNextCategoryId = $category->{$this->pathName} ? $category->getNext(1)->select('id')->asArray()->scalar() : null;
            $targetFirstChildrenId = $category->{$this->pathName} ? $category->getChildrens(1)->sorted()->select('id')->limit(1)->asArray()->scalar() : null;

            $hitLastChildren = $this->getChildrens(1)->sorted(SORT_DESC)->limit(1)->one();
            $hitPath = $hitLastChildren ? $hitLastChildren->generatePathNext() : $this->{$this->pathName} . '.' . sprintf("%0{$this->pathPartLen}d", 1);

            $transaction = Yii::$app->db->beginTransaction();
            try {
                $category->{$this->pathName} = $hitPath;
                $success = $category->save();

                if ($success && $targetFirstChildrenId) {
                    $targetFirstChildren = static::findOne($targetFirstChildrenId);
                    $success = $targetFirstChildren->changeStartPartPath($hitPath);
                }

                if ($success && $targetNextCategoryId) {
                    $targetNextCategory = static::findOne($targetNextCategoryId);
                    $success = $targetNextCategory->afterMoveOutOctantDown();
                }

                if ($success) {
                    $transaction->commit();
                    return true;
                }
            } catch (Exception $e) {
                $transaction->rollBack();
            }

            $transaction->rollBack();
        }

        return false;
    }

    /**
     * Move/insert $category into $this to the start
     *
     * @param self $category
     * @return bool
     * @throws \yii\db\Exception
     */
    public function prepend(self $category): bool
    {
        if ($this->{$this->pathName}) {
            $targetNextCategoryId = $category->{$this->pathName} ? $category->getNext(1)->select('id')->asArray()->scalar() : null;
            $targetFirstChildrenId = $category->{$this->pathName} ? $category->getChildrens(1)->sorted()->select('id')->limit(1)->asArray()->scalar() : null;

            $hitFirstChildren = $this->getChildrens(1)->sorted()->one();
            $hitPath = $this->path . '.' . sprintf("%0{$this->partLen}d", 1);

            $transaction = Yii::$app->db->beginTransaction();
            try {
                $hitFirstChildren->beforeMoveInOctantUp();

                $category->{$this->pathName} = $hitPath;
                $success = $category->save();

                if ($success && $targetFirstChildrenId) {
                    $targetFirstChildren = static::findOne($targetFirstChildrenId);
                    $success = $targetFirstChildren->changeStartPartPath($hitPath);
                }

                if ($success && $targetNextCategoryId) {
                    $targetNextCategory = static::findOne($targetNextCategoryId);
                    $success = $targetNextCategory->afterMoveOutOctantDown();
                }

                if ($success) {
                    $transaction->commit();
                    return true;
                }
            } catch (Exception $e) {
                $transaction->rollBack();
            }

            $transaction->rollBack();
        }

        return false;
    }

    /**
     * Move/insert $category after $this
     *
     * @param self $category
     * @return bool
     * @throws \yii\db\Exception
     */
    public function after(self $category): bool
    {
        if ($hitPath = $this->{$this->pathName}) {
            $targetNextCategoryId = $category->{$this->pathName} ? $category->getNext(1)->select('id')->asArray()->scalar() : null;
            $targetFirstChildrenId = $category->{$this->pathName} ? $category->getChildrens(1)->sorted()->select('id')->limit(1)->asArray()->scalar() : null;

            $nextPath = $this->generatePathNext();

            $transaction = Yii::$app->db->beginTransaction();
            try {
                $this->beforeMoveInOctantUp(false);

                $category->{$this->pathName} = $nextPath;
                $success = $category->save();

                if ($success && $targetFirstChildrenId) {
                    $targetFirstChildren = static::findOne($targetFirstChildrenId);
                    $success = $targetFirstChildren->changeStartPartPath($nextPath);
                }

                if ($success && $targetNextCategoryId) {
                    $targetNextCategory = static::findOne($targetNextCategoryId);
                    $success = $targetNextCategory->afterMoveOutOctantDown();
                }

                if ($success) {
                    $transaction->commit();
                    return true;
                }
            } catch (Exception $e) {
                $transaction->rollBack();
            }

            $transaction->rollBack();
        }

        return false;
    }

    /**
     * Move/insert $category before $this
     *
     * @param self $category
     * @return bool
     * @throws \yii\db\Exception
     */
    public function before(self $category): bool
    {
        if ($hitPath = $this->{$this->pathName}) {
            $targetNextCategoryId = $category->{$this->pathName} ? $category->getNext(1)->select('id')->asArray()->scalar() : null;
            $targetFirstChildrenId = $category->{$this->pathName} ? $category->getChildrens(1)->sorted()->select('id')->limit(1)->asArray()->scalar() : null;

            $transaction = Yii::$app->db->beginTransaction();
            try {
                $this->beforeMoveInOctantUp();

                $category->{$this->pathName} = $hitPath;
                $success = $category->save();

                if ($success && $targetFirstChildrenId) {
                    $targetFirstChildren = static::findOne($targetFirstChildrenId);
                    $success = $targetFirstChildren->changeStartPartPath($hitPath);
                }

                if ($success && $targetNextCategoryId) {
                    $targetNextCategory = static::findOne($targetNextCategoryId);
                    $success = $targetNextCategory->afterMoveOutOctantDown();
                }

                if ($success) {
                    $transaction->commit();
                    return true;
                }
            } catch (Exception $e) {
                $transaction->rollBack();
            }

            $transaction->rollBack();
        }

        return false;
    }

    /**
     * Get Tree
     * Example fields can be passed:
     * [
     *  'category_attribute1' => 'model_attribute1',
     *  'model_attribute2',
     *  'category_attribute3' => function ($category) { return $category->attribute3; }
     * ]
     *
     * Example scopes can be passed:
     * ['scope1', 'scope2' => $arg]
     *
     * @param array $fields
     * @param array $scopes
     * @return array
     */
    public static function getTree(array $fields = ['id', 'name'], array $scopes = []): array
    {
        $query = static::find()->sorted();

        foreach ($scopes as $key => $value) {
            if (is_string($key)) {
                $query->$key($value);
            } else {
                $query->$value();
            }
        }

        $categories = $query->all();
        $pathName = (new static())->pathName;

        $tmpTree = [];
        foreach ($categories as $category) {
            $data = new \stdClass();
            foreach ($fields as $k => $v) {
                if (is_callable($v)) {
                    $data->$k = $v($category);
                } else {
                    $k = is_string($k) ? $k : $v;
                    $data->$k = $category->$v;
                }
            }
            $data->children = [];

            $tmpTree[$category->level()][$category->$pathName] = $data;
        }
        unset($categories, $category);

        $tree = [];
        for ($level = count($tmpTree); $level >= 0; $level--) {
            if (isset($tmpTree[$level])) {
                foreach ($tmpTree[$level] as $path => $data) {
                    if ($level > min(array_keys($tmpTree))) {
                        if (isset($tmpTree[$level-1])) {
                            foreach ($tmpTree[$level-1] as $parent_path => $parent_data) {
                                if ($path > $parent_path && strpos($path, $parent_path) !== false) {
                                    $tmpTree[$level-1][$parent_path]->children[] = $data;
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


    /***** NEXT FUNCTION ONLY FOR USE BY FUNCTIONS THAT MOVING CATEGORIES *****/


    /**
     * Move octant on one step up for $this and all after categories and where all levels more or equal $this level
     *
     * @param bool $include
     * @return bool
     */
    protected function beforeMoveInOctantUp(bool $include = true): bool
    {
        $tb = static::tableName();
        $op = $include ? "operator({$this->schema}.>=)" : "operator({$this->schema}.>)";

        return Yii::$app->db->createCommand(
            "UPDATE $tb AS c
            SET {$this->pathName} = ({$this->schema}.subltree(c.{$this->pathName}, 0, {$this->schema}.nlevel(:path) - 1)||'.'||lpad((ltrim({$this->schema}.subltree(c.{$this->pathName}, {$this->schema}.nlevel(:path) - 1, {$this->schema}.nlevel(:path))::varchar, '0')::int + 1)::varchar, :partLen, '0')||(CASE WHEN {$this->schema}.nlevel(c.{$this->pathName}) > {$this->schema}.nlevel(:path) THEN ('.'||{$this->schema}.subpath(c.{$this->pathName}, {$this->schema}.nlevel(:path))) ELSE '' END))::{$this->schema}.ltree
            FROM (SELECT {$this->pathName} FROM $tb WHERE {$this->pathName} operator({$this->schema}.~) :lquery AND {$this->schema}.subltree({$this->pathName}, 0, {$this->schema}.nlevel(:path)) $op :path ORDER BY {$this->pathName} DESC) AS t
            WHERE c.{$this->pathName} operator({$this->schema}.=) t.{$this->pathName}",
            [
                'partLen' => $this->pathPartLen,
                'path' => $this->path,
                'lquery' => $this->generatePathParent() ? $this->generatePathParent() . '.*' : '*'
            ]
        )->execute();
    }

    /**
     * Move octant on one step down for $this and all after categories and where all levels more or equal $this level
     *
     * @param bool $include
     * @return bool
     */
    protected function afterMoveOutOctantDown(bool $include = true): bool
    {
        $tb = static::tableName();
        $op = $include ? "operator({$this->schema}.>=)" : "operator({$this->schema}.>)";

        return Yii::$app->db->createCommand(
            "UPDATE $tb AS c
            SET {$this->pathName} = ({$this->schema}.subltree(c.{$this->pathName}, 0, {$this->schema}.nlevel(:path)- 1)||'.'||lpad((ltrim({$this->schema}.subltree(c.{$this->pathName}, {$this->schema}.nlevel(:path) - 1, {$this->schema}.nlevel(:path))::varchar, '0')::int - 1)::varchar, :partLen, '0')||(CASE WHEN {$this->schema}.nlevel(c.{$this->pathName}) > {$this->schema}.nlevel(:path) THEN ('.'||{$this->schema}.subpath(c.{$this->pathName}, {$this->schema}.nlevel(:path))) ELSE '' END))::{$this->schema}.ltree
            FROM (SELECT {$this->pathName} FROM $tb WHERE {$this->pathName} operator({$this->schema}.~) :lquery AND {$this->schema}.subltree({$this->pathName}, 0, {$this->schema}.nlevel(:path)) $op :path ORDER BY {$this->pathName} ASC) AS t
            WHERE c.{$this->pathName} operator({$this->schema}.=) t.{$this->pathName}",
            [
                'partLen' => $this->pathPartLen,
                'path' => $this->path,
                'lquery' => $this->generatePathParent() ? $this->generatePathParent() . '.*' : '*'
            ]
        )->execute();
    }

    /**
     * Change start part path for $this category and for all after and children
     *
     * @param string $path
     * @return bool
     */
    protected function changeStartPartPath(string $path): bool
    {
        $tb = static::tableName();

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $success = Yii::$app->db->createCommand(
                "UPDATE $tb AS c
                SET {$this->pathName} = (:newPath||(CASE WHEN {$this->schema}.nlevel(c.{$this->pathName}) > {$this->schema}.nlevel(:parentPath) THEN ('.'||{$this->schema}.subpath(c.{$this->pathName}, {$this->schema}.nlevel(:parentPath))) ELSE '' END))::{$this->schema}.ltree
                FROM (SELECT {$this->pathName} FROM $tb WHERE {$this->pathName} operator({$this->schema}.~) :lquery AND {$this->pathName} operator({$this->schema}.>) :parentPath ORDER BY {$this->pathName}) AS t
                WHERE c.{$this->pathName} operator({$this->schema}.=) t.{$this->pathName}",
                [
                    'newPath' => $path,
                    'parentPath' => $this->generatePathParent(),
                    'lquery' => $this->generatePathParent() ? $this->generatePathParent() . '.*' : '*'
                ]
            )->execute();

            if ($success) {
                $transaction->commit();
                return true;
            }
        } catch (Exception $e) {
            $transaction->rollBack();
        }

        $transaction->rollBack();
        return false;
    }

    /**
     * Generate parent path of $this category
     *
     * @return string
     */
    public function generatePathParent(): string
    {
        return implode('.', array_slice(explode('.', $this->{$this->pathName}), 0, $this->level()));
    }

    /**
     * Generate path after $this category
     *
     * @return string
     */
    public function generatePathNext(): string
    {
        return  ($this->generatePathParent() ? "{$this->generatePathParent()}." : '') . sprintf("%0{$this->pathPartLen}d", ltrim(explode('.', $this->{$this->pathName})[$this->level()], '0') + 1);
    }
}
