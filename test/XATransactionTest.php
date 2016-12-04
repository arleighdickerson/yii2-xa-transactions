<?php


use arls\xa\XATransaction;
use models\OtherModel;
use models\TestModel;
use yii\base\Model;
use yii\db\ActiveRecord;

class XATransactionTest extends PHPUnit_Framework_TestCase {
    public function testSanity() {
        $this->assertGreaterThan(0, TestModel::find()->count());
        $this->assertGreaterThan(0, OtherModel::find()->count());
    }

    public function testStates() {
        foreach (['commit', 'rollback'] as $finalize) {
            foreach ([$this->getTx(true), $this->getTx2(true)] as $tx) {
                $tx->begin();
                $this->assertEquals(XATransaction::STATE_ACTIVE, $tx->getState());
            }
            foreach ([$this->getTx(), $this->getTx2()] as $tx) {
                $tx->end();
                $this->assertEquals(XATransaction::STATE_IDLE, $tx->getState());
            }
            foreach ([$this->getTx(), $this->getTx2()] as $tx) {
                $tx->prepare();
                $this->assertEquals(XATransaction::STATE_PREPARED, $tx->getState());
            }
            foreach ([$this->getTx(), $this->getTx2()] as $tx) {
                $tx->$finalize();
                $this->assertEquals(XATransaction::STATE_TERMINATED, $tx->getState());
            }
        }
    }

    public function testPersistence() {
        foreach (['commit', 'rollback'] as $finalize) {
            /** @var ActiveRecord[] $models */
            $counts = array_map(function ($class) {
                return $class::find()->count();
            }, $this->getClasses());
            $assertCount = function ($delta) use ($counts) {
                foreach ($counts as $class => $count) {
                    $this->assertEquals($count + $delta, $class::find()->count());
                }
            };
            $assertCount(0);
            $txs = array_map(function ($class) {
                return new XATransaction($class::getDb());
            }, $this->getClasses());
            foreach ($txs as $class => $tx) {
                $tx->begin();
            }
            foreach ($this->getClasses() as $class) {
                $model = new $class;
                $model->value = uniqid();
                $model->save(false);
                return $model;
            }
            $assertCount(1);
            foreach ($txs as $class => $tx) {
                $tx->$finalize();
            }
            $assertCount($finalize == 'commit' ? 1 : 0);
        }
    }

    private $_tx;

    public function getTx($create = false) {
        if ($create || $this->_tx === null) {
            $this->_tx = new XATransaction(Yii::$app->getDb());
        }
        return $this->_tx;
    }

    private $_tx2;

    public function getTx2($create = false) {
        if ($create || $this->_tx2 === null) {
            $this->_tx2 = new XATransaction(Yii::$app->get('db2'));
        }
        return $this->_tx2;
    }

    private $_classes;

    public function getConnections() {
        return array_map(function ($class) {
            return $class::getDb();
        }, $this->getClasses());
    }

    public function getClasses() {
        if ($this->_classes === null) {
            $classes = [
                TestModel::class,
                OtherModel::class
            ];
            $this->_classes = array_combine($classes, $classes);
        }
        return $this->_classes;
    }

    public function tearDown() {
        foreach ($this->getConnections() as $db) {
            $db->close();
        }
        parent::tearDown();
    }
}