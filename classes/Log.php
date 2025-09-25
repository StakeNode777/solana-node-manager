<?php

class Log
{
    protected static $_tg_token = null;
    protected static $_chat_id = null;
    protected static $_name = null;
    protected static $_max_num = 0;
    protected static $_last_messages = [];
    protected static $_old_last_messages = [];
    protected static $_title_prefix = "Autotransfer";
    
    public static function setTelegramBotConfig($tg_token, $chat_id, $name, $title_prefix = "Autotransfer")
    {
        self::$_tg_token = $tg_token;
        self::$_chat_id = $chat_id;
        self::$_name = $name;
        self::$_title_prefix = $title_prefix;
    }
    
    public static function setGatheringLastMessages($max_num = 0)
    {
        self::$_max_num = $max_num;
        self::$_old_last_messages = [];
        self::$_last_messages = [];
    }
    
    public static function log($msg, $significant = 0)
    {
        //$date = date('Y-m-d H:i:s.v');
        $dt = new DateTime();
        $date = $dt->format('Y-m-d H:i:s.v');
        $full_msg = "[$date]: {$msg}\n";
        echo $full_msg;  
        if ($significant && self::$_tg_token) {
            $name = self::$_name;
            $title_prefix = self::$_title_prefix;
            $tg_msg = "{$title_prefix} {$name} - {$msg}";
            $tg_token = self::$_tg_token;
            $chat_id = self::$_chat_id;            
            $url = "https://api.telegram.org/bot{$tg_token}/sendMessage?chat_id={$chat_id}&text=" . urlencode($tg_msg);
            file_get_contents($url);            
        }
        $max_num = self::$_max_num;
        if ($max_num) {
            if (count(self::$_last_messages)>=$max_num) {
                self::$_old_last_messages = self::$_last_messages;
                self::$_last_messages = [];
            }
            self::$_last_messages[] = trim($full_msg);
        }
    }
    
    public static function getLastMessages()
    {
        $max_num = self::$_max_num;
        $old_and_new = array_merge(self::$_old_last_messages, self::$_last_messages);
        return array_slice($old_and_new, 0, $max_num);
    }
    
    public static function getTgData()
    {
        return ['tg_token' => self::$_tg_token, 'chat_id' => self::$_chat_id];
    }
}

