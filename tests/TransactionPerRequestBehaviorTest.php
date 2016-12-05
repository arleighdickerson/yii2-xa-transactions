<?php


use arls\xa\ConnectionBehavior;
use arls\xa\Transaction;
use arls\xa\TransactionPerRequestBehavior;
use models\TestModel;
use yii\base\Controller;

/**
 * Class TransactionPerRequestBehaviorTest
 * test arls\xa\TransactionPerRequestBehavior
 */
class TransactionPerRequestBehaviorTest extends PHPUnit_Framework_TestCase {
    public static function setUpBeforeClass() {
        parent::setUpBeforeClass();
        Yii::$app->attachBehavior('transactionPerRequest', TransactionPerRequestBehavior::class);
        foreach (TestModel::classes() as $class) {
            $class::getDb()->attachBehavior('xa', ConnectionBehavior::class);
            $class::getDb()->attachBehavior('transactionPerRequest', TransactionPerRequestBehavior::class);
            $class::getDb()->close();
        }
    }

    public function testTransactionOpensAndTerminates() {
        $models = array_map(function ($class) {
            $model = new $class(['value' => uniqid()]);
            $model->save(false);
            $this->assertEquals(Transaction::STATE_ACTIVE, $model->getDb()->xa->transaction->state);
            return $model;
        }, TestModel::classes());
        Yii::$app->trigger(Controller::EVENT_AFTER_ACTION);
        foreach ($models as $model) {
            $this->assertEquals(Transaction::STATE_TERMINATED, $model->getDb()->xa->transaction->state);
        }
    }
}
