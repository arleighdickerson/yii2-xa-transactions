<?php


namespace arls\xa;


use yii\base\Application;
use yii\base\Behavior;
use yii\base\Controller;
use yii\base\Event;
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
    private $_transactionManager;

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

    private static $_attachedInstanceCount = 0;

    public function attach($owner) {
        parent::attach($owner);
        if ($owner instanceof Connection) {
            foreach ($owner->getBehaviors() as $behavior) {
                if ($behavior instanceof ConnectionBehavior) {
                    if (self::$_attachedInstanceCount == 0) {
                        $this->sinkApplicationListener(true);
                    }
                    self::$_attachedInstanceCount++;
                    return;
                }
            }
            throw new InvalidConfigException(
                "Connection must have arls\\xa\\ConnectionBehavior attached "
                . "to use arls\\xa\\TransactionPerRequestBehavior"
            );
        }
        if ($owner instanceof Application) {
            return;
        }
        throw new InvalidParamException("TransactionPerRequestBehavior can only be attached to an applications or connections");
    }

    public function detach() {
        parent::detach();
        self::$_attachedInstanceCount--;
        if (self::$_attachedInstanceCount == 0) {
            $this->sinkApplicationListener(false);
        }
    }

    protected function sinkApplicationListener($flag) {
        $method = ($flag ? 'attach' : 'detach') . 'Behavior';
        Yii::$app->$method('transactionPerRequest', self::class);
    }
}
