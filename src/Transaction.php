<?php


namespace arls\xa;

use yii\base\InvalidConfigException;
use yii\base\Object;
use yii\db\Connection;
use yii\di\Instance;

/**
 * Class Transaction
 * @package arls\xa
 * @see https://dev.mysql.com/doc/refman/5.6/en/xa.html
 * represents a branch of the global transaction being managed by the transaction manager
 *
 * @property string $gtrid the global transaction id. This property is read-only.
 * @property string $bqual this transaction's branch qualifier. This property is read-only.
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

    /**
     * Transaction constructor.
     * @param TransactionManager $transactionManager
     * @param array $config
     */
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
    }

    /**
     * @var string
     */
    private $_gtrid;

    /**
     * @inheritdoc
     * @see TransactionInterface::getGtrid()
     */
    public function getGtrid() {
        return $this->_gtrid;
    }

    /**
     * @inheritdoc
     * @see BranchInterface::getBqual()
     */
    public function getBqual() {
        return $this->getTransactionManager()->getBranchQualifier($this);
    }

    /**
     * @var int
     */
    private $_state;

    /**
     * @inheritdoc
     * @see BranchInterface::getState()
     */
    public function getState() {
        return $this->_state;
    }

    /**
     * @inheritdoc
     * @see BranchInterface::begin()
     */
    public function begin() {
        $this->registerWithTransactionManager();
        $this->exec(self::STMT_BEGIN);
        $this->_state = self::STATE_ACTIVE;
        return $this;
    }

    /**
     * @inheritdoc
     * @see BranchInterface::end()
     */
    public function end() {
        $this->exec(self::STMT_END);
        $this->_state = self::STATE_IDLE;
        return $this;
    }

    /**
     * @inheritdoc
     * @see BranchInterface::prepare()
     */
    public function prepare() {
        $this->exec(self::STMT_PREPARE);
        $this->_state = self::STATE_PREPARED;
        return $this;
    }

    /**
     * @inheritdoc
     * @see TransactionInterface::commit()
     */
    public function commit() {
        $this->exec(self::STMT_COMMIT);
        $this->_state = self::STATE_TERMINATED;
        return $this;
    }

    /**
     * @inheritdoc
     * @see TransactionInterface::rollBack()
     */
    public function rollBack() {
        $this->exec(self::STMT_ROLLBACK);
        $this->_state = self::STATE_TERMINATED;
        return $this;
    }

    /**
     * @return Connection
     */
    public function getDb() {
        return Instance::ensure($this->db, Connection::class);
    }

    /**
     * @param $sql
     * @return int
     * replace :xid with this transaction's xid and execute the sql
     */
    protected function exec($sql) {
        return $this->getDb()->createCommand(
            str_replace(':xid', "'{$this->getGtrid()}','{$this->getBqual()}'", $sql)
        )->execute();
    }

    /**
     * register this branch with the transaction manager and set the gtrid
     */
    protected function registerWithTransactionManager() {
        $this->_gtrid = $this->getTransactionManager()->getGtrid();
        $this->getTransactionManager()->registerTransaction($this);
    }

    /**
     * @return TransactionManager
     */
    protected function getTransactionManager() {
        return $this->_transactionManager;
    }
}
