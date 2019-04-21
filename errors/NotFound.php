<?php

namespace errors {

  class NotFound extends \errors\Generic {

    public function __construct($path = "") {
		parent::__construct("Path '".$path."' is not valid", "NOT_FOUND", [], \TTS_LOG_ERR, 404, false);
    }

  }

}

?>
