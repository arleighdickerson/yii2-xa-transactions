<?php


namespace arls\xa;


use yii\base\Object;
use yii\db\Connection;
use Yii;

class ConnectionOperations extends Object {
    private $_transactionManager;
    private $_connection;

    public function __construct(TransactionManager $transactionManager, Connection $connection, array $config = []) {
        $this->_transactionManager = $transactionManager;
        $this->_connection = $connection;
        parent::__construct($config);
    }

    public function beginTransaction() {
        Yii::trace('Begin xa transaction', __METHOD__);
        $xa = $this->_transactionManager->getCurrentTransaction($this->_connection);
        if ($xa === null) {
            $xa = Yii::createObject([
                'class' => Transaction::class,
                'db' => $this->_connection
            ]);
        }
        $xa->begin();
        $this->_connection->trigger(Connection::EVENT_BEGIN_TRANSACTION);
        return $xa;
    }

    public function getTransaction() {
        $xa = $this->_transactionManager->getCurrentTransaction($this->_connection);
        return $xa !== null && $xa->getState() ? $xa : null;
    }

}