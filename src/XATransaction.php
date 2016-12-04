<?php


namespace arls\xa;

use Yii;
use yii\base\Object;
use yii\db\Connection;

/**
 * Class XATransaction
 * @package arls\xa
 */
class XATransaction extends Object {
    const STMT_BEGIN = "XA START :xid;";
    const STMT_END = "XA END :xid;";
    const STMT_PREPARE = "XA PREPARE :xid;";
    const STMT_COMMIT = "XA COMMIT :xid;";
    const STMT_ROLLBACK = "XA ROLLBACK :xid;";

    const STATE_TERMINATED = 0;
    const STATE_ACTIVE = 1;
    const STATE_IDLE = 2;
    const STATE_PREPARED = 3;

    public function __construct(Connection $db, array $config = []) {
        $this->_db = $db;
        parent::__construct($config);
    }

    public function init() {
        parent::init();
        $this->getTransactionManager()->registerTransaction($this);
    }

    /**
     * @var Connection the database connection that this transaction is associated with.
     */
    private $_db;

    /**
     * @var int $_state
     */
    private $_state;

    public function getState() {
        return $this->_state;
    }

    public function getIsTerminated() {
        return $this->_state == self::STATE_TERMINATED;
    }

    public function getIsActive() {
        return $this->_state == self::STATE_ACTIVE;
    }

    public function getIsIdle() {
        return $this->_state == self::STATE_IDLE;
    }

    public function getIsPrepared() {
        return $this->_state == self::STATE_PREPARED;
    }

    public function begin() {
        $this->exec(self::STMT_BEGIN);
        $this->_state = self::STATE_ACTIVE;
    }

    public function end() {
        $this->exec(self::STMT_END);
        $this->_state = self::STATE_IDLE;
    }

    public function prepare($end = false) {
        if ($end && $this->getIsActive()) {
            $this->end();
        }
        $this->exec(self::STMT_PREPARE);
        $this->_state = self::STATE_PREPARED;
    }

    public function commit($prepare = null) {
        if ($prepare === null) {
            $prepare = $this->getTransactionManager()->autoPrepare;
        }
        if ($prepare && $this->getState() < self::STATE_PREPARED) {
            $this->prepare(true);
        }
        $this->exec(self::STMT_COMMIT);
        $this->_state = self::STATE_TERMINATED;
    }

    public function rollBack($prepare = null) {
        if ($prepare === null) {
            $prepare = $this->getTransactionManager()->autoPrepare;
        }
        if ($prepare && $this->getState() < self::STATE_PREPARED) {
            $this->prepare(true);
        }
        $this->exec(self::STMT_ROLLBACK);
        $this->_state = self::STATE_TERMINATED;
    }

    public function getDb() {
        return $this->_db;
    }

    protected function exec($sql) {
        $gtrid = $this->getTransactionManager()->getId();
        $bqual = $this->getId();
        return $this->getDb()->createCommand(str_replace(':xid', "'$gtrid','$bqual'", $sql))->execute();
    }

    protected function getConnectionId() {
        return $this->getTransactionManager()->getConnectionId($this->getDb());
    }

    protected function getId() {
        return $this->getTransactionManager()->getTransactionId($this);
    }

    /**
     * @return TransactionManager
     */
    protected function getTransactionManager() {
        if (Yii::$app->get('transactionManager', false) === null) {
            Yii::$app->set('transactionManager', TransactionManager::class);
        }
        return Yii::$app->get('transactionManager');
    }
}
