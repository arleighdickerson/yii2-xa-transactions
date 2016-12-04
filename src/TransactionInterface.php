<?php


namespace arls\xa;


interface TransactionInterface {
    public function getId();

    public function commit();

    public function rollBack();
}
