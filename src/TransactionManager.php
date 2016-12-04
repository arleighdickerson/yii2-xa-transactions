<?php


namespace arls\xa;

use yii\base\Component;
use yii\base\Exception;
use yii\db\Connection;

class TransactionManager extends Component implements XAInterface {
    /**
     * @var \SplObjectStorage
     */
    private $_transactions;

    /**
     * @var Connection[]
     */
    private $_connections = [];

    /**
     * @var string the (globally) unique id for this transaction manager
     * all global transaction ids for transactions belonging to this manager
     * will start with this value
     */
    private $_id;

    public function init() {
        parent::init();
        $this->_transactions = new \SplObjectStorage();
        $this->regenerateId();
    }

    public function getId() {
        return $this->_id;
    }

    public function commit() {
        foreach ($this->_transactions as $tx) {
            if ($tx->state == XATransaction::STATE_ACTIVE) {
                $tx->end();
            }
        }
        try {
            foreach ($this->_transactions as $tx) {
                if ($tx->state == XATransaction::STATE_IDLE) {
                    $tx->prepare();
                }
            }
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
        foreach ($this->_transactions as $tx) {
            if ($tx->state == XATransaction::STATE_PREPARED) {
                $tx->commit();
            }
        }
        $this->terminateGlobalTransaction();
    }

    public function rollBack() {
        foreach ($this->_transactions as $tx) {
            if ($tx->state == XATransaction::STATE_ACTIVE) {
                $tx->end();
            }
        }
        foreach ($this->_transactions as $tx) {
            if ($tx->state > XATransaction::STATE_ACTIVE) {
                $tx->rollback();
            }
        }
        $this->terminateGlobalTransaction();
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
     * @param XATransaction $transaction
     */
    public function registerTransaction(XATransaction $transaction) {
        $this->_transactions->attach($transaction);
        if (!in_array($transaction->getDb(), $this->_connections)) {
            $this->_connections[] = $transaction->getDb();
        }
    }

    public function getCurrentTransaction(Connection $connection) {
        foreach ($this->_transactions as $tx) {
            if ($tx->state && $tx->getDb() == $connection) {
                return $tx;
            }
        }
        return null;
    }

    /**
     * @param Connection $connection
     * @return mixed
     * @throws Exception
     */
    public function getConnectionId(Connection $connection) {
        if (($id = array_search($connection, $this->_connections)) !== false) {
            return $id;
        }
        throw new Exception();
    }

    /**
     * @param XATransaction $transaction
     * @return mixed
     * @throws Exception
     */
    public function getTransactionId(XATransaction $transaction) {
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

    protected function terminateGlobalTransaction() {
        $this->regenerateId();
        $this->_transactions->removeAll($this->_transactions);
    }
}
