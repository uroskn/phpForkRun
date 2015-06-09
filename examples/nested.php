<?php

  /**
   *  Nested: We can also nest them!
   *  Requires: pstree to be intalled
   *
   *  PRogram output:
   *     $ php nested.php
   *     php(17452)-+-php(17454)-+-php(17456)
   *                |            `-php(17459)
   *                |-php(17455)-+-php(17466)
   *                |            |-php(17467)
   *                |            |-php(17470)
   *                |            |-php(17471)
   *                |            `-php(17472)
   *                |-php(17457)---php(17469)
   *                |-php(17458)-+-php(17464)
   *                |            `-php(17465)
   *                |-php(17460)-+-php(17462)
   *                |            |-php(17463)
   *                |            `-php(17468)
   *                |-php(17461)---php(17473)
   *                `-sh(17474)---pstree(17475)
*
   **/

  if (PHP_SAPI != "cli") die("Please run me from command line\n");
  require("../forkrun.php");

  // Random generator has to be resseded with each fork, otherwise
  // all childs will generate same random numbers.
  ForkRun::addForkCallback(function() { srand(getmypid()); });

  $fr = new ForkRun();
  for ($i = 0; $i < rand(3,10); $i++)
  {
    $fr->addJob("Job#$i", function() {
      $moarforks = new ForkRun();
      for ($i = 0; $i < rand(1,5); $i++)
        $moarforks->addJob("moarjobs#$i", "sleep", 5);
      $moarforks->execute();
    });
  }
  $fr->execute(false);
  sleep(1);
  passthru("pstree -p ".getmypid());
  $fr->waitForChildren();
