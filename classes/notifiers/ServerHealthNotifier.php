<?php

class ServerHealthNotifier
{
    protected $_transferNode = null;
    
    public function __construct(TransferNodeInterface $transferNode) {
        $this->_transferNode = $transferNode;
    }
    
    public function checkForNotifications($k)
    {
        $periodInTicks = 800; //every 4000 (800*4) ticks we could message about online status
                
        if (($k%($periodInTicks))==0) { 
            $time = date("H");
            if (Utils::isHoursFromTo('07', '22') || $k <= $periodInTicks) {                
                $this->checkAllServersHealth();
            }    
        }           
    }
    
    public function checkAllServersHealth()
    {
        Log::log("checkAllServersHealth");
        $transferNode = $this->_transferNode;
        $servers =  $transferNode->getAllServers();
        
        foreach($servers as $ip => $srv){
            try {                
                $srv->connect();
                $transferNode->validateToServer($srv);
                $transferNode->checkDiskSpace($srv);
                $srv->disconnect();
            } catch (Exception $ex) {
                $msg = $ex->getMessage();
                Log::log("WARNING: Problem on the server '{$srv->name}' detected: $msg", 1);
            }
        }
        
        return true;
    }
    

}

