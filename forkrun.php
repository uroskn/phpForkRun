<?php

  /**
   *  Enables PHP to do multiple things at once by forking as often as you
   *  you want. It can also pass results back to it's parent trough sockets.
   *  
   *  Note that STDERR is not handled.
   *  
   *  @Author Uroš Knupleš <uros@knuples.net>
   **/
  class ForkRun 
  {
    static protected $events;
    
    static public function addForkCallback($callback)
    {
      if (!is_callable($callback)) throw new Exception("This is not a callback");
      if (!is_array(self::$events)) self::$events = array();
      self::$events[] = $callback;
    }
    
    static protected function callForkCallbacks($in_child)
    {
      if (!is_array(self::$events)) return;
      foreach (self::$events as $event) $event($in_child);
    }
   
    protected $jobs;
    protected $rjobs;
  
    /**
     *  Prepares file descriptor set for select call
     **/
    protected function prepareFD($jsets)
    {
      $fds = array();
      foreach ($jsets as $process) 
      {
        if (is_resource($process[1])) $fds[] = $process[1];
      }
      return $fds;
    }
    
    /**
     *  Given socket file descriptor, it figures out to witch process
     *  it belongs
     **/
    protected function matchFD($jobs, $fd)
    {
      foreach ($jobs as $jid => $job) 
        if ($fd === $job[1]) return $jid;
      throw new Exception("Invalid FD provided, not in jobs table!");
    }
    
    /** 
     *  Waits for processes and returns their exit codes. Hang = is this
     *  call allowed to be blocked or not?
     **/
    protected function waitProcess(&$results, $hang = false)
    {
      if (!count($this->rjobs)) return false;
      $retcode = $pid = nulL;
      $pid = pcntl_wait($retcode, ((!$hang) ? WNOHANG : null));
      if ((!$pid) || (($hang) && ($pid == -1))) return false;
      foreach ($this->rjobs as $jid => $job)
      {
        if ($job[0] == $pid)
        {
          $results[$jid]["retcode"] = $retcode;
          return true;
        }
      }
      return false; // False alarm, someone playing with SIGSTOP?
    }
  
    function __construct()
    {
      $this->jobs  = array();
      $this->rjobs = null;
    }
    
    /**
     *  Adds a new job to the working set. Callback will be called, when 
     *  execute() is called, each from it's own process.
     *  
     *  Callback can either echo it's output, in which case it's passed
     *  directly to caller, or it may return something, which is then serialized
     *  and desiralized at parent as well. This means howerver, that function in
     *  this case *MUST NOT* produce any output. If this is concern, function
     *  may always call ob_clean() before it returns.
     *  
     *  @param string $name        Job name. This name will be then used on results. Job name
     *                             must be unique. If second name is passed with a job that
     *                             is already registered, previous job entry is overwriten.
     *  @param callable $callback  Function to be executed when this job forks.
     *  @param mixed    $data      (Optional) Optional data to be passed into this specific job.
     *                             (anonymous functions can for example still use use() 
     *                             keyword).
     **/
    function addJob($name, $callback, $data = array())
    {
      if (!is_callable($callback)) throw new Exception("Errrr, callback is not callable!");
      $this->jobs[$name] = array($callback, $data);
    }

    /**
     *  Removes all queued jobs. This method is also called after each execute()
     *  call. Alternativly you can also remove specific job by passing it's name.
     *  @param string $name (Optional) Job name to be removed.
     **/
    function removeJob($name = null)
    {
      if ($this->rjobs) throw new Exception("Do not remove jbos, while they are running!");
      if ($name === null) $this->jobs = array();
      else unset($this->jobs[$name]);
    }
    
    /**
     *  Executes all callback functions each in it's own process and if set so,
     *  waits for it's results.
     *  
     *  IMPORTANT: DO *NOT* FORGET TO REOPEN DATABASE CONNECTION IN CHILD.
     *             NOT DOING SO, MIGHT RESULT IN SOME SERIOUS AND HARD TO
     *             DEBUG BUGS!
     *  
     *  @param boolean $wait (Optional) Should parent execution be halted, untill
     *                       childs complete? If false is given, caller
     *                       must call waitForChildren() manually.
     *
     *                       Defaults to true.
     *  @returns Ouput from children, or NULL if wait was set to false.
     **/
    function execute($wait = true)
    {
      if (!is_null($this->rjobs)) throw new Exception("One job list is already running");
      $this->rjobs = array();
      foreach ($this->jobs as $jid => $job)
      {
        $pair = array();
        if (socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $pair) === false)
          throw new Exception("Cannot create new communication socket pair");
        $pid = pcntl_fork();
        if ($pid == -1) throw new Exception("Cannot fork, wtf?");
        if (!$pid) // We're child. Our job is quite simple, actually. :)
        {
          socket_close($pair[0]);
          self::callForkCallbacks(true);
          $data = call_user_func_array($job[0], (array)$job[1]);
          socket_write($pair[1], base64_encode(serialize($data)));
          socket_close($pair[1]);
          exit(0);
        }
        else 
        {
          socket_close($pair[1]);
          socket_set_nonblock($pair[0]);
          $this->rjobs[$jid] = array($pid, $pair[0]);
        }
      }
      self::callForkCallbacks(false);
      if ($wait) return $this->waitForChildren();
      return null;
    }
    
    /**
     *  Waits for children and returns their output
     *  @returns Output from children
     **/
    function waitForChildren()
    {
      if (is_null($this->rjobs)) return null;
      $results = array();
      // Now we wait for the jobs to complete.
      while (true)
      {
        $fds = $this->prepareFD($this->rjobs);
        if (!count($fds)) break;
        $r = $e = null;
        if (socket_select($fds, $r, $e, null) === false)
          throw new Exception("Socket select failed, wtf?");
        foreach ($fds as $fd)
        {
          $match = $this->matchFD($this->rjobs, $fd);
          $data = socket_read($fd, 1024);
          if ($data) @$results[$match]["data"] .= $data;
          else 
          {
            socket_close($this->rjobs[$match][1]);
            $this->rjobs[$match][1] = null;
          }
        }
        // And handling of dead childs...
        while ($this->waitProcess($results, false));
      }
      while ($this->waitProcess($results, true));
      // Process incoming data.
      foreach ($results as $key => &$result) 
        $result["data"] = unserialize(base64_decode($result["data"]));
      $this->rjobs = null;
      $this->removeJob();
      return $results;
    }
    
  }

