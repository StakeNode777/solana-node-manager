<?php

class SolanaLastCreditsProvider
{   
    const MAINNET_API_URL = "https://api.mainnet-beta.solana.com";
    const TESTNET_API_URL = "https://api.testnet.solana.com";
    
    protected static $_rpcUrl = self::MAINNET_API_URL;
    
    public static function setCluster($cluster)
    {
        if ($cluster=='testnet') {
            self::$_rpcUrl = self::TESTNET_API_URL;
        } elseif ($cluster=='mainnet') {
            self::$_rpcUrl = self::MAINNET_API_URL;
        }
    }
    
    public static function getVoteAccounts()
    {
        $payload = json_encode([
            "jsonrpc" => "2.0",
            "id" => 1,
            "method" => "getVoteAccounts",
            "params" => []
        ]);

        $ch = curl_init(self::$_rpcUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_TIMEOUT, 7); 

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }   
   
    public static function getLastEpoch($cached = true)
    {        
        $data = [
            "jsonrpc" => "2.0",
            "id" => 1,
            "method" => "getEpochInfo"
        ];

        $ch = curl_init(self::$_rpcUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 7);

        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $decoded = json_decode($response, true);
            return $decoded['result']['epoch'] ?? null;
        }
        return null;
    } 
}

