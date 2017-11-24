# yii2-ltree
Postgresql ltree traits for yii2 

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
composer require --prefer-dist kr0lik/yii2-ltree "dev-master"
```

or add

```
"kr0lik/yii2-ltree": "dev-master"
```

to the require section of your `composer.json` file.

Usage
-----
Required fileds in model: id, name, path.

Lenght of each part ltree path = 4, root path = '0001'.

Extension ltree must be instaled with schema public.



Add \kr0lik\ltree\LtreeActiveRecordTrait to your ActiveRecord

Available methods:

```php
/**
 * Get level of $model
 * 0 - is root
 * -1 - cant get level
 *
 * @return int
 */
$model->level();

/**
 * Check if $model is root(0 level)
 *
 * @return bool
 */
$model->isRoot();

/**
 * Check if $model is first level(After Root)
 *
 * @return bool
 */
$model->isFirstLevel();

/**
 * Get childrens of $model
 *
 * @param int $level
 * $level = 0 - get all childs
 * $level = n - get n level childs
 * @return ActiveQuery
 */
$model->getChildrens($level);

/**
 * Get parents of $model
 *
 * @param int $level
 * $level = 0 - get all parents
 * $level = n - get n level parents
 * @return ActiveQuery
 */
$model->getParents($level);

/**
 * Get Next categories of $model in $model level
 *
 * @param int $count
 * @return ActiveQuery
 */
$model->getNext($count);

/**
 * Get Previous categories of $model in $model level
 *
 * @param int $count
 * @return ActiveQuery
 */
$model->getPrevious($count);

/**
 * Remove $this from db
 *
 * @return bool
 */
$model->delete();

/**
 * Move/insert $anotherModel into $model to the end
 *
 * @param self $anotherModel
 * @return bool
 */
$model->append($anotherModel);

/**
 * Move/insert $anotherModel into $model to the start
 *
 * @param self $anotherModel
 * @return bool
 */
$model->prepend($anotherModel);

/**
 * Move/insert $anotherModel after $model
 *
 * @param self $anotherModel
 * @return bool
 */
$model->after($anotherModel);

/**
 * Move/insert $anotherModel before $model
 *
 * @param self $anotherModel
 * @return bool
 */
$model->before($anotherModel);

/**
 * Get Tree
 *
 * @param array $fields
 * Example fields to output:
 * [
 *  'category_attribute1' => 'model_attribute1',
 *  'model_attribute2',
 *  'category_attribute3' => function ($category) { return $category->attribute3; }
 * ]
 * @param array $scopes
 * @return array
 */
$model->getTree(['id', 'name'], ['active']);
```

Add \kr0lik\ltree\LtreeQueryTrait to your ActiveQuery

Available methods:

```php
/**
 * Sort by path
 * 
 * @param int $sort DEFAULT SORT_ASC
 * @return ActiveQuery
 */
Model::find()->sorted(SORT_ASC);

/**
 * Get all without root
 *
 * @return ActiveQuery
 */
Model::find()->notRoot();

/**
 * Get root only
 *
 * @return ActiveQuery
 */
Model::find()->root();

/**
 * Get models by $path
 *
 * @param string $path
 * @param boolean $recursive DEFAULT true
 * If $recursive == true then get all models where path field value starts from $path(with all childrens)
 * @return ActiveQuery
 */
Model::find()->byPath('0001.0001', false);

/**
 * Join parents
 *
 * @param int $level DEFAULT 0
 * $level = 0 - get all parents
 * $level = n - get n levels of parents start from $this level
 * @param string $joinType DEFAULT 'LEFT JOIN'
 * @return ActiveQuery
 */
Model::find()>joinParents($level, 'LEFT JOIN');

/**
 * Join childrens
 *
 * @param int $level DEFAULT 0
 * $level = 0 - get all childrens
 * $level = n - get n levels of childrens start from $this level
 * @param string $joinType DEFAULT 'LEFT JOIN'
 * @return ActiveQuery
 */
Model::find()->joinChildrens($level, 'LEFT JOIN');
```
