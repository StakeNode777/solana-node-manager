<?php

class SuspiciousCreditsDropChecker implements CheckerInterface
{    
    protected $_validator = null;
    protected $_transferNode = null;
    protected $_checkerAssistant = null;
    
    protected $_resetAt = 0;
    protected $_activeIP = null;   
    protected $_curEpoch = null;
    protected $_lastCreditsInfos = [];
    
    public function __construct(ValidatorInfoInterface $validator, TransferNodeInterface $transferNode) {
        $this->_validator = $validator;
        $this->_transferNode = $transferNode;
        $this->_checkerAssistant = new CheckerAssistant($validator, $transferNode);
    }
    
    public function detectProblem($activeIP, $creditsInfo)
    {
        if (isset($creditsInfo['error']) && $creditsInfo['error']) {
            Log::log("creditsInfo error");
            return false;
        }
        
        $possibleProblemDetected = 0;
        if (!$creditsInfo['credits'] && $creditsInfo['credits200'] > 30000) {
            $possibleProblemDetected = 1;
        } else {            
            $maxDrop = $this->findMaxDropFromTheTop200ForTheLastTime($creditsInfo, $activeIP);
            if ($maxDrop < -50) {
                $possibleProblemDetected = 1;
            }
        }        
        
        if ($possibleProblemDetected) {
            self::log("SuspiciousCreditsDropChecker: possible problem detected {$maxDrop} for the last 2 min", 1);
            $problem = $this->researchPossibleProblem($activeIP); 
            
            if ($problem) {
                return $problem;             
            } else {
                self::log("SuspiciousCreditsDropChecker: seems server works well", 1); 
            }
        }
        
        return false;
    }
    
    public function resetStats()
    {
        $this->_resetAt = time();
        Log::log("SuspiciousCreditsDropChecker - reset");
    }     
    
    public function findMaxDropFromTheTop200ForTheLastTime($creditsInfo, $activeIP)
    {
        if ($this->_resetAt) {
            $diff = time() - $this->_resetAt;
            if ($diff < 60) { //we should wait about 1 min to see how changing of IP affected on credits
                return 0;
            }
            $this->_resetAt = 0;
            $this->_curEpoch = $creditsInfo['epoch'];
            $this->_activeIP = $activeIP;
            $this->_lastCreditsInfos = []; //начинаем считать заново все  
            Log::log("SuspiciousCreditsDropChecker init: delta200 = {$creditsInfo['delta200']}");
        } elseif ($this->_curEpoch!=$creditsInfo['epoch'] || $activeIP!=$this->_activeIP) { //новая эпоха или поменялся активный сервер, поэтому считаем, что все хорошо
            $this->resetStats();
            Log::log("SuspiciousCreditsDropChecker new epoch or acitveIP detected");
            return 0;
        }         
        
        $time = time();
       
        $min = 1000000000;
        foreach($this->_lastCreditsInfos as $key => $lci){
            $diff_time = $time - $lci['time'];
            if ($diff_time > 120) {
                unset($this->_lastCreditsInfos[$key]);
                continue;
            }
            //case 100 - -50 = 150 - increase for 150 no problem
            //case -100 - -50 = -50 - drop for 50
            //case -100 - 50 = -150 - drop for 150
            $drop = $creditsInfo['delta200'] - $lci['delta200'];
            if ($min > $drop) {
                $min = $drop;
            }
        }
        
        //Log::log(print_r($this->_lastCreditsInfos, 1));
        //Log::log("maxDrop = $min");
        
        $creditsInfo['time'] = $time;
        $this->_lastCreditsInfos[] = $creditsInfo;       
        return $min;
    }
    
    public function researchPossibleProblem($activeIP)
    {
        $old_activeIP = $activeIP;

        $activeIP = $this->_validator->getActiveNodeIP();
        
        if ($activeIP!=$old_activeIP) { //поменялся активный сервер, поэтому считаем, что все хорошо
            $this->resetStats();
            return false;
        }
        
        $checkerAssistant = $this->_checkerAssistant;
        $transferNode = $this->_transferNode;
        
        $activeServer = $transferNode->getServerByInfo($activeIP);
        
        $problem = $checkerAssistant->checkConnectionProblem($activeIP);
        if ($problem) {
            $msg = "SuspiciousCreditsDropChecker: " . $checkerAssistant->getLastProblemDescription();
            Log::log($msg, 1);
            return $problem;
        } 
        if (!$checkerAssistant->isGoodIdentityAndSync($activeIP)) {
            $msg = "SuspiciousCreditsDropChecker: " . $checkerAssistant->getLastProblemDescription();
            Log::log($msg, 1);
            return 'other_problem';
        }

        $this->resetStats();
        return false;
    }
    
    public static function log($msg, $significant = 0)
    {
        Log::log($msg, $significant);
    }   
}

