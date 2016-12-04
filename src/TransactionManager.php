<?php


namespace arls\xa;

use yii\base\Component;
use yii\base\Exception;
use yii\db\Connection;

class TransactionManager extends Component {
    /**
     * @var bool whether to prepare instead of commit when yii\db\Transaction::commit() is called
     */
    public $prepareOnCommit = false;

    /**
     * @var bool whether to automatically move transactions into the "prepared" state before committing or rolling back
     */
    public $autoPrepare = true;

    /**
     * @var XATransaction[]
     */
    private $_transactions = [];

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
        $this->_id = uniqid();
    }

    /**
     * @return string
     */
    public function getId() {
        return $this->_id;
    }

    public function commitGlobal() {
        $pending = $this->getPendingTransactions();
        try {
            foreach ($pending as $tx) {
                $tx->prepare(true);
            }
        } catch (\Exception $e) {
            $this->rollbackGlobal();
            throw $e;
        }
        foreach ($pending as $tx) {
            $tx->commit();
        }
    }

    public function rollbackGlobal() {
        foreach ($this->getPendingTransactions() as $tx) {
            $tx->rollback(true);
        }
    }

    /**
     * @return XATransaction[]
     */
    protected function getPendingTransactions() {
        return array_filter($this->_transactions, function ($tx) {
            /** @var XATransaction $tx */
            return $tx->getState() >= XATransaction::STATE_ACTIVE;
        });
    }

    /**
     * @param XATransaction $transaction
     */
    public function registerTransaction(XATransaction $transaction) {
        $this->_transactions[] = $transaction;
        if (!in_array($transaction->getDb(), $this->_connections)) {
            $this->_connections[] = $transaction->getDb();
        }
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
        if (($id = array_search($transaction, $this->_transactions)) !== false) {
            return $id;
        }
        throw new Exception();
    }
}
