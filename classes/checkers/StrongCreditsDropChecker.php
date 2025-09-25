<?php

class StrongCreditsDropChecker implements CheckerInterface
{
    protected $_creditsDropThreshold = -600;
    
    protected $_validator = null;
    protected $_transferNode = null;
    
    protected $_resetAt = 0;
    protected $_activeIP = null;   
    protected $_curEpoch = null;    
    protected $_startDelta = 0;
    protected $_curDelta = 0;
    
    protected $_problemDetectedInEpochs = [];
    protected $_flag_ttt = 0;
    
    public function __construct(ValidatorInfoInterface $validator, TransferNodeInterface $transferNode) {
        $this->_validator = $validator;
        $this->_transferNode = $transferNode;
        $this->_checkerAssistant = new CheckerAssistant($validator, $transferNode);
    }
    
    public function detectProblem($activeIP, $creditsInfo)
    {
        if ((isset($creditsInfo['error']) && $creditsInfo['error'])
        || $creditsInfo['credits']===false || $creditsInfo['credits']===0) {
            return false;
        }      
        
        $maxDrop = $this->findMaxDropFromTop200($creditsInfo, $activeIP);
        //Log::log("maxDrop = {$maxDrop} delta200 = {$creditsInfo['delta200']}");
        if ($maxDrop < $this->_creditsDropThreshold) {
            //если уже была попытка переноса для этой эпохи, то просто пишем об этом, но не переносим
            if (isset($this->_problemDetectedInEpochs[$creditsInfo['epoch']])) {
                $msg = "WARNING: Significant drop {$this->_startDelta} -> {$this->_curDelta}. Although the node could be already transfered by this problem in this epoch {$creditsInfo['epoch']}";
                Log::log($msg, 1);
                $this->resetStats();
                return false;
            }
            $this->_problemDetectedInEpochs[$creditsInfo['epoch']] = 1;
            return $this->researchPossibleProblem($activeIP);
        }

        //в самом конце, если не было проблем, то логируем IP
        $this->_checkerAssistant->logActiveIP($activeIP);
        
        return false;
    }
    
    public function resetStats()
    {
        $this->_resetAt = time();
        if (count($this->_problemDetectedInEpochs) > 1) {
            $key = array_key_last($this->_problemDetectedInEpochs);
            $this->_problemDetectedInEpochs = [];
            $this->_problemDetectedInEpochs[$key] = 1;
        }
        Log::log("StrongCreditsDropChecker - reset");
    }    
    
    public function findMaxDropFromTop200($creditsInfo, $activeIP)
    {
        if ($this->_resetAt) { //если сбросили статистику
            $diff = time() - $this->_resetAt;
            if ($diff < 60) { //we should wait about 1 min to see how changing of IP affected on credits
                return 0;
            }
            $this->_resetAt = 0;
            $this->_curEpoch = $creditsInfo['epoch'];
            $this->_activeIP = $activeIP;
            $this->_startDelta = $creditsInfo['delta200']; //начинаем считать заново все
            Log::log("StrongCreditsDropChecker init: startDelta = {$this->_startDelta}");
        } elseif ($this->_curEpoch!=$creditsInfo['epoch'] || $activeIP!=$this->_activeIP) { //если новая эпоха или поменялся сервер, то начинаем сначала
            Log::log("StrongCreditsDropChecker new epoch or acitveIP detected");
            $this->resetStats();
            return 0;
        }  
        $this->_curDelta = $creditsInfo['delta200'];
        /*if (date('Y-m-d H:i:s') > "2025-03-27 10:44:00") { //just for tests, should be commented
            $this->_curDelta = $this->_curDelta + 50;
        }*/
        /*if (!$this->_flag_ttt) { //just for tests, should be commented
            $this->_problemDetectedInEpochs[700] = 1;
            $this->_problemDetectedInEpochs[761] = 1;
            $this->_flag_ttt = 1;
        }*/
      
        //case 100 - 0 = 100 - no problem
        //case 100 - 10 = 90 - increase - no problem
        //case 100 - -50 = 150 - increase for 150 no problem
        //case -100 - -50 = -50 - drop for 50
        //case -100 - 50 = -150 - drop for 150
        $drop = $this->_curDelta - $this->_startDelta;
        
        if ($drop > 30) { //in case if we achieve new good top let's fix it
            $this->_startDelta = $this->_curDelta;
            Log::log("StrongCreditsDropChecker new top: startDelta = {$this->_startDelta}");
        }
        
        //Log::log("maxDrop = $drop startDelta = {$this->_startDelta} curDelta = {$this->_curDelta}");
        return $drop;
    }
    
    public function researchPossibleProblem($activeIP)
    {
        $checkerAssistant = $this->_checkerAssistant;
        $base_msg = "WARNING: Significant drop {$this->_startDelta} -> {$this->_curDelta}";
        
        $problem = $checkerAssistant->checkConnectionProblem($activeIP);        
        if ($problem) {
            $msg = "{$base_msg} - cause - " . $checkerAssistant->getLastProblemDescription();
            Log::log($msg, 1);
            return $problem;
        }
        
        Log::log($base_msg, 1);;
        return 'other_problem';
    }   
}

