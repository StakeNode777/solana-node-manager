<?php

require 'vendor/autoload.php';

require_once 'classes/Env.php';
require_once 'classes/AutoTransferCore.php';
require_once 'classes/AutoTransferWrapper.php';
require_once 'classes/CheckerAssistant.php';
require_once 'classes/CliTransferNodeHelper.php';
require_once 'classes/DeactivatorRunner.php';
require_once 'classes/FileCmdRunnerCore.php';
require_once 'classes/FileCmdRunnerWrapper.php';
require_once 'classes/Log.php';
require_once 'classes/NodeServerInterface.php';
require_once 'classes/NodeServer.php';
require_once 'classes/SolanaLastCreditsProvider.php';
require_once 'classes/TransferNodeInterface.php';
require_once 'classes/TransferNode.php';
require_once 'classes/TransferNodeException.php';
require_once 'classes/ValidatorInfoInterface.php';
require_once 'classes/ValidatorInfo.php';
require_once 'classes/Utils.php';

require_once 'classes/checkers/CheckerInterface.php';
require_once 'classes/checkers/SuspiciousCreditsDropChecker.php';
require_once 'classes/checkers/StrongCreditsDropChecker.php';
require_once 'classes/checkers/DelinquentChecker.php';

require_once 'classes/cmds/CmdInterface.php';
require_once 'classes/cmds/DoTransfer.php';
require_once 'classes/cmds/GetInfo.php';
require_once 'classes/cmds/ShowErrorMessage.php';

require_once 'classes/notifiers/CreditsChangeNotifier.php';
require_once 'classes/notifiers/HealthSatmNotifier.php';
require_once 'classes/notifiers/ServerHealthNotifier.php';
require_once 'classes/notifiers/NodeBalanceNotifier.php';
require_once 'classes/DiskSpaceAnalyzer.php';
