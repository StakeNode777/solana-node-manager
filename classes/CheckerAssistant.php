<?php

class CheckerAssistant
{
    protected $_activeIP = '';
    
    protected $_validator = null;
    protected $_transferNode = null;
    protected $_lastProblem = null;
    
    public function __construct(ValidatorInfoInterface $validator, TransferNodeInterface $transferNode) {
        $this->_validator = $validator;
        $this->_transferNode = $transferNode;
    }
    
    public function logActiveIP($activeIP)
    {
        if ($activeIP!=$this->_activeIP) { //поменялся активный сервер, поэтому считаем, что все хорошо
            $this->_activeIP = $activeIP;
        }         
    }
    
    public function isActiveIPChanged($activeIP)
    {
        if ($activeIP!=$this->_activeIP) { //поменялся активный сервер, поэтому считаем, что все хорошо
            $this->_activeIP = $activeIP;
            return true;
        }  
        $proposedActiveIP = $activeIP; 
        $activeIP = $this->_validator->getActiveNodeIP();
        if ($activeIP && $activeIP!=$proposedActiveIP) { //поменялся активный сервер, поэтому считаем, что все хорошо
            $this->_activeIP = $activeIP;
            return true;
        }         
        return false;
    }
    
    public function checkConnectionProblem($ip)
    {
        $info = [
            'problem' => false,
            'wereConnectionProblems' => 0,
            'desc' => ''
        ];
        
        $srv = $this->_transferNode->getServerByInfo($ip);
        
        //пытаемся подключиться к серверу 3 раза
        //если не получается, то ждем 7 сек и опять пытаемся. 
        //таймаут подключения - 5 сек - поэтому на попытку тратится 12 сек
        $wereConnectionProblems = 0;
        $isConnectionOk = 0;
        $num_bad_tries = 0;
        $sleep_time = 7;
        Log::log("Start checking connection...");
        for($i = 0; $i < 3; $i++){
            if (Utils::reconnectToServer($srv)) {
                $isConnectionOk = 1;
                break;
            }
            $wereConnectionProblems = 1;
            $num_bad_tries++;
            Utils::sleepInTicks($sleep_time);
            $sleep_time = 3;
            $try_num = $i+1;
            Log::log("Unsuccessful connection try #{$try_num}");
        }
        
        if (!$isConnectionOk) {
            $this->_lastProblem = "Can't connect to {$srv->name} ({$ip})";
            return 'connection_problem'; 
        } elseif ($wereConnectionProblems) {
            $this->_lastProblem = "Some problems with connection to {$srv->name} ({$ip}) were detected. "
            . "Number unsuccessful tries - {$num_bad_tries}";
            return 'connection_problem_partial';             
        } 
        
        return false;
    }
    
    public function isGoodIdentityAndSync($ip)
    {
        $srv = $this->_transferNode->getServerByInfo($ip); 
        //$service_options = TransferNode::getOptionsFromServiceFile($srv); //this method is not good for servers without solana.service
        
        $service_options = TransferNode::getOptionsFromValidatorProcess($srv);
        if (empty($service_options)) {
            $rows = TransferNode::getListOfAgaveValidatorProcesses($srv);            
            if (empty($rows)) {
                $this->_lastProblem = "Seems agave-validator process is not running";
                return false;
            } else {
                $this->_lastProblem = "agave-validator process is running, but no options detected";
                return false;
            }
        }
        $ledger = $service_options['ledger'][0];
        
        $max_tries = 3;
        $timeout = 0;
        for($i = 1; $i <= $max_tries; $i++) {
            $timeout += $i; //1, 3, 6 secs
            $cmd = "source ~/.profile; timeout {$timeout}s agave-validator -l {$ledger} monitor";
            $cmd_res = $srv->exec($cmd); 

            //echo "\n\n{$cmd_res}\n\n";
            
            //good case: 66:45:40 | Processed Slot: 313010153 | Confirmed Slot: 313010152 | Finalized 
            //bad case 1: 00:08:33 | health unknown | Processed Slot: 315222695 | Confirmed Slot: 315222695 | Finalized
            //bad case 2: 00:19:05 | 639 slots behind | Processed Slot: 315225479 | Confirmed Slot: 315225479 | Finalized
            if (preg_match('#(\| [^|]+)?\| Processed Slot: ([^|]+)#', $cmd_res, $matches)) {
                $problems_str = $matches[1];
                $slots_str = $matches[2];
                Log::log("detected in monitor: '{$matches[0]}'");                
                if (trim($problems_str)) {
                    if (preg_match('# (\d+) slots? behind \| Processed Slot: ([^|]+)#', $cmd_res, $matches)) {
                        $slots_behind = (int) $matches[1];
                        Log::log("slots_behind = {$matches[1]}");
                        if ($slots_behind < 10) {
                            Log::log("Detected '{$problems_str}' on ip = {$ip}, but it could be not a big problem", 1);
                            break;
                        }
                    }
                    $this->_lastProblem = "detected problems in monitor: '{$problems_str}'";
                    return false;
                }                
            }            

            if (preg_match('/^Identity: ([^\s]+)/m', $cmd_res, $matches)) {
                $monitor_identity = $matches[1]; // Захватываем значение Identity
                if ($this->_validator->getIdentity()!=$monitor_identity) {
                    $this->_lastProblem = "detected problems with identity in monitor: '{$monitor_identity}'";
                    return false;
                }
                break;
            } elseif (preg_match('/Validator startup/m', $cmd_res, $matches)) {
                $this->_lastProblem = "detected Validator startup...";
                return false;
            } else {
                if ($i >= $max_tries) {
                   $this->_lastProblem = "monitor doesn't work";
                    return false;
                }    
            }  
        } 
        
        return true;
    }

    public function getLastProblemDescription()
    {
        return $this->_lastProblem;
    }   
}

