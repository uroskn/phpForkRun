<?php

  /**
   *  do-something-else
   *
   *  Demonstration that your head can also do something else during that time
   *
   *  This program outputs:
   *    $ php do-something-else.php
   *    Waiting for 3 seconds, THEN reaping childs...
   *    Having slept for 1 seconds.
   *    Having slept for 2 seconds.
   *    Having slept for 3 seconds.
   *    OK, NOW we are reaping for kids.
   *    Having slept for 4 seconds.
   *    Having slept for 5 seconds.
   *    Having slept for 6 seconds.
   *    Having slept for 7 seconds.
   *    Having slept for 8 seconds.
   *    Having slept for 9 seconds.
   *    Having slept for 10 seconds.
   *    Having slept for 11 seconds.
   *    Having slept for 12 seconds.
   *    Having slept for 13 seconds.
   *    Having slept for 14 seconds.
   *    Done
   *
   **/

  if (PHP_SAPI != "cli") die("Please run me from command line\n");
  require("../forkrun.php");

  ForkRun::addForkCallback(function() { /* reconnect to database here */ });

  $fr = new ForkRun();
  for ($i = 1; $i < 15; $i++)
  {
    $fr->addJob("job#$i", function($n) {
      sleep($n);
      print("Having slept for $n seconds.\n");
      return "Hello $n";
    }, $i);
  }
  $fr->execute(false);
  // We're doing some hardcore stuff here
  print("Waiting for 3 seconds, THEN reaping childs...\n");
  sleep(3);
  print("OK, NOW we are reaping for kids.\n");

  $data = $fr->waitForChildren();
  print("Done\n");

  /*
   */
