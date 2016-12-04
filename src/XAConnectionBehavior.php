<?php


namespace arls\xa;


use yii\base\Behavior;
use Yii;
use yii\db\Connection;

/**
 * Class XAConnectionBehavior
 * @package arls\xa
 * @property Connection $owner
 */
class XAConnectionBehavior extends Behavior {
    private $_transactionManager;

    public function __construct(TransactionManager $transactionManager, array $config = []) {
        $this->_transactionManager = $transactionManager;
        parent::__construct($config);
    }

    public function beginXA() {
        Yii::trace('Begin xa transaction', __METHOD__);
        $xa = $this->getTransactionManager()->getCurrentTransaction($this->owner);
        if ($xa === null) {
            $xa = Yii::createObject([
                'class' => XATransaction::class,
                'db' => $this->owner
            ]);
        }
        $xa->begin();
        $this->owner->trigger(Connection::EVENT_BEGIN_TRANSACTION);
        return $xa;
    }

    public function getXA() {
        $xa = $this->getTransactionManager()->getCurrentTransaction($this->owner);
        return $xa !== null && $xa->getState() ? $xa : null;
    }

    /**
     * @return TransactionManager
     */
    protected function getTransactionManager() {
        return $this->_transactionManager;
    }
}
