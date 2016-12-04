<?php


namespace yii\db;

use arls\xa\TransactionManager;
use arls\xa\XATransaction;
use Yii;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\base\Object;

class Transaction extends Object {
    public $db;
    private $_current;
    private $_level = 0;


    /**
     * Returns a value indicating whether the current transaction is active. Also returns true if the current transaction is idle
     * @return boolean whether this transaction is active. Only an active transaction
     * can [[commit()]] or [[rollBack()]].
     */
    public function getIsActive() {
        return $this->getCurrent()->getState() >= XATransaction::STATE_ACTIVE && $this->db && $this->db->isActive;
    }

    /**
     * Begins an transaction.
     */
    public function begin() {
        if ($this->db === null) {
            throw new InvalidConfigException('Transaction::db must be set.');
        }
        Yii::trace('Begin xa transaction', __METHOD__);
        $current = $this->getCurrent();
        if ($this->_level > 0) {
            Yii::info('Transaction not started: nested xa transaction not supported', __METHOD__);
        } elseif ($current === null || $current->getState() == XATransaction::STATE_TERMINATED) {
            $tx = Yii::createObject(XATransaction::class, [$this->db]);
            $tx->begin();
            $this->getTransactionManager()->registerTransaction($tx);
            $this->_current = $tx;
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
        }
        Yii::trace('Commit xa transaction', __METHOD__);
        $this->getCurrent()->{$this->getTransactionManager()->prepareOnCommit ? "prepare" : "commit"}();
        $this->db->trigger(Connection::EVENT_COMMIT_TRANSACTION);
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
     * @return XATransaction|null
     */
    protected function getCurrent() {
        return $this->_current;
    }

    /**
     * @return TransactionManager
     */
    protected function getTransactionManager() {
        return Yii::$app->get('transactionManager');
    }
}
