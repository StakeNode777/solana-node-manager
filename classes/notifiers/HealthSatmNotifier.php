<?php

class HealthSatmNotifier
{
    protected $_em_part = null;
    protected $_activeIP = null;
    
    protected $_last_notified = 0;
    
    public function __construct($emulation) {
        $this->_em_part = $emulation ? "in EMULATION MODE" : "";
    }        
    
    public function checkForNotifications($k, $creditsInfo, $activeIP, $locked)
    {
        if ($this->_activeIP!=$activeIP) {
            $this->_activeIP = $activeIP;
            $msg = "New Active IP: $activeIP";
            Log::log($msg, 1);            
        }

        $show_msg = 0;
        $diff = time() - $this->_last_notified;        
        if ($diff > 4000) {
            if (
                Utils::isHoursFromTo('04', '05') 
                || Utils::isHoursFromTo('09', '10') 
                || Utils::isHoursFromTo('15', '16')
                || Utils::isHoursFromTo('20', '21')
                //|| Utils::isHoursFromTo('18', '19')
                || !$this->_last_notified
            ) {
                $show_msg = 1;
            }
        }
        
        if ($show_msg) {
            $msg = "is ONLINE {$this->_em_part}";
            if ($locked) {
                $msg .= ". ALTHOUGH IT LOCKED";
            }
            $msg .= "\n\ncredits: {$creditsInfo['credits']} delta200: {$creditsInfo['delta200']}";
            
            Log::log($msg, 1); 
            $this->_last_notified = time();            
        } elseif ($locked) {
            $periodInTicks = 500;
            if (($k%($periodInTicks))==0) { //every 2000 (500*4) ticks we could message about online status
                $show_msg = 1;
            }
        }
    }    
}

