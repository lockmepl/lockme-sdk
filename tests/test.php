<?php

require_once __DIR__.'/../vendor/autoload.php';

use LockMe\LockMe;
use LockMe\LockMe_Exception;

try{
  $lockme = new LockMe("wrong","string");
  $lockme->Test();
}catch(LockMe_Exception $e){
  echo $e->getMessage()."\n";
}
