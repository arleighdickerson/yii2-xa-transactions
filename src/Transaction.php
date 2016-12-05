<?php


namespace arls\xa;

use Yii;
use yii\base\InvalidConfigException;
use yii\base\Object;
use yii\db\Connection;
use yii\di\Instance;

/**
 * Class Transaction
 * @package arls\xa
 * @see https://dev.mysql.com/doc/refman/5.6/en/xa.html
 * represents a branch of the global transaction being managed by the transaction manager
 */
class Transaction extends Object implements BranchInterface {
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
     * @var TransactionManager
     */
    private $_transactionManager;

    public function __construct(TransactionManager $transactionManager, array $config = []) {
        $this->_transactionManager = $transactionManager;
        parent::__construct($config);
    }

    public function init() {
        parent::init();
        if ($this->db === null) {
            throw new InvalidConfigException("db must not be null");
        } elseif ($this->getDb()->getDriverName() != 'mysql') {
            throw new InvalidConfigException("XA Transactions are only supported for mysql driver");
        }
        $this->getTransactionManager()->registerTransaction($this);
    }

    /**
     * @var int
     */
    private $_state;

    /**
     * @return int
     */
    public function getState() {
        return $this->_state;
    }

    /**
     * @return static
     */
    public function begin() {
        $this->exec(self::STMT_BEGIN);
        $this->_state = self::STATE_ACTIVE;
        return $this;
    }

    /**
     * @return static
     */
    public function end() {
        $this->exec(self::STMT_END);
        $this->_state = self::STATE_IDLE;
        return $this;
    }

    /**
     * @return static
     */
    public function prepare() {
        $this->exec(self::STMT_PREPARE);
        $this->_state = self::STATE_PREPARED;
        return $this;
    }

    /**
     * @return static
     */
    public function commit() {
        $this->exec(self::STMT_COMMIT);
        $this->_state = self::STATE_TERMINATED;
        return $this;
    }

    /**
     * @return static
     */
    public function rollBack() {
        $this->exec(self::STMT_ROLLBACK);
        $this->_state = self::STATE_TERMINATED;
        return $this;
    }

    /**
     * @return string
     */
    public function getId() {
        return $this->getTransactionManager()->getTransactionId($this);
    }

    /**
     * @return Connection
     */
    public function getDb() {
        return Instance::ensure($this->db, Connection::class);
    }

    /**
     * @return int
     */
    protected function getConnectionId() {
        return $this->getTransactionManager()->getConnectionId($this->getDb());
    }

    /**
     * @param $sql
     * @return int
     */
    protected function exec($sql) {
        $gtrid = $this->getTransactionManager()->getId();
        $bqual = $this->getId();
        return $this->getDb()->createCommand(str_replace(':xid', "'$gtrid','$bqual'", $sql))->execute();
    }

    /**
     * @return TransactionManager
     */
    protected function getTransactionManager() {
        return $this->_transactionManager;
    }
}
