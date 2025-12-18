<?php

include 'common.php';

$params = [
    'c:' => 'config_file:',
];

$cli_options = getopt(implode('', array_keys($params)), $params);  
    
$config_file = isset($cli_options['config_file']) ? $cli_options['config_file'] : 'deactivator_config.json';
    
if (!file_exists($config_file)) {
    Log::log("Config file '$config_file' not found");
    exit;
}
    
$json = file_get_contents($config_file);
$data = json_decode($json, 1);

unlink($config_file);

$srv = new NodeServer($data['name'], $data['ip'], $data['user'], $data['password']);
$tg_token = isset($data['tg_token']) ? $data['tg_token'] : null;
$chat_id = isset($data['chat_id']) ? $data['chat_id'] : null;
Log::setTelegramBotConfig($tg_token, $chat_id, 'DEACTIVATOR');

Log::log("Deactivator started srv: {$srv->name} {$srv->ip}", 1);

for($k = 0;;$k++){
    try {
        if ($srv->connect()) {
            Log::log("Deactivator connected to the server {$srv->ip}");
            $res = TransferNode::deactivate($srv);
            $msg = "{$srv->name} {$srv->ip} deactivated: ";
            Log::log($msg, 1);
            Log::log($res, 1);
            break;
        }
    } catch (\Exception $e) {
        
    }
    sleep(1);
    if ($k>=30) { //period is k*(5+1) sec
        $msg = "Deactivator of srv {$srv->name} {$srv->ip} is still online";
        Log::log($msg, 1);
        $k = 0;
    }
}


