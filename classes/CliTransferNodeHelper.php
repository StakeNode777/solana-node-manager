<?php

class CliTransferNodeHelper
{
    public static function run($script_file)
    { 
        date_default_timezone_set('UTC');
        $params = [
            'c:' => 'config_dir:',
            'f:' => 'from:',
            't:' => 'to:',
            'm:' => 'mode:'
        ];

        $cli_options = getopt(implode('', array_keys($params)), $params);  
    
        $config_dir = isset($cli_options['config_dir']) ? $cli_options['config_dir'] : 'config'; 
        $config_file = "{$config_dir}/config.conf";
                
        if (!file_exists($config_file)) {
            Log::log("Config file not found: {$config_file}");
            return;
        }
        Log::log("Detected config_file: {$config_file}");
        
        Env::init($config_file);
        
        $emulate_transfer = intval(Env::get('EMULATE_TRANSFER', 0));  

        $cluster = Env::get('CLUSTER');
        $pub_identity = Env::get('PUB_IDENTITY');
        $pub_vote = Env::get('PUB_VOTE');
        $private_key_path = "{$config_dir}/validator-keypair.json";
        
        $servers = CliTransferNodeHelper::loadServersFromConfig();
        
        $transfer_lock_entity = Env::get('TRANSFER_LOCK_ENTITY');
        
        $transfer_mode = isset($cli_options['mode']) ? $cli_options['mode'] : null;
        $transfer_mode = $transfer_mode ? $transfer_mode : 'safe';
        $transfer_lock_entity = $transfer_lock_entity ? $transfer_lock_entity : 'manual';
        $activation_modes = ['safe_activate', 'activate_only'];
        $is_transfer = !in_array($transfer_mode, $activation_modes);     

        try { 
            CliTransferNodeHelper::validateIdentity($private_key_path, $pub_identity);
            $private_key = file_get_contents($private_key_path);    

            $tr = new TransferNode($cluster, $pub_identity, $pub_vote, $private_key, $transfer_lock_entity, $emulate_transfer);
            $tr->addServers($servers);
            
            Log::log("transfer_mode = {$transfer_mode}");

            CliTransferNodeHelper::printServers($servers);
            $from_srv = "";
            if ($is_transfer) {
                if (!isset($cli_options['from'])) {
                    $from_srv = CliTransferNodeHelper::inputServerData("Enter From Server(Active):", $servers);
                } else {
                    $from_srv = $cli_options['from'];
                } 
            }
            
            if (!isset($cli_options['to'])) {
                $to_srv = CliTransferNodeHelper::inputServerData("Enter To Server(Inactive):", $servers);
            } else {
                $to_srv = $cli_options['to'];
            }

            CliTransferNodeHelper::printAltCommand($script_file, $config_dir, $from_srv, $to_srv);
            
            if ($is_transfer) {
                $from_srv = $tr->getServerByInfo($from_srv);
                $to_srv = $tr->getServerByInfo($to_srv);
                echo "\n{$from_srv->name} -> {$to_srv->name}\n";
                echo "{$from_srv->user}@{$from_srv->ip} -> {$to_srv->user}@{$to_srv->ip}";                        
                echo "\n\n";            
                $tr->checkAndTransfer($from_srv, $to_srv, $transfer_mode);
            } else {
                $to_srv = $tr->getServerByInfo($to_srv);
                echo "\n Activation {$to_srv->name} ({$to_srv->user}@{$to_srv->ip})\n";                      
                echo "\n\n";            
                $tr->checkAndActivate($to_srv, $transfer_mode);                
            }

        } catch (TransferNodeException $e ) {
            Log::log("\n\n" . $e->getMessage());
        }

        echo "\n\n";        
    }
    
    public static function loadServersFromConfig()
    {
        $servers = [];
        for ($i = 1; $i <= MAX_SERVER_NUM; $i++) {
            $name = Env::get("SRV_NAME_{$i}");
            $ip = Env::get("SRV_IP_{$i}");
            $user = Env::get("SRV_USER_{$i}");
            $password = Env::get("SRV_PASSWORD_{$i}");

            if (is_null($ip)) {
                continue;
            }
            $servers[] = [
                'name' => $name,
                'ip' => $ip,
                'user' => $user,
                'password' => $password
            ];
        }
        return $servers;
        
    }
    
    public static function validateIdentity($private_key_path, $pub_identity)
    {
        if (!file_exists($private_key_path)) {
            $msg = "File not found '{$private_key_path}'";
            throw new TransferNodeException($msg);
        }        
        
        $cmd = "solana-keygen pubkey {$private_key_path}";        
        $pub_identity_from_private = trim(shell_exec($cmd));
        Log::log("pub identity from private = {$pub_identity_from_private}\n");
        if ($pub_identity_from_private!=$pub_identity) {
            $msg = "Identity mismatch in config private_key vs pub_identity '{$pub_identity_from_private}' vs '{$pub_identity}'";
            throw new TransferNodeException($msg);
        }        
    }
    
    public static function printServers($servers)
    {
        echo "\n\nServers:\n";
        foreach($servers as $key=>$server){            
            echo "#{$key}: {$server['name']} {$server['user']}@{$server['ip']}\n";
        }
        echo "\n";
    }
    
    public static function inputServerData($msg, $servers)
    {
        echo "{$msg} ";
        $str = trim(fgets(STDIN));
        
        if (isset($servers[$str])) {
            return $servers[$str]['name'];
        }
        
        return $str;
    }
    
    public static function printAltCommand($script_file, $config_dir, $from_srv, $to_srv)
    {
        $script_name = isset($_SERVER['argv'][0]) ? $_SERVER['argv'][0] : 'transfer_node.php';
        $config_dir_full_path = realpath($config_dir);
        $from_part = $from_srv ? " --from={$from_srv}" : "";
        echo "This command do the same:\n\n";
        echo PHP_BINARY . " {$script_file} --config_dir='{$config_dir_full_path}'{$from_part} --to={$to_srv}\n\n";
    }
}

