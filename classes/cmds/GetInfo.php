<?php

class GetInfo implements CmdInterface
{
    protected $_validatorInfo = null;
    protected $_transferNode = null;
    protected $_params = [];
    
    public function __construct(ValidatorInfo $validatorInfo, TransferNodeInterface $transferNode, $params, $emulation)
    {
        $this->_validatorInfo = $validatorInfo;
        $this->_transferNode = $transferNode;
        $this->_params = $params;
    }
    
    public function execute()
    {
        $tr = $this->_transferNode;
        $servers = $tr->getAllServers();
        
        $srv_data = [];
        
        $found_active = 0;
        foreach($servers as $server){
            $row = ['name' => $server->name, 'ip' => $server->ip];
            try {
                $tr->validateToServer($server);
                if (!$found_active)  {
                    $is_active = $tr->isServerActive($server);
                    $row['is_active'] = $is_active;
                    if ($is_active) {
                        $found_active = 1;
                    }
                } else {
                    $row['is_active'] = 0;
                }   
                $server->disconnect();
                $row['status'] = 'OK';
                $row['status_msg'] = '';
            } catch (Exception $e) {
                $row['status'] = 'ERROR';
                $row['status_msg'] = $e->getMessage();
            }
            $srv_data[] = $row;
        }        
        
        return ['servers' => $srv_data];
    }
}

