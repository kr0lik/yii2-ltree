<?php
namespace kr0lik\ltree;

use kr0lik\ltree\Exception\LtreeHitModelNotExistException;
use kr0lik\ltree\Exception\LtreeModelSaveException;
use kr0lik\ltree\Exception\LtreeProcessException;
use Throwable;
use Yii;
use yii\db\ActiveQuery;
use yii\db\Expression;

trait LtreeActiveRecordTrait
{
    /**
     * @var string
     */
    private $ltreePathField = 'lpath';

    /**
     * Children will be added there
     *
     * @var ?array<int, mixed>
     */
    public $children;

    public function getLPath(): string
    {
        return (string) $this->{$this->ltreePathField};
    }

    /**
     * 1 - is root
     * 0 - cant get level
     */
    public function getLevel(): int
    {
        if ('' !== $this->getLPath()) {
            return substr_count($this->getLPath(), Ql::SEPORATOR)+1;
        }

        return 0;
    }

    public function isRoot(): bool
    {
        return $this->getLevel() === 1;
    }

    /**
     * Check if $this is first level(After Root)
     */
    public function isFirstLevel(): bool
    {
        return $this->getLevel() == 2;
    }

    /**
     * $level = 0 - get all childs
     * $level = n - get n level childs
     */
    public function getChildrens(int $level = 0): ActiveQuery
    {
        $query = static::find();

        if (!$this->isNewRecord) {
            $query->where([
                new Expression(Ql::operator('<@')),
                new Expression(Ql::pathField(static::tableName(), $this->ltreePathField)),
                $this->getLPath()
            ])->not($this->getLPath());

            if ($level) {
                $query->endLevel($this->getLevel() + $level);
            }
        }

        return $query;
    }

    /**
     * $level = 0 - get all parents
     * $level = n - get n level parents
     */
    public function getParents(int $level = 0): ActiveQuery
    {
        $query = static::find();

        if (!$this->isNewRecord) {
            $query->where([
                new Expression(Ql::operator('@>')),
                new Expression(Ql::pathField(static::tableName(), $this->ltreePathField)),
                $this->getLPath()
            ])->not($this->getLPath());

            if ($level) {
                $query->startLevel($this->getLevel() - $level);
            }
        }

        return $query;
    }

    /**
     * Get Next models of $this in $this level
     */
    public function getNext(): ActiveQuery
    {
        $query = static::find();

        if (!$this->isNewRecord) {
            $query->where([
                new Expression(Ql::operator('~')),
                new Expression(Ql::pathField(static::tableName(), $this->ltreePathField)),
                PathHelper::generateNearLquery($this->getLPath())
            ])->andWhere([
                new Expression(Ql::operator('>')),
                new Expression(Ql::pathField(static::tableName(), $this->ltreePathField)),
                $this->getLPath()
            ])->level($this->getLevel())
                ->sorted();
        }

        return $query;
    }

    /**
     * Get Previous models of $this in $this level
     */
    public function getPrevious(): ActiveQuery
    {
        $query = static::find();

        if (!$this->isNewRecord) {
            $query->where([
                new Expression(Ql::operator('<')),
                new Expression(Ql::pathField(static::tableName(), $this->ltreePathField)),
                $this->getLPath()
            ])->andWhere([
                new Expression(Ql::operator('~')),
                new Expression(Ql::pathField(static::tableName(), $this->ltreePathField)),
                PathHelper::generateNearLquery($this->getLPath())
            ])->level($this->getLevel())
                ->sorted(SORT_DESC);
        }

        return $query;
    }

    /**
     * Get models in $this level
     */
    public function getNearest(): ActiveQuery
    {
        $query = static::find();

        if (!$this->isNewRecord) {
            $query->andWhere([
                new Expression(Ql::operator('~')),
                new Expression(Ql::pathField(static::tableName(), $this->ltreePathField)),
                PathHelper::generateNearLquery($this->getLPath())
            ])->level($this->getLevel())
                ->not($this->getLPath())
                ->sorted();
        }

        return $query;
    }

    public function delete(): bool
    {
        /** @var LtreeActiveRecordTrait $targetNext */
        $targetNext = $this->getNext(1)->one();

        $transaction = Yii::$app->db->beginTransaction();

        try {
            if (!parent::delete()) {
                throw new LtreeProcessException('Target model not deleted.');
            }

            if ($targetNext) {
                if (!PathHelper::moveOctantsDown(static::tableName(), $this->ltreePathField, $targetNext->getLPath())) {
                    throw new LtreeProcessException('Target next models not updated.');
                }
            }
        } catch (Throwable $t) {
            $transaction->rollBack();
            throw $t;
        }

        $transaction->commit();

        return true;
    }

    /**
     * Move/insert $this into $model to the end
     */
    public function appendTo(self $model): void
    {
        if ($model->isNewRecord) {
            throw new LtreeHitModelNotExistException();
        }

        /** @var self $hitLastChildren */
        $hitLastChildren = $model->getChildrens(1)->sorted(SORT_DESC)->limit(1)->one();
        $hitPath = $hitLastChildren ? PathHelper::generateNextPath($hitLastChildren->getLPath()) : PathHelper::generateChildrenPath($model->getLPath());

        if ($hitPath === $this->getLPath()) {
            return;
        }

        $targetFirstChildrenId = !$this->isNewRecord ? $this->getChildrens(1)->limit(1)->select('id')->asArray()->scalar() : null;
        $targetNextId = !$this->isNewRecord ? $this->getNext()->limit(1)->select('id')->asArray()->scalar() : null;

        $transaction = Yii::$app->db->beginTransaction();

        try {
            $this->saveLPath($this, $hitPath, $targetFirstChildrenId, $targetNextId);
        } catch (Throwable $t) {
            $transaction->rollBack();
            throw $t;
        }

        $transaction->commit();
    }

    /**
     * Move/insert $this into $model to the start
     */
    public function prependTo(self $model): void
    {
        if ($model->isNewRecord) {
            throw new LtreeHitModelNotExistException();
        }

        $hitPath = PathHelper::generateChildrenPath($model->getLPath());

        if ($hitPath === $this->getLPath()) {
            return;
        }

        $targetFirstChildrenId = !$this->isNewRecord ? $this->getChildrens(1)->limit(1)->select('id')->asArray()->scalar() : null;
        $targetNextId = !$this->isNewRecord ? $this->getNext()->limit(1)->select('id')->asArray()->scalar() : null;

        $transaction = Yii::$app->db->beginTransaction();

        try {
            PathHelper::moveOctantsUp(static::tableName(), $this->ltreePathField, $hitPath);

            $this->saveLPath($this, $hitPath, $targetFirstChildrenId, $targetNextId);
        } catch (Throwable $t) {
            $transaction->rollBack();
            throw $t;
        }

        $transaction->commit();
    }

    /**
     * Move/insert $this after $model
     */
    public function after(self $model): void
    {
        if ($model->isNewRecord) {
            throw new LtreeHitModelNotExistException();
        }

        $hitPath = PathHelper::generateNextPath($model->getLPath());

        if ($hitPath === $this->getLPath()) {
            return;
        }

        $targetFirstChildrenId = !$this->isNewRecord ? $this->getChildrens(1)->limit(1)->select('id')->asArray()->scalar() : null;
        $targetNextId = !$this->isNewRecord ? $this->getNext()->limit(1)->select('id')->asArray()->scalar() : null;

        $transaction = Yii::$app->db->beginTransaction();

        try {
            PathHelper::moveOctantsUp(static::tableName(), $this->ltreePathField, $hitPath);

            $this->saveLPath($this, $hitPath, $targetFirstChildrenId, $targetNextId);
        } catch (Throwable $t) {
            $transaction->rollBack();
            throw $t;
        }

        $transaction->commit();
    }

    /**
     * Move/insert $this before $model
     */
    public function before(self $model): void
    {
        if ($model->isNewRecord) {
            throw new LtreeHitModelNotExistException();
        }

        $hitPath = $model->getLPath();

        if ($hitPath === $this->getLPath()) {
            return;
        }

        $targetFirstChildrenId = !$this->isNewRecord ? $this->getChildrens(1)->limit(1)->select('id')->asArray()->scalar() : null;
        $targetNextId = !$this->isNewRecord ? $this->getNext()->limit(1)->select('id')->asArray()->scalar() : null;

        $transaction = Yii::$app->db->beginTransaction();

        try {
            PathHelper::moveOctantsUp(static::tableName(), $this->ltreePathField, $hitPath);

            $this->saveLPath($this, $hitPath, $targetFirstChildrenId, $targetNextId);
        } catch (Throwable $t) {
            $transaction->rollBack();
            throw $t;
        }

        $transaction->commit();
    }

    /**
     * Save $this as root
     */
    public function makeRoot(): void
    {
        $lastRootPath = static::find()->root()->sorted(SORT_DESC)->limit(1)->select($this->ltreePathField)->asArray()->scalar();
        $hitPath = $lastRoot ? PathHelper::generateNextPath($lastRootPath) : PathHelper::generateOctant(1);

        $targetFirstChildrenId = !$this->isNewRecord ? $this->getChildrens(1)->limit(1)->select('id')->asArray()->scalar() : null;
        $targetNextId = !$this->isNewRecord ? $this->getNext()->limit(1)->select('id')->asArray()->scalar() : null;

        $transaction = Yii::$app->db->beginTransaction();

        try {
            $this->saveLPath($this, $hitPath, $targetFirstChildrenId, $targetNextId);
        } catch (Throwable $t) {
            $transaction->rollBack();
            throw $t;
        }

        $transaction->commit();
    }

    private function saveLPath(self $model, string $hitPath, ?int $targetFirstChildrenId = null, ?int $targetNextId = null): void
    {
        $model->{$this->ltreePathField} = $hitPath;
        if (!($model->validate([$this->ltreePathField]) && $model->update(false, [$this->ltreePathField]))) {
            throw new LtreeModelSaveException($model->getErrors(), 'Target model not saved.');
        }

        if ($targetFirstChildrenId) {
            /** @var self $targetFirstChildren */
            $targetFirstChildren = static::findOne($targetFirstChildrenId);
            if (!PathHelper::changePrePath(static::tableName(), $this->ltreePathField, $targetFirstChildren->getLPath(), $hitPath)) {
                throw new LtreeProcessException('Target childrens models not updated.');
            }
        }

        if ($targetNextId) {
            /** @var self $targetNex */
            $targetNext = static::findOne($targetNextId);
            if (!PathHelper::moveOctantsDown(static::tableName(), $this->ltreePathField, $targetNext->getLPath())) {
                throw new LtreeProcessException('Target next models not updated.');
            }
        }
    }
}
