<?php

namespace arls\xa;
/**
 * Interface BranchInterface
 * @package arls\xa
 *
 * Interface for "branch" transactions (child transactions of the global transaction)
 */
interface BranchInterface extends TransactionInterface {
    const STATE_TERMINATED = 0;
    const STATE_ACTIVE = 1;
    const STATE_IDLE = 2;
    const STATE_PREPARED = 3;

    /**
     * @return static
     * begins the transaction, moving it into the active state
     */
    public function begin();

    /**
     * @return static
     * ends the transaction, moving it into the idle state
     */
    public function end();

    /**
     * @return static
     * prepares the transaction, moving it into the prepared state
     */
    public function prepare();

    /**
     * @return int|null the state of the transaction,
     * null if the transaction has not been started
     */
    public function getState();
}
