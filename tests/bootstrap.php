<?php


use arls\xa\Bootstrap;

defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'test');
defined('YII_APP_BASE_PATH') or define('YII_APP_BASE_PATH', __DIR__);

require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../vendor/yiisoft/yii2/Yii.php');

spl_autoload_register(function ($class) {
    $file = __DIR__ . '/' . str_replace("\\", "/", $class) . '.php';
    if (file_exists($file)) {
        require_once($file);
    }
    return false;
});
Yii::setAlias("@arls/xa", dirname(__DIR__) . '/src');
(new Bootstrap())->bootstrap(new yii\console\Application(require(__DIR__ . '/config.php')));

$sql = <<<SQL
CREATE TABLE IF NOT EXISTS `test` (
  `id`          INT(11)     NOT NULL AUTO_INCREMENT,
  `value`       VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE = InnoDB;
SQL;
Yii::$app->getDb()->createCommand($sql)->execute();

$sql = <<<SQL
CREATE TABLE IF NOT EXISTS `other` (
  `id`          INT(11)     NOT NULL AUTO_INCREMENT,
  `value`       VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE = InnoDB;
SQL;
Yii::$app->get('db2')->createCommand($sql)->execute();

$classes = ['models\TestModel', 'models\OtherModel'];
$deleteAll = function () use ($classes) {
    foreach ($classes as $class) {
        $class::deleteAll();
    }
};
$deleteAll();
foreach ($classes as $class) {
    for ($i = 0; $i < 3; $i++) {
        $model = new $class;
        $model->value = (string)$i;
        $model->save(false);
    }
}
register_shutdown_function($deleteAll);

