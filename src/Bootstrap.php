<?php


namespace arls\xa;


use yii\base\BootstrapInterface;
use Yii;


/**
 * Class Bootstrap
 * @package arls\xa
 *
 * Sets the transaction manager application component if it is missing.
 * Also registers the transaction manager as a container singleton for constructor injection
 */
class Bootstrap implements BootstrapInterface {
    const COMPONENT_ID = 'transactionManager';

    public function bootstrap($app) {
        if (!Yii::$container->hasSingleton(TransactionManager::class)) {
            Yii::$container->setSingleton(TransactionManager::class);
        }
    }
}
