<?php

namespace errors {

  class InputEndpoint extends \errors\Generic {

    public function __construct($path = "") {
		parent::__construct("Endpoint '".$path."' is not valid", "INVALID_ENDPOINT", [], \TTS_LOG_ERR, 404, false);
    }

  }

}

?>
