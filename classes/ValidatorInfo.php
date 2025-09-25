<?php

class ValidatorInfo implements ValidatorInfoInterface
{    
    protected $_cluster = null;
    protected $_identity_pubkey = null;

    protected $_first_no_ping_time = 0;
    protected $_first_deliq_time = 0;
    
    protected $_cur_epoch = 0;
    
    public function __construct($cluster, $identity_pubkey)
    {
        $this->_cluster = $cluster;
        $this->_identity_pubkey = $identity_pubkey;
        
        SolanaLastCreditsProvider::setCluster($cluster);
    }    
    
    public function getIdentity()
    {
        return $this->_identity_pubkey;
    }
    
    public function getDelinquentStatus($delinquent_slot_distance = false)
    {
        $cluster = $this->_cluster;
        $identity_pubkey = $this->_identity_pubkey;
        
        $num_tries = 3;
        for($i = 0; $i < $num_tries; $i++){
            $data = self::getAllValidatorsData($cluster, $delinquent_slot_distance);
            if (isset($data['validators'])) {
                break;
            }
            self::log("WARNING: RPC getAllValidatorsData returned bad result try #{$i}");
            if (($i+1) < $num_tries) {
                sleep($i + 2);
            }    
        }        
        
        if (!isset($data['validators'])) {            
            self::log("ERROR: wrong All Validators Data. Please check solana installation or API");
            return self::DSTATUS_UNDEFINED;
        }
        
        $validators = $data['validators'];
        
        foreach ($validators as $validator) {
            if ($validator['identityPubkey']!=$identity_pubkey) {
                continue;
            }
            $node_id = $validator['identityPubkey'];
            
            if (!isset($validator['delinquent'])) {
                self::log("WARNING: no param 'delinquent' for validator '{$identity_pubkey}'");
            }

            if ($validator['delinquent']) {
                $problem_node_ids[] = $node_id;
                self::log("validator '{$identity_pubkey}' is DELINQUENT", 1);
                if (!$this->_first_deliq_time) {
                    $this->_first_deliq_time = time();
                }                
                return self::DSTATUS_DELIQUENT;
            } else {
                //if (DEBUG_INFO) {
                //    self::log("DEBUG INFO: validator '{$identity_pubkey}' is OK ds = {$validator['delinquent']}");
                //}
                $this->_first_deliq_time = 0;
                return self::DSTATUS_OK;
            }
        }   
        self::log("validator '{$identity_pubkey}' Not found"); 
        return self::DSTATUS_UNDEFINED;
    }

    
    public static function getAllValidatorsData($cluster = 'mainnet', $delinquent_slot_distance = false)
    {
        $cluster_param = 'um';
        if ($cluster=='testnet') {
            $cluster_param = 'ut';
        }
        //TODO --delinquent-slot-distance - сделать 64 вместо 128
        $dsd_part = "";
        if ($delinquent_slot_distance && $delinquent_slot_distance > 10 &&  $delinquent_slot_distance < 300) {
            $dsd_part = "--delinquent-slot-distance {$delinquent_slot_distance} ";
        }
        $cmd = "timeout 5s solana -{$cluster_param} validators {$dsd_part}--output json-compact";

        $output = `$cmd`;

        return json_decode($output, true);
    }

    /**
     *
     * @return boolean
     */
    //TODO we can use alternative - solana gossip
    public function getActiveNodeIP()
    {
        $num_tries = 3;
        for($i = 0; $i < $num_tries; $i++){
            $arr = self::getClusterNodes($this->_cluster);
            if (!empty($arr)) {
                break;
            }
            if (($i+1) < $num_tries) {
                sleep(1);
            }    
        }
        
        if (empty($arr)) {           
            self::log("WARNING: Problem with detection of Active IP by RPC request getClusterNodes");
        }
        
        foreach($arr as $el){
            if ($el['pubkey']==$this->_identity_pubkey) {
                $parts = explode(":", $el['gossip']);
                return $parts[0];
            }
        }
        
        return false;
    }
    
    public static function getClusterNodes($cluster)
    {
        $cluster_part = 'testnet';
        if ($cluster=='mainnet') {
            $cluster_part = 'mainnet-beta';
        }        
        $url = "https://api.{$cluster_part}.solana.com";
        $request = '\'{"jsonrpc":"2.0","id":1,"method":"getClusterNodes"}\'';
        $headers_part = '-H "Content-Type: application/json"';
        
        $cmd = "curl -sS --max-time 10 $url -X POST $headers_part -d $request";
        $json = shell_exec($cmd);
        $data = json_decode($json, 1);

        if (!isset($data['result'])) {
            self::log("WARNING: RPC request getClusterNodes returned bad result");
            return [];
        }
        
        return $data['result'];        
    }
   
    public function getCreditsInfo()
    {        
        $data = self::getVoteAccounts();
        if (!$data) {
            return ['error' => 1];
        }
        
        $cur_epoch_from_va = self::extractCurEpochFromVoteAccounts($data);
        
        if ($this->_cur_epoch!=$cur_epoch_from_va || !$this->_cur_epoch) {
            $epoch = $this->getLastEpoch();            
            if (!$epoch) {
                return ['error' => 1];
            }
            $this->_cur_epoch = $epoch;
        }
        
        $epoch = $this->_cur_epoch;
        $voteCreditsData = self::extractCreditsFromVoteAccounts($epoch, $data);        
        if (!$voteCreditsData) {
            return ['error' => 1];
        }        
        
        $identity = $this->_identity_pubkey;
        $credits = isset($voteCreditsData[$identity]) ? $voteCreditsData[$identity] : false;
        
        //после такой сортировки ключи будут числовыми
        usort($voteCreditsData, function($a, $b) {
            return $b['credits'] <=> $a['credits'];
        });    
       
        $delta200 = false;
        $credits200 = $voteCreditsData[199];
        $delta200 = $credits===false ? false : $credits['credits'] - $credits200['credits'];
        
        return [
            'epoch' => $epoch,
            'credits' => $credits===false ? false : $credits['credits'],
            'credits200' => $credits200['credits'],
            'delta200' => $delta200,
        ];        
    }
    
    public static function extractCurEpochFromVoteAccounts($data)
    {
        if (isset($data['result'])) {
            $voteAccounts = $data['result']['current'];

            $voteCredits = [];
            $max_epoch = 0;
            $k = 0;
            foreach ($voteAccounts as $account) {
                if (isset($account['epochCredits'])) {
                    foreach ($account['epochCredits'] as $cr_data) {
                        if ($cr_data[0] > $max_epoch) {
                            $max_epoch = $cr_data[0];
                        }
                    }
                    $k++;
                }
                if ($k >= 100) {
                    break;
                }
            }

            return $max_epoch;
        }

        return false;
    }
    
    public static function extractCreditsFromVoteAccounts($epoch, $data)
    {
        if (isset($data['result'])) {
            $voteAccounts = array_merge($data['result']['current'], $data['result']['delinquent']);

            $voteCredits = [];
            foreach ($voteAccounts as $account) {
                $identity = $account['nodePubkey'];
                $voteCredits[$identity] = [
                    'identity' => $identity,
                    'credits' => 0 
                ];

                if (isset($account['epochCredits'])) {
                    $num_credits = 0;
                    foreach ($account['epochCredits'] as $cr_data) {
                        if ($cr_data[0] == $epoch) {    
                            $num_credits = $cr_data[1] - $cr_data[2]; // Подтвержденные credits                 
                        }
                    }
                    $voteCredits[$identity]['credits'] = $num_credits;
                }
            }

            return $voteCredits;
        }

        return false;
    }
   
    public function getLastEpoch()
    {
        $num_tries = 3;
        for($i = 0; $i < $num_tries; $i++){
            $epoch = SolanaLastCreditsProvider::getLastEpoch();
            if ($epoch) {
                break;
            }
            if (($i+1) < $num_tries) {
                sleep($i+2);
            }    
        }
        
        if (!$epoch) {           
            self::log("WARNING: Problem with detection of Epoch by RPC request getEpochInfo");
        }
        return $epoch;
    }    
    
    public static function getVoteAccounts()
    {
        $num_tries = 3;
        for($i = 0; $i < $num_tries; $i++){
            $data = SolanaLastCreditsProvider::getVoteAccounts();
            if (isset($data['result'])) {
                return $data;
            }
            if (($i+1) < $num_tries) {
                sleep($i+2);
            }    
        }  
        self::log("WARNING: Problem with getting credits by RPC request getVoteAccounts");
        return false;        
    }
    
    public static function log($msg, $significant = 0)
    {
        Log::log($msg, $significant);
    }     
}

