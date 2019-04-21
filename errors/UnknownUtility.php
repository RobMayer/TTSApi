<?php

namespace errors {

  class UnknownUtility extends \errors\Generic {

    public function __construct($input) {
      parent::__construct("The utility provided is not a known utility", "UNKNOWN_UTILITY", ["provided_utility" => $input], \TTS_LOG_ERR, 400, false);
    }


  }

}

?>
