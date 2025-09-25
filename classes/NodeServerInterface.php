<?php

interface NodeServerInterface{
    public function exec($cmd);
    
    public function reconnect();
}

