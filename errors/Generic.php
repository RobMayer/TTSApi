<?php

namespace errors {

  class Generic extends \Exception {

    private $type;
    private $data;
    private $level;
    private $status;

    public function __construct($message, $type, $data = [], $level = TTS_LOG_UNK, $status = 500, $log = true) {
		if (is_array($message)) { $message = json_encode($message, true); }
		parent::__construct($message);
		$this->level = $level;
		$this->type = $type;
		$this->data = $data;
		$this->status = $status;

		if ($log && \TTS_LOG_LEVEL & $level == $level) {
			\lib\Logger::log($message, $level, $status);
		}

    }

    public function getType() { return $this->type; }
    public function getData() { return $this->data; }
    public function getLevel() { return $this->level; }
    public function getStatus() { return $this->status; }

  }
}

?>
