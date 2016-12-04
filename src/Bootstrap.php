<?php


namespace arls\xa;


use yii\base\BootstrapInterface;
use Yii;


class Bootstrap implements BootstrapInterface {
    public function bootstrap($app) {
        if ($app->get('transactionManager', false) === null) {
            $app->set('transactionManager', TransactionManager::class);
        }
        Yii::$container->setSingleton(TransactionManager::class, $app->get('transactionManager'));
    }
}
