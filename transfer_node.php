<?php

//Должны быть следующие проверки перед отправкой:
//на активной ноде: доступ SSH, наличие solana.service и его опций --ledger, --vote-account (и его правильность), является ли активной
//на неактивной ноде: доступ SSH, наличие solana.service и его опций --ledger, --vote-account (и его правильность), кэтчап

//https://stackoverflow.com/questions/45882200/unable-to-exchange-encryption-keys

//https://solana.stackexchange.com/questions/3259/splitting-a-solana-keypair-into-public-and-private-keys

//https://github.com/Attestto-com/solana-php-sdk
//https://github.com/verze-app/solana-php-sdk

include 'common.php';

const MAX_SERVER_NUM = 10;

$script_file = __FILE__;

CliTransferNodeHelper::run($script_file);




