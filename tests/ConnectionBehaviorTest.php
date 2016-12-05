<?php


use arls\xa\ConnectionOperations;
use arls\xa\Transaction;
use models\TestModel;

/**
 * Class ConnectionBehaviorTest
 * tests arls\xa\ConnectionBehavior
 */
class ConnectionBehaviorTest extends PHPUnit_Framework_TestCase {
    public static function setUpBeforeClass() {
        parent::setUpBeforeClass();
        foreach (static::getConnections() as $conn) {
            $conn->attachBehavior('xa', 'arls\xa\ConnectionBehavior');
        }
    }

    public function testBehaviorsAttached() {
        $this->assertNotNull(Yii::$app->getDb()->getBehavior('xa'));
        $this->assertNotNull(Yii::$app->get('db2')->getBehavior('xa'));
    }

    public function testGetOperations() {
        foreach (static::getConnections() as $conn) {
            $ops = $conn->xa;
            $this->assertInstanceOf(ConnectionOperations::class, $ops);
        }
    }

    public function testOperateTransaction() {
        foreach (static::getConnections() as $conn) {
            $tx = $conn->xa->beginTransaction();
            $this->assertInstanceOf(Transaction::class, $tx);
            $this->assertEquals($tx, $conn->xa->transaction);
            $this->assertEquals(Transaction::STATE_ACTIVE, $tx->state);
            $tx->end();
            $tx->rollback();
            $this->assertEquals(Transaction::STATE_TERMINATED,$conn->xa->getTransaction()->getState());
            $this->assertNotNull($conn->xa->getTransaction(true));
            $conn->xa->transaction->end()->rollback();
        }
    }


    public static function getConnections() {
        foreach (TestModel::classes() as $class) {
            yield $class::getDb();
        }
    }
}
