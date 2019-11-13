<?php


namespace arls\xa;


use yii\base\BaseObject;
use yii\db\Connection;
use Yii;

/**
 * Class ConnectionOperations
 * @package arls\xa
 */
class ConnectionOperations extends BaseObject {
    /**
     * @var TransactionManager
     */
    private $_transactionManager;
    /**
     * @var Connection
     */
    private $_connection;

    /**
     * ConnectionOperations constructor.
     * @param TransactionManager $transactionManager
     * @param Connection $connection
     * @param array $config
     */
    public function __construct(TransactionManager $transactionManager, Connection $connection, array $config = []) {
        $this->_transactionManager = $transactionManager;
        $this->_connection = $connection;
        parent::__construct($config);
    }

    /**
     * Begins a new xa transaction branch.
     * This will fail if the transaction manager has a non-terminated xa transaction on this connection.
     * @return Transaction
     */
    public function beginTransaction() {
        Yii::trace('Begin xa transaction', __METHOD__);
        $transaction = $this->getCurrentTransaction();
        if ($transaction === null) {
            $transaction = Yii::createObject([
                'class' => Transaction::class,
                'db' => $this->_connection
            ]);
        }
        $transaction->begin();
        return $transaction;
    }

    /**
     * @param bool $beginIfNone whether to begin a new transaction branch
     * if there is no current transaction branch or the current transaction branch is terminated
     * @return Transaction|null
     */
    public function getTransaction($beginIfNone = false) {
        $transaction = $this->getCurrentTransaction();
        return ($transaction === null || !$transaction->state) && $beginIfNone
            ? $this->beginTransaction()
            : $transaction;
    }

    protected function getCurrentTransaction() {
        return $this->_transactionManager->getCurrentTransaction($this->_connection);
    }
}
