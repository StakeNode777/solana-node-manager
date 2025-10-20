<?php
//ТЕСТЫ: проверить просто ожидание с несуществующим адресом, потушить неактивный сервер, потушить реальный сервер, потушить запасной сервер после выключения

$dir = __DIR__;

include "{$dir}/../common.php";

const MAX_SERVER_NUM = 10;

date_default_timezone_set('UTC');
$params = [
    'c:' => 'config_dir:',
];

$cli_options = getopt(implode('', array_keys($params)), $params);  

$config_dir = isset($cli_options['config_dir']) ? $cli_options['config_dir'] : '';
$config_file = "{$config_dir}/config.conf";
echo "\nconfig_file = {$config_file}\n";

Env::init($config_file);
$servers = CliTransferNodeHelper::loadServersFromConfig();
CliTransferNodeHelper::printServers($servers);

echo "Enter Active Server for deactivation: ";
$index = trim(fgets(STDIN));

$srv_data = $servers[$index];

$tg_data = [];

$script_file0 = __FILE__;
$script_dir = pathinfo($script_file0, PATHINFO_DIRNAME);

$script_file = realpath("{$script_dir}/../snm_auto_transfer.php");

$srv = new NodeServer($srv_data['name'], $srv_data['ip'], $srv_data['user'], $srv_data['password']);

//set 0 to run real deactivator
$dr = new DeactivatorRunner($script_file, $tg_data, 1);
$dr->run($srv, $tg_data);


