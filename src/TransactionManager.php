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
        $this->_gtrid = $this->regenerateId();
        return $this;
    }

    /**
     * @return static
     */
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
        foreach ($this->_transactions as $tx) {
            /** @var Transaction $tx */
            if ($tx->db === $connection) {
                $current = $tx;
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
        foreach ($this->_transactions as $tx) {
            if ($tx->gtrid == $branch->gtrid) {
                if ($tx->db === $branch->db) {
                    if ($tx === $branch) {
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
