Yii2 XA Transactions
===================
Useful for composing multiple operations on multiple database connections into a single transactional unit.
Requires MySQL, see https://dev.mysql.com/doc/refman/5.6/en/xa.html

Installation
===================

Edit your composer.json file
```JSON
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/arleighdickerson/yii2-xa-transactions"
        }
    ],
    "require": {
        "arls/xa": "dev-master"
    }
}
```
and do a composer update.

Usage
===================
Attach ConnectionBehavior to a Connection instance. Here, we'll use the DI Container to attach it to all connection instances running MySQL
```PHP
//in the bootstrap
use yii\db\Connection;
Yii::$container->set(Connection::class, function ($container, $params, $config) {
    $conn = new Connection($config);
    if ($conn->getDriverName() == 'mysql') {
        $conn->attachBehavior('xa', 'arls\xa\ConnectionBehavior');
    }
    return $conn;
});
```
Now, we can do
```PHP
Yii::$app->db->xa;
```
To get our xa operations. 

To start a transaction, do
```PHP
Yii::$app->db->xa->beginTransaction();
```
and to get reference to the active transaction (branch) for that connection, do 
```PHP
Yii::$app->db->xa->transaction;
```
Here, we can go through the transaction lifecycle on multiple connections
```PHP
/** @var yii\db\Connection[] $connection */
foreach ($connections as $db) {
    $tx = $db->xa->beginTransaction();
    //do some database operations
    //insert, update, delete, it's all ok
    //provided you don't cause an implicit commit
    //or start a regular transaction.
    //XA transactions and regular transactions
    //are mutually exclusive
    $tx->end();
}
//two stage commit:
try {
    //stage 1: we prepare the transactions
    foreach ($connections as $db) {
        $db->xa->transaction->prepare();
    }
} catch (\Exception $e) {
    //stage 1 failed, we roll back the transactions
    foreach ($connections as $db) {
        $db->xa->transaction->rollback();
    }
    throw $e;
}
//stage 1 succeeded, we commit the transactions
foreach ($connections as $db) {
    $db->xa->transaction->commit();
}
```
However, managing transaction branches by hand like this is tedious and error prone. We can configure the application to use a transaction manager to manage one global transaction per request. Using Yii's event hooks we can also make the process transparent to client code.
```PHP
use yii\db\Connection;
use yii\base\Event;
use yii\base\Controller;

//begin an XA Transaction when a connection is opened
Event::on(Connection::class, Connection::EVENT_AFTER_OPEN, function (Event $event) {
    /** @var Connection $db */
    $db = $event->sender;
    if ($db->hasProperty('xa')) {
        $db->xa->beginTransaction();
    }
});

//commit all XA transactions after the action executes
Yii::$app->on(Controller::EVENT_AFTER_ACTION, function () {
    $transactionManager = Yii::$container->get('arls\xa\TransactionManager');
    //prepare and commit all running XA transactions
    //if an exception is triggered, all running XA transactions will be rolled back
    //therefore, all transactions succeed or all transactions fail
    //all within the context of one global transaction
    $transactionManager->commit(); 
});

```

Tests
===================

to run tests:

```bash
#!/bin/bash
cp vagrant/config/vagrant-local.example.yml vagrant/config/vagrant-local.yml
```
Update vagrant/config/vagrant-local.yml with your personal github token

```bash
#!/bin/bash
vagrant up
vagrant ssh
cd /app
./vendor/bin/phpunit
```
