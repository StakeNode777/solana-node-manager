<?php

class FileCmdRunnerCore
{
    protected $_validator = null;
    protected $_transferNode = null;
    protected $_emulate_transfer = 0;
    protected $_requestDir = null;
    protected $_resDir = null;
    
    //protected $_first_try_resolve_time = 0;
    //protected $_failed_try = 0;
    
    protected $_num_failed_ip_checks = 0;
    protected $_activeIP = '';
    
    protected $_locked = 0;
    protected $_deliqNum = 0;
    
    public function __construct(
        ValidatorInfoInterface $validator, 
        TransferNodeInterface $transferNode,
        $requestDir,
        $resDir,
        $emulate_transfer
    ) 
    {
        $this->_validator = $validator;
        $this->_transferNode = $transferNode;
        $this->_emulate_transfer = $emulate_transfer;
        $this->_requestDir = $requestDir;
        $this->_resDir = $resDir;
    }
    
    public function run()
    {
        $emulation = $this->_emulate_transfer;
        $validator = $this->_validator;
        $transferNode = $this->_transferNode;
        
        $identity = $validator->getIdentity();
     
        $em_part = $emulation ? " in EMULATION MODE" : "";
        
        Log::log("started identity='{$identity}' $em_part", 1);

        //Log::log("activeIP = {$activeIP}");
        
        for($k=0;;$k++){            
            if ($this->handleNewCmd()) {
                continue;
            }
       
            if ($k > 1000000) {
                $k = -1;
            }
            sleep(1);
        }        
    }
    
    public function handleNewCmd()
    {
        $filenames = glob("{$this->_requestDir}/*");
        
        $new_cmd_filename = false;
        foreach($filenames as $filename) {
            if (is_dir($filename)) {
                continue;
            }
            $new_cmd_filename = $filename;
            break;
        }
        
        if (!$new_cmd_filename) {
            return;
        }
        
        try {
            $cmd = $this->createCmd($new_cmd_filename);
            $res = $cmd->execute();
            $res_json = json_encode($res);
        } catch(Exception $e) {
            $msg = $e->getMessage();
            $trace_str = $e->getTraceAsString();
            Log::log("Some problems detected due execution command: $msg\n\n{$trace_str}");
        }
        
        $basename = pathinfo($new_cmd_filename, PATHINFO_BASENAME);
        $res_file = "{$this->_resDir}/{$basename}";
        file_put_contents($res_file, $res_json);
        
        
        return true;
        
    }
    
    public function createCmd($new_cmd_filename)
    {
        $size = filesize($new_cmd_filename);
        $big_file_flag = 0;
        if ($size > 1000000) { //max filesize 1MB
            $big_file_flag = 1;
        } else {
            $cmd_data = json_decode(file_get_contents($new_cmd_filename), 1);
        }        
        
        unlink($new_cmd_filename);
        
        if ($big_file_flag) {
            $msg = "File is too big. Maximum allowed size is 1MB";
            return new ShowErrorMessage(['msg' => $msg]);
        } elseif (is_null($cmd_data)) {
            if (!trim($cmd_data)) {
                $msg = "Empty command detected";
            } else {
                $msg = json_last_error_msg();
            }
            return new ShowErrorMessage(['msg' => $msg]);
        }   
        
        $cmd_name = $cmd_data['name'] ?? '';
        $cmd_params = $cmd_data['params'] ?? [];
        Log::log("Start execution command '{$cmd_name}'");
        
        $map = [
            'do-transfer' => 'DoTransfer',
            'get-info' => 'GetInfo'
        ];
        
        if (!isset($map[$cmd_name])) {
            $msg = "Command '{$cmd_name}' is not possible";
            return new ShowErrorMessage(['msg' => $msg]);
        }

        //Log::log("cmd_params: " . print_r($cmd_params, 1));
        return new $map[$cmd_name]($this->_validator, $this->_transferNode, $cmd_params, $this->_emulate_transfer);
    }
    
    
    
    public static function log($msg, $significant = 0)
    {
        Log::log($msg, $significant);
    }     
    
}

