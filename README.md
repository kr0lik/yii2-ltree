# yii2-ltree
Postgresql ltree traits for yii2 

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
composer require --prefer-dist kr0lik/yii2-ltree
```

or add

```
"kr0lik/yii2-ltree": "*"
```

to the require section of your `composer.json` file.

Usage
-----
Required fileds in model: id, path.

Extension ltree must be instaled with schema `public`.
You can change it by changing static property `Ql::$ltreeSchema`

By default path field in table must be named as `lpath`
You can change it by changing property `ltreePathField` in traits

Add \kr0lik\ltree\LtreeActiveRecordTrait to your ActiveRecord

Available methods:

```php
/**
 * Get path of $model
 */
$model->getLPath(): string

/**
 * Get level of $model
 * retutn 1 - is root
 * retutn 0 - cant get level
 */
$model->getLevel(): int;

/**
 * Check if $model is root(0 level)
 */
$model->isRoot(): bool;

/**
 * Check if $model is first level(After Root)
 */
$model->isFirstLevel(): bool;

/**
 * Get childrens of $model
 *
 * @param int $level DEFAULT 0
 * $level = 0 - get all childs
 * $level = n - get n level childs
 */
$model->getChildrens($level): ActiveQuery;

/**
 * Get parents of $model
 *
 * @param int $level DEFAULT 0
 * $level = 0 - get all parents
 * $level = n - get n level parents
 */
$model->getParents($level): ActiveQuery;

/**
 * Get Next categories of $model in $model level
 */
$model->getNext(): ActiveQuery;

/**
 * Get Previous categories of $model in $model level
 */
$model->getPrevious(): ActiveQuery;

/**
 * Get categories in $model level
 *
 * @return ActiveQuery
 */
$model->getNearest(): ActiveQuery;

/**
 * Remove $model from db
 */
$model->delete(): bool;

/**
 * Move/insert $model into $anotherModel to the end
 *
 * @param self $anotherModel
 */
$model->appendTo($anotherModel): void;

/**
 * Move/insert $model into $anotherModel to the start
 *
 * @param self $anotherModel
 */
$model->prependTo($anotherModel): void;

/**
 * Move/insert $model after $anotherModel
 *
 * @param self $anotherModel
 */
$model->after($anotherModel): void;

/**
 * Move/insert $model before $anotherModel
 *
 * @param self $anotherModel
 */
$model->before($anotherModel): void;

/**
 * Save $model as root
 */
$model->makeRoot(): void
```

Add \kr0lik\ltree\LtreeQueryTrait to your ActiveQuery

Available methods:

```php
/**
 * Sort by path
 * 
 * @param int $sort DEFAULT SORT_ASC
 */
Model::find()->sorted($sort): ActiveQuery;

/**
 * Get all without root
 */
Model::find()->notRoot(): ActiveQuery;

/**
 * Get root only
 */
Model::find()->root(): ActiveQuery;

/**
 * Get models by $path
 *
 * @param string $path
 * @param boolean $recursive DEFAULT true
 * If $recursive == true then get all models where path field value starts from $path(with all childrens)
 */
Model::find()->byPath($path, $recursive): ActiveQuery;

/**
 * Get not equal path
 *
 * @param string $path
 */
Model::find()->not($path): ActiveQuery;

/**
 * Join parents
 *
 * @param int $level DEFAULT 0
 * $level = 0 - get all parents
 * $level = n - get n levels of parents start from $this level
 * @param string $joinType DEFAULT 'LEFT JOIN'
 */
Model::find()>joinParents($level, $joinType): ActiveQuery;

/**
 * Join childrens
 *
 * @param int $level DEFAULT 0
 * $level = 0 - get all childrens
 * $level = n - get n levels of childrens start from $this level
 * @param string $joinType DEFAULT 'LEFT JOIN'
 */
Model::find()->joinChildrens($level, $joinType): ActiveQuery;

/**
 * Set start level
 *
 * @param int $level
 */
Model::find()->startLevel($level): ActiveQuery;

/**
 * Set end level
 *
 * @param int $level
 */
Model::find()->endLevel($level): ActiveQuery;
 
/**
 * Set level
 *
 * @param int $level
 */
Model::find()->level($level): ActiveQuery;

/**
 * Get all as tree
 *
 * @return array<int, mixed>
 */
Model::find()->tree(): array;
```
