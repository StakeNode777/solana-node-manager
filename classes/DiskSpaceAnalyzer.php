<?php

class DiskSpaceAnalyzer
{
    protected $_threshold = null;
    protected $_srv = null;
    
    public function __construct(NodeServer $srv, $threshold = null) 
    {
        $threshold = intval($threshold);
        if ($threshold) $this->_threshold = $threshold; 
        $this->_srv = $srv;
    }   
    
    public function areFullDisks()
    {
        $output = $this->_srv->exec('df -h');
        $lines = explode("\n", trim($output));
        unset($lines[0]); //remove first one
        $errors = array();
        foreach($lines as $str) {
            $res = $this->_analyzeStr($str);
            if (!empty($res)) {
                $errors = $errors + $res;
            }
        }
        
        if (!empty($errors)) {
            return true;
        }      
    }
    
    protected function _analyzeStr($str)
    {
        if (!preg_match("/^((cdrom))/", $str)) {
            $arr = preg_split("/[\s]+/", $str);
            $disk_name = $arr[0];
            $use = intval(str_replace('%', '', $arr[4]));
            //echo "{$disk_name} - {$use}%\n";
            if ($use >= $this->_threshold) {
                return array($disk_name => $use);
            }
        }  
        return false;
    }    
}

