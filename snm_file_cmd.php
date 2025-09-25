<?php

include 'common.php';

const MAX_SERVER_NUM = 10;

$script_file = __FILE__;

$manager = new FileCmdRunnerWrapper($script_file);
$manager->run();

