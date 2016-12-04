<?php


namespace arls\xa;


use yii\base\Behavior;
use Yii;
use yii\db\Connection;
use yii\di\Instance;

/**
 * Class XAConnectionBehavior
 * @package arls\xa
 * @property Connection $owner
 */
class ConnectionBehavior extends Behavior {
    private $_transactionManager;
    private $_operations;

    public function attach($owner) {
        Instance::ensure($owner, Connection::class);
        parent::attach($owner);
    }

    public function __construct(TransactionManager $transactionManager, array $config = []) {
        $this->_transactionManager = $transactionManager;
        parent::__construct($config);
    }

    public function getXa() {
        if ($this->_operations === null) {
            $this->_operations = Yii::createObject(ConnectionOperations::class, [
                $this->_transactionManager,
                $this->owner
            ]);
        }
        return $this->_operations;
    }

    /**
     * @return TransactionManager
     */
    protected function getTransactionManager() {
        return $this->_transactionManager;
    }
}
