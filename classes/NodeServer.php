<?php

use phpseclib3\Net\SSH2;

class NodeServer implements NodeServerInterface
{
    protected $_name = null;
    protected $_ip = null;
    protected $_user = null;
    protected $_password = null;
    protected $_ssh = null;
    protected $_status_label = null;

    public function __get($name) {
        $property_name = "_{$name}";
        if (property_exists($this, $property_name)) {
            return $this->$property_name;
        }
    }
    
    public function __construct($name, $ip, $user, $password) {
        $this->_name = $name;
        $this->_ip = $ip;
        $this->_user = $user;
        $this->_password = $password;
    }
    
    public function connect()
    {
        if ($this->_ssh) {
            return true;
        }        
        $ssh = new SSH2($this->_ip);
        $ssh->setTimeout(5);

        // Подключаемся к серверу
        if (!$ssh->login($this->_user, $this->_password)) {
            return false;
        }  
        $ssh->setTimeout(30);
        $this->_ssh = $ssh;     
        return true;
    }
    
    public function disconnect()
    {
        if (!$this->_ssh) {
            return true;
        }          
        $this->_ssh->disconnect();   
        $this->_ssh = null;
    }
    
    public function reconnect()
    {
        $this->disconnect();
        $this->connect();
    }
    
    public function exec($cmd)
    {
        return $this->_ssh->exec($cmd);
    }
    
    public function resetChannel()
    {
        //Log::log("reset channel");
        if ($this->_ssh) {
            $this->_ssh->reset();
        }
    }
   
    public function setStatusLabel($status_label)
    {
        $this->_status_label = $status_label;
    }
    
    public function getServerData()
    {
        return [
            'name' => $this->name,
            'ip' => $this->ip,
            'user' => $this->user,
            'password' => $this->password
        ];
    }
}

