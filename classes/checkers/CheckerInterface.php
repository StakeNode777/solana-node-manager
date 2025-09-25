<?php

interface CheckerInterface
{
    public function detectProblem($activeIp, $creditsInfo);
    
    public function resetStats();
}

