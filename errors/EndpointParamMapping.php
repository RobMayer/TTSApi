<?php

namespace errors {

  class EndpointParamMapping extends \errors\Generic {

    public function __construct($className, $mapType) {
      parent::__construct("Bad '".$mapType."' on endpoint class '".$className."'", "BAD_ENDPOINT_PARAM_MAPPING", [], \TTS_LOG_FTL, 500);
    }


  }

}

?>
