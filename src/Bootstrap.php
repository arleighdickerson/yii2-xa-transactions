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
    public function bootstrap($app) {
        if ($app->get('transactionManager', false) === null) {
            $app->set('transactionManager', TransactionManager::class);
        }
        Yii::$container->setSingleton(TransactionManager::class, $app->get('transactionManager'));
    }
}
