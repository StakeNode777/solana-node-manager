<?php

include 'common.php';

const MAX_SERVER_NUM = 10;

$script_file = __FILE__;

$autoTransfer = new AutoTransferWrapper($script_file);
$autoTransfer->run();

