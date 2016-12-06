<?php


namespace arls\xa;

use yii\base\Component;
use yii\base\Exception;
use yii\db\Connection;
use SplObjectStorage;

/**
 * Class TransactionManager
 * @package arls\xa
 *
 * Manages the global transaction and provides branch transactions with their
 * global transaction id and branch qualifier
 *
 * @property $gtrid the current global transaction id. This property is read-only.
 */
class TransactionManager extends Component implements TransactionInterface {
    /**
     * @var SplObjectStorage
     */
    private $_transactions;


    /**
     * @var string the (unique) current global transaction id
     */
    private $_gtrid;

    /**
     * Initializes the transaction and generates a global transaction id
     */
    public function init() {
        parent::init();
        $this->_transactions = new SplObjectStorage();
        $this->setGtrid($this->regenerateGtrid());
    }

    /**
     * @return string the global transaction id
     */
    public function getGtrid() {
        return $this->_gtrid;
    }

    /**
     * Terminates the global transaction via a two-phase commit.
     * If a failure occurs during the first phase of the commit, the transaction is
     * terminated via a rollback.
     *
     * Any branches that are not in the idle state before this method is called will
     * be brought to the idle state before the actual commit.
     *
     * The global transaction id changes when this method completes,
     * regardless of whether the commit was successful.
     *
     * @return static
     * @throws \Exception
     */
    public function commit() {
        foreach ($this->getTransactions() as $transaction) {
            if ($transaction->state == Transaction::STATE_ACTIVE) {
                $transaction->end();
            }
        }
        try {
            foreach ($this->getTransactions() as $transaction) {
                if ($transaction->state == Transaction::STATE_IDLE) {
                    $transaction->prepare();
                }
            }
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
        foreach ($this->getTransactions() as $transaction) {
            if ($transaction->state == Transaction::STATE_PREPARED) {
                $transaction->commit();
            }
        }
        $this->setGtrid($this->regenerateGtrid());
        return $this;
    }

    /**
     * Rolls back the global transaction by rolling back all of its branches
     *
     * The global transaction id changes when this method completes,
     * regardless of whether the commit was successful.
     *
     * @return static
     */
    public function rollBack() {
        foreach ($this->getTransactions() as $transaction) {
            if ($transaction->state == Transaction::STATE_ACTIVE) {
                $transaction->end();
            }
        }
        foreach ($this->getTransactions() as $transaction) {
            if ($transaction->state > Transaction::STATE_ACTIVE) {
                $transaction->rollback();
            }
        }
        $this->setGtrid($this->regenerateGtrid());
        return $this;
    }

    /**
     * Registers a transaction branch of the current global transaction
     *
     * @param Transaction $transaction
     */
    public function registerTransaction(Transaction $transaction) {
        $this->_transactions->attach($transaction);
    }

    /**
     * Gets the transaction branch which active on a given connection.
     * Note that only one branch at a time can be active on any connection
     * and no branch can belong to more than one connection
     *
     * @param Connection $connection
     * @return null|Transaction
     */
    public function getCurrentTransaction(Connection $connection) {
        $current = null;
        foreach ($this->getTransactions() as $transaction) {
            /** @var Transaction $transaction */
            if ($transaction->db === $connection) {
                $current = $transaction;
            }
        }
        return $current;
    }

    /**
     * Returns the branch qualifier (bqual) of the given transaction branch.
     * Qualifiers are assigned in the order the transaction branches are opened.
     * The first transaction branch registered would have a branch qualifier of 0,
     * the second would have a branch qualifier of 1, etc.
     *
     * @param Transaction $branch
     * @return int non-negative and never null
     * @throws Exception
     */
    public function getBranchQualifier(Transaction $branch) {
        $bqual = 0;
        foreach ($this->getTransactions() as $transaction) {
            /** @var Transaction $transaction */
            if ($transaction->gtrid == $branch->gtrid) {
                if ($transaction->db === $branch->db) {
                    if ($transaction === $branch) {
                        return $bqual;
                    }
                    $bqual++;
                }
            }
        }
        throw new Exception("Branch was not found in global transaction");
    }

    /**
     * Sets the current global transaction id
     *
     * @param string $gtrid a globally unique transaction id (gtrid)
     */
    protected function setGtrid($gtrid) {
        $this->_gtrid = $gtrid;
    }

    /**
     * @return SplObjectStorage the transactions managed by this manager.
     */
    protected function getTransactions() {
        return $this->_transactions;
    }

    /**
     * @return string a globally unique transaction id (gtrid)
     */
    protected function regenerateGtrid() {
        return uniqid();
    }
}
