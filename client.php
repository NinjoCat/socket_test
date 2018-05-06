#!/usr/bin/env php
<?php
require __DIR__ . "/vendor/autoload.php";

use server\Client;
use server\Helper;

$client  = new Client(new Helper());
array_shift($argv);
if (!$argv) {
    echo "\n\nBAD REQUEST!\n\n";
    exit();
}
echo "\n\n";
echo $client->send(implode('&', $argv));
echo "\n\n";


