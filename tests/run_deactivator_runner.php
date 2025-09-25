<?php
//ТЕСТЫ: проверить просто ожидание с несуществующим адресом, потушить неактивный сервер, потушить реальный сервер, потушить запасной сервер после выключения
include '../common.php';

$srv_data = [
    'name' => "Test Server",
    'ip' => "22.34.55.40",
    'user' => "test",
    'password' => 'superPassword'
];

$tg_data = [];

$script_file0 = __FILE__;
$script_dir = pathinfo($script_file0, PATHINFO_DIRNAME);

$script_file = realpath("{$script_dir}/../solana_auto_transfer_manager.php");

$srv = new NodeServer($srv_data['name'], $srv_data['ip'], $srv_data['user'], $srv_data['password']);

$dr = new DeactivatorRunner($script_file, $tg_data, 1);
$dr->run($srv, $tg_data);


