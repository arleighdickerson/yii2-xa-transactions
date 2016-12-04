<?php


namespace arls\xa;


use yii\base\Behavior;
use yii\base\Controller;
use yii\base\InvalidConfigException;
use yii\db\Connection;
use yii\di\Instance;

class TransactionPerRequestBehavior extends Behavior {
    private $_transactionManager;

    public function __construct(TransactionManager $transactionManager, array $config = []) {
        $this->_transactionManager = $transactionManager;
        parent::__construct($config);
    }

    public function events() {
        return [
            Connection::EVENT_AFTER_OPEN => [$this->owner->xa, 'beginTransaction'],
            Controller::EVENT_AFTER_ACTION => [$this->_transactionManager, 'commit']
        ];
    }

    public function attach($owner) {
        if ($owner instanceof Connection) {
            foreach ($owner->getBehaviors() as $behavior) {
                if ($behavior instanceof ConnectionBehavior) {
                    parent::attach($owner);
                    return;
                }
            }
            throw new InvalidConfigException(
                "Connection must have arls\\xa\\ConnectionBehavior attached "
                . "to use arls\\xa\\TransactionPerRequestBehavior"
            );
        }
    }
}