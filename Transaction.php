<?php


namespace yii\db;

use arls\xa\TransactionManager;
use Yii;
use yii\base\InvalidConfigException;
use yii\base\Object;

class Transaction extends Object {
    const STMT_BEGIN = "XA START :xid;";
    const STMT_END = "XA END :xid;";
    const STMT_PREPARE = "XA PREPARE :xid;";
    const STMT_COMMIT = "XA COMMIT :xid;";
    const STMT_ROLLBACK = "XA ROLLBACK :xid;";

    /**
     * @var Connection the database connection that this transaction is associated with.
     */
    public $db;

    /**
     * @var integer the nesting level of the transaction. 0 means the outermost level.
     */
    private $_level;


    /**
     * Returns a value indicating whether this transaction is active.
     * @return boolean whether this transaction is active. Only an active transaction
     * can [[commit()]] or [[rollBack()]].
     */
    public function getIsActive() {
        return $this->_level !== null && $this->db && $this->db->isActive;
    }

    public function begin() {
        if ($this->db === null) {
            throw new InvalidConfigException('Transaction::db must be set.');
        }
        $this->db->open();

        if ($this->_level === null) {
            Yii::trace('Begin transaction', __METHOD__);

            $this->db->trigger(Connection::EVENT_BEGIN_TRANSACTION);
            $this->_level = 0;
            //send begin (db) root tx sql

            return;
        }

        $this->_level++;
        //send begin (db) child tx sql
    }

    /**
     * Commits a transaction.
     * @throws Exception if the transaction is not active
     */
    public function commit() {
        if (!$this->getIsActive()) {
            throw new Exception('Failed to commit transaction: transaction was inactive.');
        }

        $this->_level--;
        if ($this->_level === 0) {
            Yii::trace('Commit transaction', __METHOD__);
            //send end (db) root tx sql
            //send prepare (db) root tx sql
            //send commit (db) root tx sql
            $this->db->trigger(Connection::EVENT_COMMIT_TRANSACTION);
            return;
        }

        //send commit (db) child tx sql
    }

    /**
     * Rolls back a transaction.
     * @throws Exception if the transaction is not active
     */
    public function rollBack() {
        if (!$this->getIsActive()) {
            return;
        }
        $this->_level--;
        if ($this->_level === 0) {
            Yii::trace('Roll back transaction', __METHOD__);
            //send rollback (db) tx root sql
            $this->db->trigger(Connection::EVENT_ROLLBACK_TRANSACTION);
            return;
        }
        //send rollback (db) tx child sql
    }

    /**
     * @return TransactionManager
     */
    public function getTransactionManager() {
        return Yii::$app->get('transactionManager');
    }

    protected function exec($sql, $bqual = null) {
        if ($bqual === null) {
            $bqual = $this->_level;
        }
        $gtrid = $this->getTransactionManager()->getId() . $this->getConnectionId();
        return $this->db->createCommand(str_replace(':xid', "'$gtrid','$bqual'", $sql))->execute();
    }

    protected function getConnectionId() {
        $this->getTransactionManager()->getConnectionId($this->db);
    }
}
