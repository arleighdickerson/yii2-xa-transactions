<?php


namespace arls\xa;

use yii\base\Component;
use yii\base\Exception;
use yii\db\Transaction;
use ArrayObject;
use SplObjectStorage;

class TransactionManager extends Component {
    /**
     * @var SplObjectStorage
     */
    private $_connections;

    public function init() {
        parent::init();
        $this->_connections = new SplObjectStorage();
    }

    /**
     * @param Transaction $transaction
     */
    public function registerTransaction($transaction) {
        if (!$this->_connections->offsetExists($transaction->db)) {
            $this->_connections->offsetSet($transaction->db, new ArrayObject());
        }
        $this->_connections->offsetGet($transaction->db)->append($transaction);
    }

    public function getConnectionId($connection) {
        if (($id = array_search($connection, $this->_connections->offsetGet($connection->db))) !== false) {
            return $id;
        }
        throw new Exception();
    }

    public function getTransactionId($transaction) {
        $id = 0;
        foreach ($this->_connections as $db) {
            if ($transaction->db == $db) {
                return $id;
            }
            $id++;
        }
        throw new Exception();
    }
}
