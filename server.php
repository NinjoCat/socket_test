#!/usr/bin/env php
<?php
require __DIR__ . "/vendor/autoload.php";

use server\Server;
use server\Helper;

$s = new Server(new Helper());
$s->start();