<?php


namespace arls\xa;


use yii\base\Object;
use yii\db\Connection;
use Yii;

/**
 * Class ConnectionOperations
 * @package arls\xa
 */
class ConnectionOperations extends Object {
    /**
     * @var TransactionManager
     */
    private $_transactionManager;
    /**
     * @var Connection
     */
    private $_connection;

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
        $tx = $this->_transactionManager->getCurrentTransaction($this->_connection);
        if ($tx === null) {
            $tx = Yii::createObject([
                'class' => Transaction::class,
                'db' => $this->_connection
            ]);
        }
        $tx->begin();
        return $tx;
    }

    /**
     * @param bool $beginIfNone whether to begin a new transaction branch
     * if there is no current transaction branch or the current transaction branch is terminated
     * @return Transaction|null
     */
    public function getTransaction($beginIfNone = false) {
        $tx = $this->_transactionManager->getCurrentTransaction($this->_connection);
        if (($tx === null || !$tx->state) && $beginIfNone) {
            $tx = $this->beginTransaction();
        }
        return $tx;
    }
}
