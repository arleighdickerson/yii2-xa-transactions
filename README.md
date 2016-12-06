Yii2 XA Transactions
===================
Useful for composing multiple operations on multiple database connections into a single transactional unit.
Requires MySQL, see https://dev.mysql.com/doc/refman/5.6/en/xa.html

Usage
===================
Attach ConnectionBehavior to a Connection instance. Here, we'll use the DI Container to attach it to all connection instances running MySQL
```PHP
//in the bootstrap
Yii::$container->set('yii\db\Connection', function ($container, $params, $config) {
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
However, managing transaction branches by hand like this is tedious and error prone. We can configure the application to use a transaction manager to manage one global transaction per request and via Yii's component event hooks we can make the process transparent to client code.
```PHP
use yii\db\Connection;
use yii\base\Event;
use yii\base\Controller;

Event::on(Connection::class, Connection::EVENT_AFTER_OPEN, function (Event $event) {
    /** @var Connection $db */
    $db = $event->sender;
    if ($db->hasProperty('xa')) {
        $db->xa->beginTransaction();
    }
});

Yii::$app->on('afterAction', function () {
    $transactionManager = Yii::$container->get('arls\xa\TransactionManager');
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
