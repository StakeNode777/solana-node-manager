<?php

class DelinquentChecker implements CheckerInterface
{
    //пока комментируем это дело
    protected $_delinqNum;
    
    protected $_validator = null;
    protected $_transferNode = null;
    protected $_checkerAssistant = null;
    
    protected $_delinquent_slot_distance = 32;
    
    public function __construct(ValidatorInfoInterface $validator, TransferNodeInterface $transferNode) {
        $this->_validator = $validator;
        $this->_transferNode = $transferNode;
        $this->_checkerAssistant = new CheckerAssistant($validator, $transferNode);
    }
    
    public function detectProblem($activeIP, $creditsInfo)
    {        
        $dsd = $this->_delinquent_slot_distance;        
        $delinq_status = $this->_validator->getDelinquentStatus($dsd);
        
        if ($delinq_status == ValidatorInfo::DSTATUS_DELIQUENT) {
            $this->_delinqNum++;
            Log::log("WARNING: Detected delinquent status. Starting research", 1);
            return $this->researchPossibleProblem($activeIP);
        } elseif ($delinq_status == ValidatorInfo::DSTATUS_UNDEFINED) {
            Log::log("WARNING: Detected UNDEFINED deliquent status. Could be problems with RPC");
        }  
        //в самом конце, если не было проблем, то логгируем IP
        $this->_checkerAssistant->logActiveIP($activeIP);
        
        return false;
    }
    
    public function resetStats()
    {
        //nope;
        Log::log("DelinquentChecker - reset not needed");
    }
    
    public function researchPossibleProblem($activeIP)
    {                
        $checkerAssistant = $this->_checkerAssistant;
        if ($checkerAssistant->isActiveIPChanged($activeIP)) {
            return false;
        }        

        //тут у нас будет небольшая потеря времени
        $problem = $checkerAssistant->checkConnectionProblem($activeIP);
        if (!$problem && !$checkerAssistant->isGoodIdentityAndSync($activeIP)) {
            $problem = 'other_problem';            
        } 
        
        if ($problem) {
            $msg = "WARNING: Delinquent - cause - " . $checkerAssistant->getLastProblemDescription();
            Log::log($msg, 1);
            return $problem;
        }
       
        //ждем еще 3 сек и проверяем опять
        $validator = $this->_validator;
        Utils::sleepInTicks(3);
        $dsd = $this->_delinquent_slot_distance;
        if ($validator->getDelinquentStatus($dsd) == ValidatorInfo::DSTATUS_OK) {
            Log::log("WARNING: No more delinquent - 2nd try", 1);
            return false;
        }
        
        Log::log("WARNING: Delinquent - all 2 checks", 1);
        return 'other_problem';
    }
}

