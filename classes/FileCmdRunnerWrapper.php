<?php

class FileCmdRunnerWrapper
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
        $transfer_lock_entity = Env::get('TRANSFER_LOCK_ENTITY', 'snm-cmd');  
        $request_dir = Env::get('REQUEST_DIR', '');  
        
        if (!$request_dir) {
            Log::log("CmdWrapper was NOT STARTED: please set REQUEST_DIR.\n\n Please start again");
            return;
        }
        if (!is_dir($request_dir)) {
            Log::log("CmdWrapper was NOT STARTED: please set proper REQUEST_DIR - '{$request_dir}' not found.\n\n Please start again");
            return;            
        }

        $res_dir = "{$request_dir}/res";
        if (!is_dir($res_dir)) {
            mkdir($res_dir);
        }
        
        $tg_token = Env::get('TG_TOKEN');
        $chat_id = Env::get('TG_CHAT_ID');
        Log::setTelegramBotConfig($tg_token, $chat_id, $name, 'SN Manager');
        
        try {
            CliTransferNodeHelper::validateIdentity($private_key_path, $pub_identity);
        } catch(TransferNodeException $ex) {
            $msg = $ex->getMessage();
            Log::log("CmdWrapper was NOT STARTED: $msg.\n\n Please start again");
            return;
        }  
        
        $servers = CliTransferNodeHelper::loadServersFromConfig();        
        
        $private_key = file_get_contents($private_key_path);
        
        $validator = new ValidatorInfo($cluster, $pub_identity, $pub_vote, $private_key, $transfer_lock_entity);      
        $transferNode = new TransferNode($cluster, $pub_identity, $pub_vote, $private_key, $transfer_lock_entity, $emulate_transfer);
        $transferNode->addServers($servers);
       
        $core = new FileCmdRunnerCore($validator, $transferNode, $request_dir, $res_dir, $emulate_transfer);
        $core->run();
    }
    
    public static function log($msg, $significant = 0)
    {
        Log::log($msg, $significant);
    }    
}

