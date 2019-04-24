<?php

namespace errors {

  class EndpointLoad extends \errors\Generic {

    public function __construct($class) {
      parent::__construct("Cannot load endpoint class '".$class."'", "BAD_ENDPOINT_CLASS", [], \SYS_LOG_FTL, 500);
    }


  }

}

?>
