<?php

class AutoTransferWrapper
{
    protected $_script_file = null;
    
    public function __construct($script_file)
    {
        $this->_script_file = $script_file;
    }
    
    public function run()
    {
        date_default_timezone_set('UTC');
        $params = [
            'c:' => 'config_dir:',
        ];

        $cli_options = getopt(implode('', array_keys($params)), $params);      
        $config_dir = isset($cli_options['config_dir']) ? $cli_options['config_dir'] : 'config';
       
        $config_file = "{$config_dir}/config.conf";
        $private_key_path = "{$config_dir}/validator-keypair.json";
        
        if (!file_exists($config_file)) {
            self::log("ERROR: Config file '$config_file' not found");
            return;            
        }
        
        Env::init($config_file);
        
        $name = Env::get('NAME', 'noname' . random_int(0, 10000));
        $emulate_transfer = Env::get('EMULATE_TRANSFER', 0);        
        
        $cluster = Env::get('CLUSTER');
        $pub_identity = Env::get('PUB_IDENTITY');
        $pub_vote = Env::get('PUB_VOTE');
        $transfer_lock_entity = Env::get('TRANSFER_LOCK_ENTITY', 'auto');                   
        
        
        $tg_token = Env::get('TG_TOKEN');
        $chat_id = Env::get('TG_CHAT_ID');
        Log::setTelegramBotConfig($tg_token, $chat_id, $name);
        
        try {
            CliTransferNodeHelper::validateIdentity($private_key_path, $pub_identity);
        } catch(TransferNodeException $ex) {
            $msg = $ex->getMessage();
            Log::log("Autotransfer was NOT STARTED: $msg.\n\n Please start again");
            return;
        }
        
        $servers = CliTransferNodeHelper::loadServersFromConfig();        
        
        $private_key = file_get_contents($private_key_path);
        
        $validator = new ValidatorInfo($cluster, $pub_identity, $pub_vote, $private_key, $transfer_lock_entity);      
        $transferNode = new TransferNode($cluster, $pub_identity, $pub_vote, $private_key, $transfer_lock_entity, $emulate_transfer);
        $transferNode->addServers($servers);
        
        $deactivatorRunner = new DeactivatorRunner($this->_script_file, Log::getTgData());
        
        $possible_checkers = [
            'SuspiciousCreditsDropChecker',
            'StrongCreditsDropChecker',
            'DelinquentChecker'
        ];
        
        $checkers_line = Env::get('CHECKERS', 'DelinquentChecker');
        $checker_names = explode(',', $checkers_line);
        $checkers = [];
        $good_checker_names = [];
        
        foreach($checker_names as $checker_name0){
            $checker_name = trim($checker_name0);
            if (!in_array($checker_name, $possible_checkers)) {
                self::log("impossible checker '{$checker_name}'");
                continue;
            }
            $good_checker_names[] = $checker_name;
            $checkers[] = new $checker_name($validator, $transferNode);
        }
        $enabled_checkers_str = implode(",", $good_checker_names);
        self::log("enabled checkers - {$enabled_checkers_str}", 1);
        
        /*$checkers = [
            new SuspiciousCreditsDropChecker($validator, $transferNode),
            new StrongCreditsDropChecker($validator, $transferNode),
            new DelinquentChecker($validator, $transferNode)
        ];*/        
        
        $core = new AutoTransferCore($validator, $transferNode, $deactivatorRunner, $checkers, $emulate_transfer);
        $core->run();
    }
    
    public static function log($msg, $significant = 0)
    {
        Log::log($msg, $significant);
    }    
}

