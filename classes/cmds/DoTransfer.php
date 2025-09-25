<?php

class DoTransfer implements CmdInterface
{
    protected $_validatorInfo = null;
    protected $_transferNode = null;
    protected $_emulation = 0;
    protected $_params = [];
    
    public function __construct(ValidatorInfo $validatorInfo, TransferNodeInterface $transferNode, $params)
    {
        $this->_validatorInfo = $validatorInfo;
        $this->_transferNode = $transferNode;
        $this->_params = $params;
    }
    
    public function execute()
    {
        $params = $this->_params;
        $emulation = $this->_emulation;
        $transfer_lock_entity = $params['transfer_lock_enitity'] ?? 'snm-cmd';
        $transfer_mode = $params['mode'] ?? false;
        $pub_identity = $params['pub_identity'] ?? false;
        $snm_pub_identity = $this->_validatorInfo->getIdentity();
        
        $from_srv = $params['from'] ?? false;
        $to_srv = $params['to'] ?? false;
        
        if (!$transfer_lock_entity) {
            $transfer_lock_entity = 'snm-cmd';
        }
        
        if (!$transfer_mode) { 
            return $this->errorResult("Transfer mode was not specified");
        }
        
        if ($pub_identity!=$snm_pub_identity) {
            $msg = "Pub identity mismatch '{$snm_pub_identity}' vs '{$pub_identity}'";
            return $this->errorResult($msg);
        }
        
        $tr = $this->_transferNode;
        
        $activation_modes = ['safe_activate', 'activate_only'];
        $is_transfer = !in_array($transfer_mode, $activation_modes);   
        
        if ($is_transfer && !$from_srv) {
            $msg = "server 'from' was not specified for the mode '{$transfer_mode}'";
            return $this->errorResult($msg);
        }
        
        if (!$to_srv) {
            $msg = "server 'to' was not specified";
            return $this->errorResult($msg);            
        }

        $error_flag = 0;
        try { 
            Log::setGatheringLastMessages(200);
            Log::log("transfer_mode = {$transfer_mode}");
            
            if ($is_transfer) {
                $from_srv = $tr->getServerByInfo($from_srv);
                $to_srv = $tr->getServerByInfo($to_srv);
                Log::log("\n{$from_srv->name} -> {$to_srv->name}");
                Log::log("{$from_srv->user}@{$from_srv->ip} -> {$to_srv->user}@{$to_srv->ip}");
                Log::log("\n\n");   
                if (!$emulation) {                
                    $tr->checkAndTransfer($from_srv, $to_srv, $transfer_mode);
                } else {
                    Log::log("TRANSFER EMULATED");
                }
            } else {
                $to_srv = $tr->getServerByInfo($to_srv);
                Log::log("\n Activation {$to_srv->name} ({$to_srv->user}@{$to_srv->ip})");                      
                Log::log("\n\n");   
                
                if (!$emulation) {
                    $tr->checkAndActivate($to_srv, $transfer_mode);
                } else {
                    Log::log("ACTIVATION EMULATED");
                }
            }
        } catch (TransferNodeException $e ) {
            $error_flag = 1;
            $error_msg = $e->getMessage();
            Log::log("\n\n" . $error_msg);
        }
        
        $last_messages = Log::getLastMessages();
        Log::setGatheringLastMessages(0);
        
        if ($error_flag) {
            return $this->errorResult($error_msg, $last_messages);
        }
        
        return  [
            'log' => $last_messages
        ];
    }
    
    public function errorResult($msg, $log = [])
    {
        $res = [
            'error' => 1,
            'msg' => $msg            
        ];
        if (!empty($log)) {
            $res['log'] = $log;
        }
        
        return $res;
    }
}

