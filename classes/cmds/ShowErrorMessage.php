<?php

class ShowErrorMessage implements CmdInterface
{
    protected $_msg = null;
    
    public function __construct($params) {
        $this->_msg = $params['msg'];
    }
    
    public function execute()
    {
        return [
            'error' => 1,
            'msg' => $this->_msg
        ];
    }
}

