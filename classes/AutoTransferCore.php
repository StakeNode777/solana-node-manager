<?php

class AutoTransferCore
{
    protected $_validator = null;
    protected $_transferNode = null;
    protected $_deactivatorRunner = null;
    protected $_emulate_transfer = 0;
    
    //protected $_first_try_resolve_time = 0;
    //protected $_failed_try = 0;
    
    protected $_num_failed_ip_checks = 0;
    protected $_activeIP = '';
    
    protected $_locked = 0;
    protected $_deliqNum = 0;
    
    public function __construct(
        ValidatorInfoInterface $validator, 
        TransferNodeInterface $transferNode,
        DeactivatorRunner $deactivatorRunner,
        $checkers,
        $emulate_transfer
    ) 
    {
        $this->_validator = $validator;
        $this->_transferNode = $transferNode;
        $this->_deactivatorRunner = $deactivatorRunner;
        $this->_checkers = $checkers;
        $this->_emulate_transfer = $emulate_transfer;
    }
    
    public function run()
    {
        $emulation = $this->_emulate_transfer;
        $validator = $this->_validator;
        $transferNode = $this->_transferNode;
        
        $identity = $validator->getIdentity();
        
        //TODO remove it
        /*$servers = $transferNode->getAllServers();
        $mev_srv = $servers['193.34.212.64'];
        $mev_srv->connect();
        Log::log($mev_srv->exec('sleep 32'));
        $mev_srv->resetChannel(); 
        $mev_srv->connect();
        Log::log($mev_srv->exec('pwd'));              
        $mev_srv->connect();
        return;*/
        
        $ccNotifier = new CreditsChangeNotifier();
        $healthNotifier = new HealthSatmNotifier($emulation);
        $serverHealthNotifier = new ServerHealthNotifier($transferNode);
        
        $em_part = $emulation ? " in EMULATION MODE" : "";
        
        Log::log("started identity='{$identity}' $em_part", 1);

        //Log::log("activeIP = {$activeIP}");
        
        for($k=0;;$k++){   
            //TODO set it back to 12
            if (($k%6)==0) { //every 60 (12*5) ticks check active node IP
                $activeIP = $this->getActiveNodeIpWithCheck();
            }                 
            $creditsInfo = $validator->getCreditsInfo(); 
            //self::log(print_r($creditsInfo, 1));

            if (!$this->_locked) {
                $problem = false;                
                foreach($this->_checkers as $checker){
                    $problem = $this->tryToDetectProblemByChecker($checker, $activeIP, $creditsInfo);
                    if ($problem) {
                        break;
                    }
                }                
                if ($problem) {
                    if ($this->tryToSolveProblemAndSleep($problem, $activeIP, $emulation)) {                        
                        foreach($this->_checkers as $checker){
                            $checker->resetStats();
                        }
                        Log::log("Checkers resetted");
                    } 
                    $k = -1;
                }
            }

            $ccNotifier->checkForNotifications($creditsInfo);
            $healthNotifier->checkForNotifications($k, $creditsInfo, $activeIP, $this->_locked);
            $serverHealthNotifier->checkForNotifications($k);
       
            if ($k > 1000000) {
                $k = -1;
            }
            //Log::log("sleeping for 4 ticks");
            Utils::sleepInTicks(4);
        }        
    }
    
    public function tryToDetectProblemByChecker(CheckerInterface $checker, $activeIP, $creditsInfo)
    {
        try {
            $problem = $checker->detectProblem($activeIP, $creditsInfo);
        } catch (Exception $ex) {
            //TODO write significant message, но ограничить
            self::log($ex->getMessage(), 1);
            $problem = 'other_problem';
        } 
        return $problem;
    }
    
    public function tryToSolveProblemAndSleep($problem, $activeIP, $emulation)
    {
        $pausePeriodAfterResolve = 600;
        $solved = $this->tryToSolveProblem($problem, $activeIP, $emulation);
        
        if (!$solved) {
            $pausePeriodAfterResolve = 60;
            self::log("WARNING: Autotransfer failed", 1); 
            self::log("Sleep after failed resolving for a {$pausePeriodAfterResolve} ticks", 1);
        } else {
            self::log("Sleep after resolving for a {$pausePeriodAfterResolve} ticks", 1);
        }
        
        Utils::sleepInTicks($pausePeriodAfterResolve);  
        return $solved;
    }
    
    public function tryToSolveProblem($problem, $activeIP, $emulation)
    {
        $solved = false;
        $tryPeriods = [0, 0, 1, 5, 0]; //we shoul think that on IP detection will be spend about +2 sec        
        foreach($tryPeriods as $k => $sleepTime) {
            $channelProblem = 0;
            $tryNum = $k+1;
            Log::log("solveProblem try #{$tryNum} started");
            try {
                $solved = $this->solveProblem($problem, $activeIP, $emulation);  
            } catch (Exception $ex) {                
                Log::log($ex->getMessage(), 1);
                if (strpos($ex->getMessage(), 'Please close the channel')!==false) {
                    $channelProblem = 1;
                }
            }
            if ($solved) {
                break;
            }
            Log::log("solveProblem try #{$tryNum} failed. Sleep {$sleepTime}", 1);
            Utils::sleepInTicks($sleepTime);
            if ($channelProblem) {
                $this->solveChannelProblem();
            }
            
            try { 
                $newActiveIP = $this->_validator->getActiveNodeIP();
                if ($newActiveIP && $newActiveIP!=$activeIP) { //could be manually changed
                    break; //in the next cycle we check problem again
                }
            } catch (Exception $ex) {
                Log::log("tryToSolveProblem - IP detection problem: " . $ex->getMessage());
            }
        }  
        return $solved;
    }
    
    public function solveChannelProblem()
    {
        try {
            $servers = $this->_transferNode->getAllServers();
            foreach($servers as $srv){
                $srv->resetChannel();
            }
        } catch (Exception $ex) {
            Log::log("Some problems with 'solveChannelProblem' srv = {$srv->name()}: " . $ex->getMessage(), 1);
        }        
    }
    
    /*public function getPausePeriodAfterFailedResolve()
    {
        $time = time();
        $delta = $time - $this->_first_try_resolve_time;
        if (!$delta > 1800) {
            $this->_first_try_resolve_time = $time;
            $this->_failed_try = 0;
        }
        
        $failed_try = $this->_failed_try;
        $wait_map = [
            0 => 3,
            1 => 3,
            2 => 7,
            3 => 7,
            4 => 10,
            5 => 10,
            6 => 15,
        ];
        $this->_failed_try++;
        return isset($wait_map[$failed_try]) ? $wait_map[$failed_try] : 60;
    }*/
    
    public function getActiveNodeIpWithCheck()
    {
        $transferNode = $this->_transferNode;
        $activeIP = $this->_validator->getActiveNodeIP();
        $date = date("Y-m-d H:i:s");
        /*if ($date >= '2025-07-02 12:03:00' && $date <= "2025-07-02 12:07:00") {
            $activeIP = '';
        }*/
        //self::log("activeIP: $activeIP");
        try {
            if (!$activeIP) {
                $this->_num_failed_ip_checks++;
                self::log("WARNING: some problems with detection of active IP. Number of failed tries - {$this->_num_failed_ip_checks}", 1);
                if ($this->_num_failed_ip_checks >= 3) {
                    $activeIP = $this->findActiveNodeOnTheServers($this->_activeIP);
                    $this->_num_failed_ip_checks = 0;
                    $this->_activeIP = $activeIP;                    
                    self::log("WARNING: Active IP '{$activeIP}' detected by the 2nd method - on the server", 1);               
                } else {
                    $activeIP = $this->_activeIP;
                }            
            } else {
                $this->_num_failed_ip_checks = 0;
                $this->_activeIP = $activeIP;                
            }
            $transferNode->getServerByInfo($activeIP);
            if ($this->_locked) {
                self::log("was unlocked - problem with Active IP resolved '{$activeIP}'", 1);
                $this->_locked = 0;
            }
        } catch (TransferNodeException $ex) {
            if (!$this->_locked) {
                self::log("WARNING: Active IP '{$activeIP}' is not accessible. Autotransfer was locked", !$this->_locked);
                $this->_locked = 1;
            }             
        }
        return $activeIP;
    }
    
    public function findActiveNodeOnTheServers($activeIP)
    {
        $transferNode = $this->_transferNode;
        $servers =  $transferNode->getAllServers();
        
        if (!isset($servers[$activeIP])) {
            return '';
        }        
        
        try {
            $candidateIP = $activeIP;
            if ($transferNode->isServerActive($servers[$candidateIP])) {
                return $candidateIP;
            }
            
            foreach($servers as $ip => $srv) {
                $candidateIP = $ip;
                if ($transferNode->isServerActive($servers[$candidateIP])) {
                    return $candidateIP;
                }                
            }
            return '';
        } catch (TransferNodeException $ex) {
            self::log("WARNING: findActiveNodeOnTheServers - problem on checking '{$candidateIP}':" . $ex->getMessage(), 1);
            return '';
        }
    }
    
    public function solveProblem($problem, $activeIP, $emulation)
    {   
        $validator = $this->_validator;
        $transferNode = $this->_transferNode;
        $em_part = $emulation ? " EMULATION" : "";
       
        $activeServer = $transferNode->getServerByInfo($activeIP);
        $to_srv = self::detectToSrv($transferNode, $activeServer);
        
        if (!$to_srv) {
            self::log("WARNING: No good server for transfer", 1);
            return false;
        }
        
        //если 'connection_problem', 
        //который в независимом потоке пытается отключить его
        //и запускаем режим безопасной активации
        if ($problem=='connection_problem') {
            if (!$emulation) {
                $this->runDeactivator($activeServer);
                //запускаем активацию только без проверки, т.к. при отборе уже проверили в  detectToSrv
                $transferNode->checkAndActivate($to_srv, 'activate_only');                
            }
            self::log("ACTIVATION{$em_part} : run Deativator for {$activeServer->name} ({$activeServer->ip}), activate {$to_srv->name} ({$to_srv->ip})", 1);
            return true;
        }
        
        $transferNode->checkServerConfig($activeServer);

        //если к серверу получилось подключиться
        //запускаем трансфер только без проверки, т.к. при отборе уже проверили в  detectToSrv
        if (!$emulation) {
            $transferNode->checkAndTransfer($activeServer, $to_srv, 'transfer_only'); 
        }
        $msg = self::getTransferMsg($activeServer, $to_srv, $emulation);  
        self::log($msg, 1);
        return true;
    }    
    
    public static function getTransferMsg($activeServer, $to_srv, $emulation)
    {
        $em_part = $emulation ? " EMULATION" : "";
        return "TRANSFER{$em_part}: {$activeServer->name} -> {$to_srv->name} ({$activeServer->user}@{$activeServer->ip} -> {$to_srv->user}@{$to_srv->ip})";
    }
    
    public static function detectToSrv(TransferNodeInterface $transferNode, $activeServer)
    {
        $all_servers = $transferNode->getAllServers();
        foreach($all_servers as $srv){
            if ($srv->ip!=$activeServer->ip) {
                try{
                    $srv->disconnect();
                    $srv->connect();
                    $transferNode->validateToServer($srv);
                    return $srv;
                } catch (Exception $ex) {
                    self::log("WARNING: Seems there problems with server '{$srv->ip}'", 1);
                }                
            }
        }
        return false;
    }
    
    public function runDeactivator($activeServer)
    {
        //создать файл с кредентиалами для сервера, который при запуске деактиватора удаляется
        $this->_deactivatorRunner->run($activeServer, Log::getTgData());
    }
    
    public static function log($msg, $significant = 0)
    {
        Log::log($msg, $significant);
    }     
    
}

