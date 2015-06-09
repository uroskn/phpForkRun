<?php

  /**
   *  Fork-join primes program.
   *
   *  Get list of numbers and computes for each number whenever it is prime
   *  or not in parallel.
   *
   *  Recommended: php primes.php 15485863 15485867 15485263 15493871 15501049
   **/

  if (PHP_SAPI != "cli") die("Please run me from command line\n");
  require("../forkrun.php");

  $prog = array_shift($argv);
  $nums = array();
  foreach ($argv as $num)
  {
    $num = (int)$num;
    if ($num <= 0) die("Number $num is negative or invalid\n");
    if ($num > PHP_INT_MAX)
      die("Number $num is bigger than biggest PHP int (and we don't want it to overflow into float)\n");
    $nums[] = $num;
  }
  if (!count($nums)) die("Usage: $prog <num1,num2,...>\n");

  function is_prime($num)
  {
    for ($i = 2; $i <= ceil($num/2); $i++)
      if (!($num % $i)) return false;
    return true;
  }

  print("Doing parallel run... ");
  $start = microtime(true);
  $fr = new ForkRun();
  foreach ($nums as $num) $fr->addJob((string)$num, "is_prime", $num);
  $data = $fr->execute();
  print((microtime(true)-$start)." s\n");

  print("Doing sequential run... ");
  $start = microtime(true);
  foreach ($nums as $num) is_prime($num);
  print((microtime(true)-$start)." s\n");

  foreach ($data as $num => $prime)
  {
    if ($prime["retcode"]) print("Child process $num died with non-negative code {$prime["retcode"]}\n");
    if ($prime["data"]) print("$num is prime\n");
    else print("$num is not prime\n");
  }
