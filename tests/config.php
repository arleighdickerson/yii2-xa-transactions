<?php
/**
 * Test Application Configuration
 */
return [
    'id' => 'xa-transactions-test',
    'basePath' => __DIR__,
    'components' => [
        'db' => [
            'class' => 'yii\db\Connection',
            'dsn' => 'mysql:host=127.0.0.1;dbname=xa_transactions_test',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8',
        ],
        'db2' => [
            'class' => 'yii\db\Connection',
            'dsn' => 'mysql:host=127.0.0.1;port=3337;dbname=xa_transactions_test',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8',
        ],
    ],
];
