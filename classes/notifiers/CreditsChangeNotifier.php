<?php

class CreditsChangeNotifier
{
    protected $_epoch = 0;
    protected $_lowest_delta200 = 0;
    
    protected $_credits_false_count = 0;
    protected $_credits_false_last_time = 0;
    
    protected $_impossible_delta200_count = 0;
    protected $_impossible_delta200_last_time = 0;
    protected $_num_credits_info_errors = 0;
    protected $_notify_about_num_credits_info_errors = 20;


    public function checkForNotifications($creditsInfo)
    {        
        $max_impossible_delta200_num_msg = 10;
        $impossible_delta200_msg_period = 600;
        $max_credits_false_num_msg = 20;
        $credits_false_msg_period = 120;       
        
        if (isset($creditsInfo['error']) && $creditsInfo['error']) {
            $this->_num_credits_info_errors++;
            if ($this->_num_credits_info_errors >= $this->_notify_about_num_credits_info_errors) {
                $this->_notify_about_num_credits_info_errors *= 2;                
                $msg = "WARNING: Number of creditsInfo errors reached {$this->_num_credits_info_errors}";
                self::log($msg, 1);
            }
            return;
        }
        
        if ($creditsInfo['credits']===false) {
            $period = time() - $this->_credits_false_last_time;
            $significant = 0;
            if ($this->_credits_false_count < $max_credits_false_num_msg
            && $period >= $credits_false_msg_period) {
                $significant = 1;
                $this->_credits_false_count++;
                $this->_credits_false_last_time = time();
            }            
            $msg = "WARNING: not in credits stats. Could be delinquent";            
            self::log($msg, $significant);
            return;
        }
        
        if ($creditsInfo['credits']===0 && $creditsInfo['credits200'] > 10000) {            
            $period = time() - $this->_impossible_delta200_last_time;
            $significant = 0;
            if ($this->_impossible_delta200_count < $max_impossible_delta200_num_msg
            && $period >= $impossible_delta200_msg_period) {
                $significant = 1;
                $this->_impossible_delta200_count++;
                $this->_impossible_delta200_last_time = time();                
            }
            
            $msg = "has impossible delta200: {$creditsInfo['delta200']}";
            self::log($msg, $significant);
            return;
        }
        
        if (!$this->_epoch || $this->_epoch!=$creditsInfo['epoch']) {
            $this->_epoch = $creditsInfo['epoch'];
            $this->_lowest_delta200 = $creditsInfo['delta200'];
            $this->_notified_for_delta200 = 0;
            $this->_credits_false_count = 0;
            $this->_impossible_delta200_count = 0;
        }
        
        $thresholds = [-100, -200, -300, -500, -1000, -1500, -2000, -3000, -6000];
        $next_threshold = false;
        foreach($thresholds as $th){
            if ($this->_notified_for_delta200 > $th) {
                $next_threshold = $th;
                break;
            }
        }      
        
        if ($this->_lowest_delta200 > $creditsInfo['delta200']) {
            $this->_lowest_delta200 = $creditsInfo['delta200'];
        }
        
        if ($next_threshold && $next_threshold >= $this->_lowest_delta200) {
            $this->_notified_for_delta200 = $this->_lowest_delta200;
            $msg = "WARNING: delta200 = {$creditsInfo['delta200']}";
            self::log($msg, 1);
        }
      
    }
    
    public static function log($msg, $significant)
    {
        Log::log($msg, $significant);
    }
}

