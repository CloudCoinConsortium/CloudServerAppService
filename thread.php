<?php

namespace CloudService;

class Thread {
	var $pref ; // process reference
	var $pipes; // stdio
	var $buffer; // output buffer
	var $output;
	var $error;
	var $timeout;
	var $start_time;
	
	function Thread($timeout) {
		$this->pref = 0;
		$this->buffer = "";
		$this->pipes = (array)NULL;
		$this->output = "";
		$this->error="";
		$this->start_time = time();
		$this->timeout = $timeout;
	}
	static function Create ($command, $timeout) {
		$t = new Thread($timeout);
		$descriptor = array (0 => array ("pipe", "r"), 1 => array ("pipe", "w"), 2 => array ("pipe", "w"));
		//Open the resource to execute $command
		$t->pref = proc_open($command,$descriptor,$t->pipes);
		//Set STDOUT and STDERR to non-blocking 
		stream_set_blocking ($t->pipes[1], 0);
		stream_set_blocking ($t->pipes[2], 0);
		return $t;
	}
	//See if the command is still active
	function isActive () {
		$this->buffer .= $this->listen();
		$f = stream_get_meta_data ($this->pipes[1]);
		return !$f["eof"];
	}
	//Close the process
	function close () {
		$r = proc_close ($this->pref);
		$this->pref = NULL;
		return $r;
	}
	//Send a message to the command running
	function tell ($thought) {
		fwrite ($this->pipes[0], $thought);
	}
	//Get the command output produced so far
	function listen () {
		$buffer = $this->buffer;
		$this->buffer = "";
		while ($r = stream_get_contents ($this->pipes[1])) {
			$buffer .= $r;
		}
		return $buffer;
	}
	
	//Get the status of the current runing process
	function getStatus(){
		return proc_get_status($this->pref);
	}
	
	//See if the command is taking too long to run (more than $this->timeout seconds)
	function isBusy(){
		return ($this->start_time>0) && ($this->start_time+$this->timeout<time());
	}
	
	//What command wrote to STDERR
	function getError () {
		$buffer = "";
		while ($r = fgets ($this->pipes[2], 1024)) {
			$buffer .= $r;
		}
		return $buffer;
	}
	
	function getDurationSeconds() {
		return time() - $this->start_time;
	}
}
class Future {
	var $taskId;
	var $command;
	var $result;
	var $error;
	var $finished = false;
	var $started = false;
	var $thread;
	var $callback;
	var $executor;

	function __construct($taskId, $command, $callback, $executor) {
		$this->taskId = $taskId;
		$this->command = $command;
		$this->callback = $callback;
		$this->executor = $executor;
	}
	
	function startup($timeout) {
		$this->started = true;
		$this->thread = Thread::create($this->command, $timeout);
	}
	
	function end($result, $error) {
		$this->result = $result;
		$this->error = $error;
		$this->finished = true;
		call_user_func($this->callback, $this->result, $this->error);
	}
}
//Wrapper for Thread class
class ThreadPool{
	var $poolSize;
	var $defaultTimeout;
	var $futures = array();
	var $pipes = array();
	var $queue = array();
	var $output;
	var $error;
	var $index = 0;
	
	function __construct($size, $timeout) {
		$this->poolSize = $size;
		$this->defaultTimeout = $timeout;
	}
	
	function scheduleCommand($executor, $command, $callback) {
		$future = new Future(""+$this->index++, $command, $callback, $executor);
		$this->futures[$future->taskId] = $future;
		$this->output[$future->taskId] = "";
		$this->error[$future->taskId] = "";
		if (count($this->pipes) >= $this->poolSize)
			array_push($this->queue, $future);
		else
			$this->scheduleNow($future);
		return $future;
	}
	
	function scheduleNow($future) {
		$future->startup($this->defaultTimeout);
		$this->pipes[$future->taskId] = $future->thread->pipes[1];
		echo 'thread '.$future->taskId." started, command:".$future->command."\n";
	}
	
	function loop(){
		while (count($this->pipes)>0){
			$this->runOnce();
			while(count($this->pipes)<$this->poolSize && count($this->queue)>0) {
				$future = array_shift($this->queue);
				$this->scheduleNow($future);
			}
		}
	}
	
	function runOnce() {
		$streams = $this->pipes;
		if (count($streams)>0){
			$read = $streams;
			$write = null;
			$except = null;
			stream_select($read, $write, $except, 1);
			foreach ($read as $r) {
				$id = array_search($r, $streams);
				$thread = $this->futures[$id]->thread;
				if ($thread->isActive()) {
					$this->output[$id] .= $thread->listen();
					if ($thread->isBusy()) {
						$thread->close();
						unset($this->pipes[$id]);
						$this->futures[$id]->end($this->output[$id], "");
						unset($this->output[$id]);
						echo "thread $id timeout, duration ".$this->futures[$id]->thread->getDurationSeconds()."s, command:".$this->futures[$id]->command."\n";
					}
				} else {
					$this->output[$id] .= $thread->listen();
					$this->error[$id] .= $thread->getError();
					$thread->close();
					unset($this->pipes[$id]);
					$this->futures[$id]->end($this->output[$id], $this->error[$id]);
					unset($this->output[$id]);
					unset($this->error[$id]);
					echo "thread $id completed, duration ".$this->futures[$id]->thread->getDurationSeconds()."s, command:".$this->futures[$id]->command."\n";
				}
				
			}
		}	
	}
}
class TaskExecutor {
	var $threadPool;
	var $callback;
	
	function __construct($callbackFunc, $maxThreads=1, $taskTimeout=120) {
		if ($taskTimeout <= 0)
			$taskTimeout = 2147483647;	//~never timeout
		if ($maxThreads <= 0)
			$maxThreads = 1;
		if ($maxThreads >= 250)
			$maxThreads = 250;
		$this->threadPool = new ThreadPool($maxThreads, $taskTimeout);
		$this->callback = $callbackFunc;
	}
	
	public function executeAsync($task, $private) {
		$this->threadPool->scheduleCommand($this, $task, $this->callback);
	}
	
	public function executeWaitTerminal($task) {
		if (count($this->threadPool->pipes) > 0)
			$this->waitForAllTerminal();    //execute after current tasks end
		$future = $this->threadPool->scheduleCommand($this, $task, $this->callback);
		while (!$future->finished) {
			$this->threadPool->runOnce();
		}
	}
	
	public function waitForAllTerminal() {
		$this->threadPool->loop();
	}
}
?>
