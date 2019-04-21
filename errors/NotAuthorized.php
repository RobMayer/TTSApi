<?php

namespace errors {

  class NotAuthorized extends \errors\Generic {

    public function __construct() {
        parent::__construct("You are not authorized to perform this action", "NOT_AUTHORIZED", [], \TTS_LOG_ERR, 403);
    }


  }

}

?>
