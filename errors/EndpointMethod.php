<?php

namespace errors {

  class EndpointMethod extends \errors\Generic {

    public function __construct($path, $method) {
      parent::__construct("Method '".$method."' is not valid for endpoint '".$path."'", "BAD_ENDPOINT_METHOD", [], \SYS_LOG_ERR, 404, false);
    }


  }

}

?>
