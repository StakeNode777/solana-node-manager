<?php

include 'common.php';

const MAX_SERVER_NUM = 10;

$script_file = __FILE__;

date_default_timezone_set('UTC');
$params = [
    'c:' => 'config_dir:',
];

$cli_options = getopt(implode('', array_keys($params)), $params);  
    
$config_dir = isset($cli_options['config_dir']) ? $cli_options['config_dir'] : '.env';
$config_file = "{$config_dir}/config.conf";
$private_key_path = "{$config_dir}/validator-keypair.json";
                
if (!file_exists($config_file)) {
    Log::log("Config file not found: {$config_file}");
    return;
}
Log::log("Detected config_file: {$config_file}");
        
Env::init($config_file);

$output = shell_exec("solana --version");
if (strpos($output, 'solana-cli')===false) {
    Log::log("ERROR: solana not installed. Please check by running 'solana --version'");
    exit;
}

$cluster = Env::get('CLUSTER');
$pub_identity = Env::get('PUB_IDENTITY');
$pub_vote = Env::get('PUB_VOTE');
$servers_data = CliTransferNodeHelper::loadServersFromConfig();

$transfer_lock_entity = Env::get('TRANSFER_LOCK_ENTITY');

CliTransferNodeHelper::validateIdentity($private_key_path, $pub_identity);
$private_key = file_get_contents($private_key_path);    

$tr = new TransferNode($cluster, $pub_identity, $pub_vote, $private_key, $transfer_lock_entity);
$tr->addServers($servers_data);  

CliTransferNodeHelper::printServers($servers_data);

$servers = $tr->getAllServers();

foreach($servers as $server){
    $tr->validateToServer($server);
    $server->disconnect();
}
