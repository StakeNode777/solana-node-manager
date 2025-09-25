<?php

class Utils
{
    protected static $_tick = 1000000; //time for the tick in microsecunds
    
    public static function sleepInTicks($ticks)
    {
        usleep($ticks * self::$_tick);
    }    
    
    /*public static function sleepMaxInTicks($ticks)
    {
        $curmicrotime();
    }*/
    
    public static function isHoursFromTo($from, $to = null) {
        $from = trim($from);
        if (strpos($from, '0')===0) $from = substr($from, 1, 1);
        if (strpos($to, '0')===0) $to = substr($to, 1, 1);
        $h = date('G');
        if ($h >= $from && $h < $to) return true;
        return false;
    }  
    
    public static function reconnectToServer(NodeServerInterface $server)
    {
        try{
            //$server->resetChannel();
            $server->reconnect();
            return true;
        } catch (Exception $ex) {
            return false;
        }
    }  
    
    public static function connectToServer(NodeServerInterface $server)
    {
        try{
            $server->connect();
            return true;
        } catch (Exception $ex) {
            return false;
        }
    } 
    
}

