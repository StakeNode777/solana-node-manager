<?php

//TODO implement TRANSFER_MODE, implement transfer tower file,

class TransferNode implements TransferNodeInterface
{
    const TRANSFER_LOCK_FILE = 'transfer.lock';
    
    protected $_cluster = null;
    protected $_pub_identity = null;
    protected $_pub_vote = null;
    protected $_private_key = null;
    protected $_private_key_path = null;
    protected $_emulate_transfer = 0;
    protected $_disk_warning_percent = null;
    
    protected $_transfer_lock_entity = null;
    
    protected $_servers = [];
    protected $_server_name_idx = [];
    
    public function __construct($cluster, $pub_identity, $pub_vote, $private_key, $transfer_lock_entity, $emulate_transfer, $disk_warning_percent = 94)
    {      
        date_default_timezone_set('UTC');
        $this->_cluster = $cluster;
        $this->_pub_identity = $pub_identity;
        $this->_pub_vote = $pub_vote;
        $this->_private_key = $private_key;
        $this->_transfer_lock_entity = $transfer_lock_entity;
        $this->_emulate_transfer = $emulate_transfer;
        $this->_disk_warning_percent = $disk_warning_percent;
        
        if ($this->_emulate_transfer) {
            self::log("Emulate transfer mode");
        }
        
        self::log("Cluster = '{$cluster}'");
        self::log("Identity = '{$pub_identity}'");
        self::log("Vote = '{$pub_vote}'");   
        self::log("transfer_lock_entity = {$transfer_lock_entity}");
    }
    
    public function addServer($name, $ip, $user, $password)
    {        
        if (isset($this->_servers[$ip])) {
            throw new TransferNodeException("You can't add the server with same IP twice - IP: {$ip}");
        }
        
        $server = new NodeServer($name, $ip, $user, $password);
        
        $this->_servers[$ip] = $server;
        if ($name) {
            $this->_server_name_idx[$name] = $ip; 
        }
    }
    
    public function addServers($servers)
    {
        foreach($servers as $server) {
            $name = isset($server['name']) ? $server['name'] : null;
            $ip = isset($server['ip']) ? $server['ip'] : null;
            $user = isset($server['user']) ? $server['user'] : null;
            $password = isset($server['password']) ? $server['password'] : null;
            
            $this->addServer($name, $ip, $user, $password);
        }
    }
    
    public function checkAndActivate($to_srv, $transfer_mode)
    {
        $to_srv = $this->getServerByInfo($to_srv);
        $to_srv->setStatusLabel('TO_SRV'); 
        
        $this->validateConfig($transfer_mode);     
        if ($this->_emulate_transfer) { //emulate activation            
            self::log("EMULATION OF ACTIVATION COMPLETED");
            return;
        }          
        $this->lockAllServers(false, $to_srv, $transfer_mode); 
        $this->checkBeforeTransfer(false, $to_srv, $transfer_mode);
        $this->doTransfer(false, $to_srv, $transfer_mode);
    }
    
    public function checkAndTransfer($from_srv, $to_srv, $transfer_mode)
    {
        $from_srv = $this->getServerByInfo($from_srv);
        $from_srv->setStatusLabel('FROM_SRV');
        $to_srv = $this->getServerByInfo($to_srv);
        $to_srv->setStatusLabel('TO_SRV');   
        
        $this->validateConfig($transfer_mode);       
        if ($this->_emulate_transfer) { //emulate transfer            
            self::log("EMULATION OF TRANSFER COMPLETED");
            return;
        }      
        $this->lockAllServers($from_srv, $to_srv, $transfer_mode);        
        $this->checkBeforeTransfer($from_srv, $to_srv, $transfer_mode);
        $this->doTransfer($from_srv, $to_srv, $transfer_mode);
    }
    
    public function validateConfig($transfer_mode)
    {
        self::log("Checking config");
        $possible_modes = [
            'safe', //validates both servers before transfer, then transfer
            'safe_activate', //validate only $to_srv and only activate there
            'transfer_only', //only transfer without validation
            'activate_only', //only activation without validation
        ];
        
        if(!in_array($transfer_mode, $possible_modes)){
            $possible_modes_str = implode(', ', $possible_modes);
            throw new TransferNodeException("Wrong transfer mode - '$transfer_mode'. Please select one from possible: $possible_modes_str");
        }
        if (!$this->_pub_vote) {
            throw new TransferNodeException("Please specify pub_vote option in config file!");
        }        
        if (!$this->_pub_identity) {
            throw new TransferNodeException("Please specify pub_identty option in config file!");
        }            
    }
    
    public function checkBeforeTransfer($from_srv, $to_srv, $transfer_mode)
    {    
        self::log("Validation started");
        
        if ($transfer_mode=='safe_activate') {
            $this->validateToServer($to_srv);
        } elseif ($transfer_mode=='safe') { //'safe' mode
            $this->validateFromServer($from_srv);   
            $this->validateToServer($to_srv);            
        } else {
            self::log("no validation for this mode '{$transfer_mode}'");
        }

        self::log("Validation finished");
    }
    
    public function lockAllServers($from_srv, $to_srv, $transfer_mode)
    {        
        self::log("Start locking servers");;
        $transfer_modes = ['safe', 'transfer_only'];
        $is_transfer_mode = in_array($transfer_mode, $transfer_modes);
        $locked_srvs = [];
        foreach($this->_servers as $srv){
            try {
                $connected = $srv->connect();
            } catch(phpseclib3\Exception\UnableToConnectException $ec) {
                $connected = false;
            }
            if ($connected) {
                if ($this->tryToLockConnectedServer($srv)) {
                    $locked_srvs[] = $srv;
                } else {
                    $this->unlockLockedServers($locked_srvs);
                    throw new TransferNodeException("Can't lock all servers");
                }
            } elseif (
                $srv->ip==$to_srv->ip
                || ($is_transfer_mode && $srv->ip==$from_srv->ip)
            ) {
                $msg = "Can't connect to {$srv->status_label}. IP: {$srv->ip}";
                throw new TransferNodeException($msg);
            } else {
                $msg = "WARNING: Can't connect to the server {$srv->name}. IP: {$srv->ip}";
                self::log($msg);
            }
        }
    }
    
    public function tryToLockConnectedServer($srv)
    {
        $date_format = 'Y-m-d H:i:s';
        $transfer_lock_file = self::TRANSFER_LOCK_FILE;
        $res = $srv->exec("cat {$transfer_lock_file}");
        if (trim($res)) {
            $parts = explode(',',$res);
            $num_parts = count($parts);
            if ($num_parts==3) {
                $entity = $parts[0];
                $start_date = date($date_format, strtotime($parts[1]));
                $end_date = date($date_format, strtotime($parts[2]));
                $cur_date = date($date_format);
                if (
                    $entity!=$this->_transfer_lock_entity 
                    && $cur_date >= $start_date
                    && $cur_date <= $end_date
                ) {
                    self::log("WARNING: Can't take lock for the server {$srv->name}. IP: {$srv->ip}");
                    return false;
                }
            } else {
                $msg = "WARNING: Wrong format of transfer.lock - ignoring it on the server {$srv->name}. IP: {$srv->ip}";
                self::log($msg);
            }
        }
        $start_date = date($date_format);
        $end_date = date($date_format, strtotime("+10 minutes"));        
        $cmd = "echo '{$this->_transfer_lock_entity},{$start_date},{$end_date}' > {$transfer_lock_file}";
        $srv->exec($cmd);
        self::log("{$srv->name} was locked. IP: {$srv->ip}");
        return true;
    } 
    
    public function unlockLockedServers($locked_srvs)
    {
        $transfer_lock_file = self::TRANSFER_LOCK_FILE;
        foreach($locked_srvs as $srv){
            $srv->exec("rm {$transfer_lock_file}");
            self::log("{$srv->name} was unlocked. IP: {$srv->ip}");
        }
    }
    
    public function validateFromServer(NodeServer $from_srv)
    {        
        $service_options = $this->validateServerCommon($from_srv);
        $ledger = $service_options['ledger'][0];
        
        $max_tries = 3;
        $timeout = 0;
        for($i = 1; $i <= $max_tries; $i++) {
            Log::log("Validating using monitor try #{$i}");
            $timeout += $i; //1, 3, 6 secs
            $cmd = "source ~/.profile; timeout {$timeout}s agave-validator -l {$ledger} monitor";
            $cmd_res = $from_srv->exec($cmd);

            //echo "\n\n{$cmd_res}\n\n";

            if (preg_match('/^Identity: ([^\s]+)/m', $cmd_res, $matches)) {
                $identity = $matches[1]; // Захватываем значение Identity
                if ($this->_pub_identity!=$identity) {
                    throw new TransferNodeException("Identity mismatch config and {$from_srv->status_label}. IP: {$from_srv->ip}, '{$this->_pub_identity}' vs '{$identity}'");
                }
                break;
            } elseif (preg_match('/Validator startup/m', $cmd_res, $matches)) {
                throw new TransferNodeException("Detected Validator startup");
                return false;                
            } else {
                if ($i >= $max_tries) {
                    throw new TransferNodeException("Identity not detected from monitor - 'agave-validator -l {$ledger} monitor'");
                }    
            }  
        }
    }
    
    public function isServerActive(NodeServer $srv)
    {        
        $service_options = $this->validateServerCommon($srv);
        $ledger = $service_options['ledger'][0];
        
        $max_tries = 3;
        $timeout = 0;
        for($i = 1; $i <= $max_tries; $i++) {
            Log::log("Validating using monitor try #{$i}");
            $timeout += $i; //1, 3, 6 secs
            $cmd = "source ~/.profile; timeout {$timeout}s agave-validator -l {$ledger} monitor";
            $cmd_res = $srv->exec($cmd);

            //echo "\n\n{$cmd_res}\n\n";

            if (preg_match('/^Identity: ([^\s]+)/m', $cmd_res, $matches)) {
                $identity = $matches[1]; // Захватываем значение Identity
                if ($this->_pub_identity!=$identity) {
                    return false;
                }
                return true;
            } elseif (preg_match('/Validator startup/m', $cmd_res, $matches)) {
                return false;                
            } else {
                if ($i >= $max_tries) {
                    throw new TransferNodeException("Identity not detected from monitor - 'agave-validator -l {$ledger} monitor'");
                }    
            }  
        }
    }    
    
    public function validateToServer(NodeServer $to_srv)
    {
        //commented method is used in validateSyncBySolanaMonitor too
        //$this->validateServerCommon($to_srv);         
        
        if (!$this->validateSyncBySolanaMonitor($to_srv)) {
            self::log("WARNING: '{$to_srv->name}' has problems with validation by monitor. Trying by catchup...");
            if (!$this->validateSyncByCatchup($to_srv)) {
                self::log("Try commands to see server sync:");
                self::log("solana catchup --our-localhost --follow --log");
                self::log("or");
                self::log("agave-validator -l <ledger> monitor");      
                $msg = "Seems server {$to_srv->name} is not synced";
                throw new TransferNodeException($msg);
            }
        }        
    }   
   
    public function validateSyncBySolanaMonitor(NodeServer $srv)
    {
        $service_options = $this->validateServerCommon($srv);
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
                self::log("detected in monitor: '{$matches[0]}'");
                if (trim($problems_str)) {
                    self::log("detected problems in monitor: '{$problems_str}'");
                    return false;
                }                
                return true;
            }
        }
        return false;
    }
    
    public function validateSyncByCatchup(NodeServer $srv, $start_timeout = 5, $delta_timeout = 7, $max_tries = 2)
    {
        $timeout = $start_timeout;
        for($i = 1; $i <= $max_tries; $i++) {
            $timeout += $delta_timeout; //5, 12 secs by the def values of func
            $cmd = "source ~/.profile; timeout {$timeout}s solana catchup --our-localhost";
            $cmd_res = $srv->exec($cmd);
            //echo "\n\n{$cmd_res}\n\n";

            if (preg_match('#us:(\d+) them:(\d+)#', $cmd_res, $matches)) {
                $us = intval($matches[1]); 
                $them = intval($matches[2]);
                //echo "us = $us them = $them\n";
                $delta = $us - $them;
                if ($delta < -10) {
                    self::log("Bad catchup on {$srv->status_label}. IP: {$srv->ip}: \n\n$cmd_res");
                    return false;
                }
                self::log("Good catchup");                
                return true;
            } 
        } 
        self::log("Not possible to catchup on {$srv->status_label}. IP: {$srv->ip}: \n\n{$cmd_res}");
        return false;
    }
    
    /**
     * DEPRECATED - use checkServerProcessAndUser() instead
     * This method is not good for servers without solana.service
     * Very important method.
     * If the user running the active node changes and we are not aware of it,
     * both the active and the backup servers may crash simultaneously.
     *
     * Example scenario:
     * The node was originally started under the 'sol' user, but later it was
     * launched under 'root' on the same server. Access for 'sol' still exists.
     * During a failover check performed as user 'sol', it will appear as not running,
     * then it will unsuccessfully try to deactivate and successfully activate again.
     * As a result, two nodes will be running at the same time, which will immediately crash.
     *
     * This function checks the symlink /etc/systemd/system/solana.service
     * to ensure it points to the solana.service file located in the user's home directory.
     */
    public function checkServerConfig(NodeServerInterface $srv)
    {       
        $link_to = trim($srv->exec("readlink /etc/systemd/system/solana.service"));
        $home_dir = trim($srv->exec("echo \$HOME"));
        $should_be_link_to = "{$home_dir}/solana.service";
        //echo "'{$link_to}' vs '{$should_be_link_to}'\n";
        if ($should_be_link_to!=$link_to) {
            $msg = "Server {$srv->name} ({$srv->ip}) has problem with configuration: "
            . "/etc/systemd/system/solana.service -> {$link_to} . "
            . "Although link should direct to: $should_be_link_to . "
            . "Please provide proper credentials to the server!!!";
            throw new TransferNodeException($msg);
        }
    } 
    
    public function checkServerProcessAndUser(NodeServerInterface $srv)
    {
        $msg_start = "Server {$srv->name} ({$srv->ip}) has a problem:";
        
        $rows = self::getListOfAgaveValidatorProcesses($srv);
        if (empty($rows)) {
            $msg = "$msg_start agave-validator is not running";
            throw new TransferNodeException($msg);
        }
        $num = count($rows);
        if ($num > 1) {
            $msg = "$msg_start more than one agave-validator process is running. Only a single instance should run";
            throw new TransferNodeException($msg);
        }
        
        $user = trim($srv->exec("echo \$USER"));
        
        foreach($rows as $row){
            if ($user!=$row['user']) {
                $msg = "$msg_start the agave-validator process is running under a different user ({$row['user']}), but it should be {$user}.";
                throw new TransferNodeException($msg);
            }
            break;
        }
    }
    
    public static function getListOfAgaveValidatorProcesses(NodeServerInterface $srv) {        
        $cmd = "ps -eo user,pid,cmd | grep agave-validator | grep -v grep";
        $output = $srv->exec($cmd);
        $lines = explode("\n", $output);

        if (empty($lines)) {
            return [];
        }
        
        $rows = [];

        foreach ($lines as $line) {
            if (preg_match('/^(\S+)\s+\d+\s+(.*)$/', $line, $matches)) {
                $user = $matches[1];
                $process  = $matches[2];
                $rows[] = ['process' => $process, 'user' => $user];
            }
        }

        return $rows;
    }
    
    public function validateServerCommon(NodeServer $srv)
    {        
        $this->tryToConnect($srv);
        
        //$this->checkServerConfig($srv); //this method is not good for servers without solana.service
        $this->checkServerProcessAndUser($srv);
        
        //$service_options = self::getOptionsFromServiceFile($srv); //this method is not good for servers without solana.service
        $service_options = self::getOptionsFromValidatorProcess($srv);

        if (!isset($service_options['ledger'])) {
            throw new TransferNodeException("--ledger option not detected in solana.service - {$srv->status_label}. IP: {$srv->ip}");
        }
        if (!isset($service_options['vote-account'])) {
            throw new TransferNodeException("--vote-account option not detected in solana.service - {$srv->status_label}. IP: {$srv->ip}");
        }        
        
        //$ledger = $service_options['ledger'][0];
        //print_r($service_options);
        $server_vote_account = $service_options['vote-account'][0];
        
        if ($this->_pub_vote!=$server_vote_account) {
            throw new TransferNodeException("vote account mismatch config and {$srv->status_label}. IP: {$srv->ip}, '{$this->_pub_vote}' vs '{$server_vote_account}'");
        }       
        
        return $service_options;
    }
    
    public function tryToConnect(NodeServer $srv)
    {
        if (!$srv->connect()) {
            throw new TransferNodeException("Failed to connect to the server - {$srv->status_label}. IP: {$srv->ip}");
        }         
    }
    
    public static function getOptionsFromValidatorProcess(NodeServerInterface $srv)
    {
        $rows = self::getListOfAgaveValidatorProcesses($srv);
        $process = $rows[0]['process'];
       
        preg_match_all('/--([a-zA-Z0-9\-]+)\s+(\S+)/', $process, $matches, PREG_SET_ORDER);

        $options = [];
        foreach ($matches as $match) {
            $key = $match[1];
            $value = $match[2];
            $options[$key][] = $value;
        }        
        
        return $options; 
    }
    
    /**
     * DEPRECATED - use getOptionsFromValidatorProcess() instead
     * This method is not good for servers without solana.service
     * return options from solana.service
     * @param NodeServerInterface $srv
     * @return type
     * @throws TransferNodeException
     */
    public static function getOptionsFromServiceFile(NodeServerInterface $srv)
    {
        $solana_service_path = "~/solana.service";
        if (!self::isFileExists($srv, $solana_service_path)) {
            throw new TransferNodeException("solana.service file not found - {$srv->status_label}. IP: {$srv->ip}");
        }
        
        $cmd = "cat {$solana_service_path}";
        $sf_string = $srv->exec($cmd);
        
        return self::getOptionsFromServiceFileString($sf_string);
    }
    
    public static function getOptionsFromServiceFileString($sf_string)
    {
        preg_match_all('/--([a-z0-9\-]+) +([^\s\\\\]+)/i', $sf_string, $matches, PREG_SET_ORDER);

        // Ассоциативный массив для хранения опций
        $options = [];

        foreach ($matches as $match) {
            $key = $match[1];
            $value = $match[2];
            $options[$key][] = $value;
        }        
        
        return $options;        
    }
    
    public static function isFileExists(NodeServerInterface $srv, $path)
    {  
        $cmd = "[ -f \"$path\" ] && echo 'false' || echo 'true'";
        return boolval($srv->exec($cmd));
    }
    
    public function doTransfer($from_srv, $to_srv, $transfer_mode)
    {
        $this->doTransferConservative($from_srv, $to_srv, $transfer_mode);
        //$this->doTransferF1($from_srv, $to_srv, $transfer_mode);
    }
    
    public function doTransferConservative($from_srv, $to_srv, $transfer_mode)
    {
        //self::log("Emulate transfer"); return; //should be commented
        
        $private_key = $this->_private_key;
        $to_srv = $this->getServerByInfo($to_srv);
        $to_srv->setStatusLabel('TO_SRV');   
        $to_so = self::getOptionsFromValidatorProcess($to_srv);
        
        $to_ledger = isset($to_so['ledger'][0]) ? $to_so['ledger'][0] : '~/ledger';        
        $to_tower_dir = isset($to_so['tower'][0]) ? $to_so['tower'][0] : $to_ledger; 
        
        if ($transfer_mode=='safe' || $transfer_mode=='transfer_only') {
            self::log("Starting Transfer...\n");
            $from_srv = $this->getServerByInfo($from_srv);
            $from_srv->setStatusLabel('FROM_SRV');       
            $from_so = self::getOptionsFromValidatorProcess($from_srv);
            $from_ledger = isset($from_so['ledger'][0]) ? $from_so['ledger'][0] : '~/ledger';

            //old server   
            self::log("deactivation started");
            $cmd = "source ~/.profile; "
                    . "agave-validator -l {$from_ledger} set-identity ~/unstaked-identity.json; "
                    . "agave-validator -l {$from_ledger} authorized-voter remove-all";

            $cmd_res = $from_srv->exec($cmd); 
            self::log("deactivation result:\n{$cmd_res}");
            //usleep(300000);
            //echo "emulate:\n {$cmd}\n";

            //TODO lock tower file, transfer tower file       
        } else {
            self::log("Starting Activation...\n");
        }        
   
        self::log("activation started");
        $cmd = "source ~/.profile; "
                . "rm {$to_tower_dir}/tower-1_9-*.bin; "
                . "echo '{$private_key}' | agave-validator -l {$to_ledger} set-identity; "
                . "echo '{$private_key}' | agave-validator -l {$to_ledger} authorized-voter add; ";
        $cmd_res = $to_srv->exec($cmd); 
        //echo "emulate:\n {$cmd}\n";
        self::log("activation result:\n{$cmd_res}");
        self::log("Transfer finished");        
    } 
    
    public static function deactivate($srv)
    {
        $from_so = self::getOptionsFromValidatorProcess($srv);
        
        $from_ledger = isset($from_so['ledger'][0]) ? $from_so['ledger'][0] : '~/ledger';
       
        $cmd = "source ~/.profile; "
            . "agave-validator -l {$from_ledger} set-identity ~/unstaked-identity.json; "
            . "agave-validator -l {$from_ledger} authorized-voter remove-all";
        return $srv->exec($cmd);         
    }
    
    public function doTransferF1($from_srv, $to_srv, $transfer_mode)
    {
        //self::log("Emulate transfer"); return; //should be commented
        $private_key = $this->_private_key;
        $to_srv = $this->getServerByInfo($to_srv);
        $to_srv->setStatusLabel('TO_SRV');   
        $to_so = self::getOptionsFromValidatorProcess($to_srv);
        
        $to_ledger = isset($to_so['ledger'][0]) ? $to_so['ledger'][0] : '~/ledger';        
        $to_tower_dir = isset($to_so['tower'][0]) ? $to_so['tower'][0] : $to_ledger;  
        
        self::log("Preparing for transfer/activation on to_srv");
        
        $cmd = "source ~/.profile; "
                . "rm {$to_tower_dir}/tower-1_9-*.bin; "
                . "echo '{$private_key}' | agave-validator -l {$to_ledger} authorized-voter add; ";
        $cmd_res = $to_srv->exec($cmd);         
        
        if ($transfer_mode=='safe' || $transfer_mode=='transfer_only') {
            self::log("Starting Transfer...\n");
            $from_srv = $this->getServerByInfo($from_srv);
            $from_srv->setStatusLabel('FROM_SRV');                     
            $from_so = self::getOptionsFromValidatorProcess($from_srv);
            $from_ledger = isset($from_so['ledger'][0]) ? $from_so['ledger'][0] : '~/ledger';

            //old server        
            $cmd = "source ~/.profile; "
                    . "agave-validator -l {$from_ledger} set-identity ~/unstaked-identity.json; ";

            //if we run this command:
            //agave-validator -l /mnt/ledger wait-for-restart-window --min-idle-time 2 --skip-new-snapshot-check
            //then solana restart!!! Be careful!
                    
            $cmd_res = $from_srv->exec($cmd); 
            self::log("from_srv deactivated");
            //echo "emulate:\n {$cmd}\n";

            //TODO lock tower file, transfer tower file       
        } else {
            self::log("Starting Activation...\n");
        }        
       
        $cmd = "source ~/.profile; "
                . "echo '{$private_key}' | agave-validator -l {$to_ledger} set-identity; ";
        $cmd_res = $to_srv->exec($cmd);
        self::log("to_srv activated");

        $cmd = "source ~/.profile; "
            . "agave-validator -l {$from_ledger} authorized-voter remove-all";        
        $cmd_res = $from_srv->exec($cmd);
        self::log("from_srv removed authorized-voter");
        //echo "emulate:\n {$cmd}\n";
        self::log("Transfer finished");        
    }   
    /**
     * tries to find server by name or ip
     */
    public function getServerByInfo($srv_info)
    {
        //it could be server data already
        if ($srv_info instanceof NodeServerInterface) {
            return $srv_info;
        }
        
        $ip = $srv_info;
        //try to detect IP by the server name
        if (isset($this->_server_name_idx[$srv_info])) {
            $ip = $this->_server_name_idx[$srv_info];
        }        
        
        if (isset($this->_servers[$ip])) {
            return $this->_servers[$ip];
        }
        
        throw new TransferNodeException("Can't detect server by '{$srv_info}'");        
    }
    
    public function getAllServers()
    {
        return $this->_servers;
    }
    
    public function checkDiskSpace(NodeServer $srv)
    {
        $diskSpaceAnalyzer = new DiskSpaceAnalyzer($srv, $this->_disk_warning_percent);
        if ($diskSpaceAnalyzer->areFullDisks()) {
            throw new TransferNodeException("Server has almost full disks");
        }
    }

    public static function log($msg)
    {
        Log::log($msg);
    }
}

