<?php


namespace arls\xa;


use yii\base\BootstrapInterface;
use Yii;

class Bootstrap implements BootstrapInterface {
    public function bootstrap($app) {
        Yii::$classMap['yii\db\Transaction'] = dirname(__DIR__) . '/Transaction.php';
        if ($app->get('transactionManager', false) === null) {
            $app->set('transactionManager', TransactionManager::className());
        }
    }
}
