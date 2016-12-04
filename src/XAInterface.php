<?php


namespace arls\xa;


interface XAInterface {
    public function getId();

    public function commit();

    public function rollBack();
}
