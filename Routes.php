<?php

\Central::addRoute("deploy", "handlers/System", "GET", "deploy", [PARAM_GET]);
\Central::addRoute("version", "handlers/System", "PUT", "getSpecific", [PARAM_RAW]);
\Central::addRoute("version", "handlers/System", "GET", "getVersions");
\Central::addRoute("status", "handlers/System", "GET", "status");
\Central::addRoute("minihud/inject", "handlers/MiniHud", "PUT", "build", [PARAM_JSON], \ENC_RAW);
\Central::addRoute("minihud/build", "handlers/MiniInjector", "PUT", "build", [], \ENC_RAW);

?>
