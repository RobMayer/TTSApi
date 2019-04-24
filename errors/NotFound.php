<?php

namespace errors {

  class NotFound extends \errors\Generic {

    public function __construct($path = "") {
		parent::__construct("Path '".$path."' is not valid", "NOT_FOUND", [], \SYS_LOG_ERR, 404, false);
    }

  }

}

?>
