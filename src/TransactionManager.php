<?php


namespace arls\xa;

use yii\base\Component;
use yii\base\Exception;
use yii\db\Connection;
use SplObjectStorage;

class TransactionManager extends Component implements TransactionInterface {
    /**
     * @var SplObjectStorage
     */
    private $_transactions;


    /**
     * @var string the (globally) unique id for this transaction manager
     * all global transaction ids for transactions belonging to this manager
     * will start with this value
     */
    private $_id;

    public function init() {
        parent::init();
        $this->_transactions = new SplObjectStorage();
        $this->regenerateId();
    }

    public function getId() {
        return $this->_id;
    }

    public function commit() {
        foreach ($this->_transactions as $tx) {
            if ($tx->state == Transaction::STATE_ACTIVE) {
                $tx->end();
            }
        }
        try {
            foreach ($this->_transactions as $tx) {
                if ($tx->state == Transaction::STATE_IDLE) {
                    $tx->prepare();
                }
            }
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
        foreach ($this->_transactions as $tx) {
            if ($tx->state == Transaction::STATE_PREPARED) {
                $tx->commit();
            }
        }
        $this->regenerateId();
    }

    public function rollBack() {
        foreach ($this->_transactions as $tx) {
            if ($tx->state == Transaction::STATE_ACTIVE) {
                $tx->end();
            }
        }
        foreach ($this->_transactions as $tx) {
            if ($tx->state > Transaction::STATE_ACTIVE) {
                $tx->rollback();
            }
        }
        $this->regenerateId();
    }

    /**
     * @return \Generator
     */
    public function getTransactions() {
        foreach ($this->_transactions as $transaction) {
            yield $transaction;
        }
    }

    /**
     * @param Transaction $transaction
     */
    public function registerTransaction(Transaction $transaction) {
        $this->_transactions->attach($transaction);
    }

    public function getCurrentTransaction(Connection $connection) {
        $current = null;
        foreach ($this->_transactions as $tx) {
            if ($tx->db === $connection) {
                $current = $tx;
            }
        }
        return $current;
    }

    /**
     * @param Connection $connection
     * @return mixed
     * @throws Exception
     */
    public function getConnectionId(Connection $connection) {
        $set = new SplObjectStorage();
        foreach ($this->_transactions as $tx) {
            if ($connection == $tx->getDb()) {
                return $set->count();
            }
            $set->attach($tx);
        }
        throw new Exception("Connection not found");
    }

    /**
     * @param Transaction $transaction
     * @return mixed
     * @throws Exception
     */
    public function getTransactionId(Transaction $transaction) {
        $id = 0;
        foreach ($this->_transactions as $tx) {
            if ($tx === $transaction) {
                return $id;
            }
            $id++;
        }
        throw new Exception();
    }

    protected function regenerateId() {
        return $this->_id = uniqid();
    }
}
