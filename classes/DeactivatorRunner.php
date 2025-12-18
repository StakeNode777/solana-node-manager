<?php

class DeactivatorRunner
{
    protected $_script_dir = null;
    protected $_emulate = 0;
    protected $_tg_data = [];
    
    public function __construct($script_file, $tg_data, $emulate = 0) {
        $this->_script_dir = pathinfo($script_file, PATHINFO_DIRNAME);
        if (!is_dir($this->_script_dir)) {
            $msg = "Please detect right script_file='{$script_file}' for the DeactivatorRunner - '{$this->_script_dir}'";
            throw new TransferNodeException($msg);
        }
        $this->_emulate = $emulate;
        $this->_tg_data = $tg_data;
    }
    
    public function run(NodeServerInterface $activeServer)
    {       
        $activeServerDataFile = tempnam('/tmp', 'asd_');
        $tg_data = is_array($this->_tg_data) ? $this->_tg_data : [];
        $data = $activeServer->getServerData() + $tg_data;
        file_put_contents($activeServerDataFile, json_encode($data));

        $output_part = '> /dev/null';
        $log_dir = getcwd() . "/logs";
        if (is_dir($log_dir)) {
            $output_part = ">> {$log_dir}/deactivator.log";
        }
        
        $cmd = "nohup php {$this->_script_dir}/deactivator.php --config_file={$activeServerDataFile} {$output_part} 2>&1 &";
        
        if (!$this->_emulate) {            
            Log::log("Started deactivator with config file '$activeServerDataFile'", 1);
            Log::log("deactivator cmd: $cmd");
            shell_exec($cmd);
        } else {
            Log::log("Started deactivator mockup with config file '$activeServerDataFile'"); 
            Log::log($cmd);
            Log::log("PLEASE REMOVE FILE $activeServerDataFile");
        }
    }
}

