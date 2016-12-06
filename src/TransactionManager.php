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
    private $_gtrid;

    /**
     * Initializes the transaction and generates a gtrid
     */
    public function init() {
        parent::init();
        $this->_transactions = new SplObjectStorage();
        $this->_gtrid = $this->regenerateId();
    }

    /**
     * @return string
     */
    public function getGtrid() {
        return $this->_gtrid;
    }

    /**
     * @return static
     * @throws \Exception
     */
    public function commit() {
        foreach ($this->_transactions as $transaction) {
            if ($transaction->state == Transaction::STATE_ACTIVE) {
                $transaction->end();
            }
        }
        try {
            foreach ($this->_transactions as $transaction) {
                if ($transaction->state == Transaction::STATE_IDLE) {
                    $transaction->prepare();
                }
            }
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
        foreach ($this->_transactions as $transaction) {
            if ($transaction->state == Transaction::STATE_PREPARED) {
                $transaction->commit();
            }
        }
        $this->_gtrid = $this->regenerateId();
        return $this;
    }

    /**
     * @return static
     */
    public function rollBack() {
        foreach ($this->_transactions as $transaction) {
            if ($transaction->state == Transaction::STATE_ACTIVE) {
                $transaction->end();
            }
        }
        foreach ($this->_transactions as $transaction) {
            if ($transaction->state > Transaction::STATE_ACTIVE) {
                $transaction->rollback();
            }
        }
        $this->_gtrid = $this->regenerateId();
        return $this;
    }

    /**
     * @param Transaction $transaction
     */
    public function registerTransaction(Transaction $transaction) {
        $this->_transactions->attach($transaction);
    }

    /**
     * @param Connection $connection
     * @return null|Transaction
     */
    public function getCurrentTransaction(Connection $connection) {
        $current = null;
        foreach ($this->_transactions as $transaction) {
            /** @var Transaction $transaction */
            if ($transaction->db === $connection) {
                $current = $transaction;
            }
        }
        return $current;
    }

    /**
     * @param Transaction $branch
     * @return int
     * @throws Exception
     */
    public function getBranchQualifier(Transaction $branch) {
        $id = 0;
        foreach ($this->_transactions as $transaction) {
            /** @var Transaction $transaction */
            if ($transaction->gtrid == $branch->gtrid) {
                if ($transaction->db === $branch->db) {
                    if ($transaction === $branch) {
                        return $id;
                    }
                    $id++;
                }
            }
        }
        throw new Exception("Branch was not found in global transaction");
    }

    /**
     * @return \SplObjectStorage
     */
    protected function getTransactions() {
        return $this->_transactions;
    }

    /**
     * @return string
     */
    protected function regenerateId() {
        return uniqid();
    }
}
