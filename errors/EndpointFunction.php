<?php

namespace errors {

  class EndpointFunction extends \errors\Generic {

    public function __construct($className, $funcName) {
      parent::__construct("Cannot find function '".$funcName."' on endpoint class '".$className."'", "BAD_ENDPOINT_FUNC", [], \TTS_LOG_FTL, 500);
    }


  }

}

?>
