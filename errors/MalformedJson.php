<?php

namespace errors {

  class MalformedJson extends \errors\Generic {

    public function __construct() {
      parent::__construct("body must be a valid JSON object", "MALFORMED_JSON", [], \SYS_LOG_ERR, 400, false);
    }


  }

}

?>
