<?php


namespace arls\xa;

/**
 * Interface TransactionInterface
 * @package arls\xa
 *
 * An interface all arls\xa transaction classes will implement
 */
interface TransactionInterface {
    /**
     * @return string a globally unique transaction id
     */
    public function getId();

    /**
     * @return static
     * commits the transaction moving it into the terminated state
     */
    public function commit();

    /**
     * @return static
     * rolls back the transaction moving it into the terminated state
     */
    public function rollBack();
}
