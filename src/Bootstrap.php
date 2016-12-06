<?php


namespace arls\xa;


use yii\base\BootstrapInterface;
use Yii;
use yii\db\mysql\Schema;


/**
 * Class Bootstrap
 * @package arls\xa
 *
 * Sets the transaction manager application component if it is missing.
 * Also registers the transaction manager as a container singleton for constructor injection
 */
class Bootstrap implements BootstrapInterface {
    public function bootstrap($app) {
        if (!Yii::$container->hasSingleton(TransactionManager::class)) {
            Yii::$container->setSingleton(TransactionManager::class);
        }
        Yii::$container->set(Schema::class, function ($container, $params, $config) {
            $schema = new Schema($config);
            $schema->exceptionMap['global transaction'] = TransactionException::class;
            return $schema;
        });
    }
}
