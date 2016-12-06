<?php


namespace arls\xa;


use yii\base\Application;
use yii\base\Behavior;
use yii\base\Controller;
use yii\base\InvalidConfigException;
use yii\base\InvalidParamException;
use yii\db\Connection;
use Yii;

/**
 * Class TransactionPerRequestBehavior
 * @package arls\xa
 *
 * Attach an instance to a database connection (after attaching arls\xa\ConnectionBehavior)
 * and it will manage the transaction branch lifecycle such that each connection has one transaction per request.
 * The transaction will be started when the connection is open.
 * The transaction will be prepared and terminated (ie committed or rolled back)
 * after the controller action has executed and before the response is sent.
 */
class TransactionPerRequestBehavior extends Behavior {
    /**
     * @var TransactionManager
     */
    private $_transactionManager;

    /**
     * TransactionPerRequestBehavior constructor.
     * @param TransactionManager $transactionManager
     * @param array $config
     */
    public function __construct(TransactionManager $transactionManager, array $config = []) {
        $this->_transactionManager = $transactionManager;
        parent::__construct($config);
    }

    public function events() {
        return [
            Connection::EVENT_AFTER_OPEN => $this->owner instanceof Application
                ? null
                : [$this->owner->xa, 'beginTransaction'],
            Controller::EVENT_AFTER_ACTION => [$this->_transactionManager, 'commit'],
        ];
    }

    public function attach($owner) {
        parent::attach($owner);
        if ($owner instanceof Application) {
            return;
        }
        if ($owner instanceof Connection) {
            foreach ($owner->getBehaviors() as $behavior) {
                if ($behavior instanceof ConnectionBehavior) {
                    return;
                }
            }
            throw new InvalidConfigException(
                "Connection must have arls\\xa\\ConnectionBehavior attached "
                . "to use arls\\xa\\TransactionPerRequestBehavior"
            );
        }
        throw new InvalidParamException("TransactionPerRequestBehavior can only be attached to an applications or connections");
    }
}
