<?php


namespace yii\db;

use arls\xa\TransactionManager;
use arls\xa\Branch;
use Yii;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\base\Object;

/**
 * A Transaction class that delegates to XA transactions via arls\xa\XATransaction
 * We patch the autoloader in the extension bootstrap to use this class over the stock transaction class.
 * Class Transaction
 * @package yii\db
 */
class Transaction extends Object {
    public $db;
    private $_level = 0;

    /**
     * Returns a value indicating whether the current transaction is active. Also returns true if the current transaction is idle
     * @return boolean whether this transaction is active. Only an active transaction
     * can [[commit()]] or [[rollBack()]].
     */
    public function getIsActive() {
        return ($this->getCurrent() === null || $this->getCurrent()->getState() >= Branch::STATE_ACTIVE)
            && $this->db
            && $this->db->isActive;
    }

    /**
     * Begins an transaction.
     */
    public function begin() {
        if ($this->db === null) {
            throw new InvalidConfigException('Transaction::db must be set.');
        }
        Yii::trace('Begin xa transaction', __METHOD__);
        $tx = $this->getCurrent();
        if ($tx !== null && $tx->getState()) {
            Yii::info('Transaction not started: nested xa transaction not supported', __METHOD__);
        } else {
            $tx = Yii::createObject(Branch::class, [$this->db]);
            $this->getTransactionManager()->registerBranch($tx);
            $tx->begin();
            $this->db->trigger(Connection::EVENT_BEGIN_TRANSACTION);
        }
        $this->_level++;
    }

    /**
     * Commits a transaction.
     * @throws Exception if the transaction is not active
     */
    public function commit() {
        if (!$this->getIsActive()) {
            throw new Exception('Failed to commit transaction: transaction was inactive.');
        }

        $this->_level--;
        if ($this->_level > 0) {
            Yii::info('Transaction not committed: nested xa transaction not supported', __METHOD__);
        } else {
            Yii::trace('Commit xa transaction', __METHOD__);
            $tx = $this->getCurrent();
            if ($this->getTransactionManager()->autoPrepare) {
                if ($tx->getState() == Branch::STATE_ACTIVE) {
                    $tx->end();
                }
                if ($tx->getState() == Branch::STATE_IDLE) {
                    $tx->prepare();
                }
            }
            $tx->commit();
            $this->db->trigger(Connection::EVENT_COMMIT_TRANSACTION);
        }
        return;
    }

    /**
     * Rolls back a transaction.
     * @throws Exception if the transaction is not active
     */
    public function rollBack() {
        if (!$this->getIsActive()) {
            // do nothing if transaction is not active: this could be the transaction is committed
            // but the event handler to "commitTransaction" throw an exception
            return;
        }

        $this->_level--;
        if ($this->_level === 0) {
            Yii::trace('Roll back xa transaction', __METHOD__);
            $tx = $this->getCurrent();
            if ($tx->getIsActive()) {
                $tx->end();
            }
            $this->getCurrent()->rollBack(false);
            $this->db->trigger(Connection::EVENT_ROLLBACK_TRANSACTION);
            return;
        }

        Yii::info('Transaction not rolled back: nested transaction not supported', __METHOD__);
        // throw an exception to fail the outer transaction
        throw new Exception('Roll back failed: nested transaction not supported.');
    }

    public function setIsolationLevel() {
        throw new NotSupportedException(__METHOD__ . ' is not supported.');
    }

    /**
     * @return integer The current nesting level of the transaction.
     * @since 2.0.8
     */
    public function getLevel() {
        return $this->_level;
    }

    /**
     * @return Branch|null
     */
    protected function getCurrent() {
        return $this->getTransactionManager()->getCurrentBranch($this->db);
    }

    /**
     * @return TransactionManager
     */
    public function getTransactionManager() {
        return Yii::$app->get('transactionManager');
    }
}
