<?php

namespace arls\xa;
interface XABranchInterface extends XAInterface {
    const STATE_TERMINATED = 0;
    const STATE_ACTIVE = 1;
    const STATE_IDLE = 2;
    const STATE_PREPARED = 3;

    public function begin();

    public function end();

    public function prepare();

    public function getState();
}
