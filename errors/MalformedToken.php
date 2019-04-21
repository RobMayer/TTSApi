<?php

namespace errors {

  class MalformedToken extends \errors\Generic {

    public function __construct($input) {
      parent::__construct("body must be a valid TRH-type token", "MALFORMED_TOKEN", ["token" => $input], \TTS_LOG_ERR, 400, false);
    }


  }

}

?>
