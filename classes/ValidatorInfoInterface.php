<?php

interface ValidatorInfoInterface
{
    const DSTATUS_OK = 1; //Deliquent status - OK - no deliquency
    const DSTATUS_DELIQUENT = -2; //Deliquent status - Deliquent
    const DSTATUS_UNDEFINED = -3; //Deliquent status - Undefined - can't understand  

    public function getDelinquentStatus();
    
    public function getActiveNodeIP();
    
    public function getIdentity();
    
    public function getCreditsInfo();
}

