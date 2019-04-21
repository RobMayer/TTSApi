<?php

class Central {

    private static $routeRegistry = [];
    private static $messages = [];
    private static $errors = [];
    private static $debugs = [];
    private static $warnings = [];
    private static $payload;
    private static $enctype;

    public static function startup() {

    }

    public static function handleRequest() {
        if (!isset($_GET['path']) || strlen($_GET['path']) == 0) { $_GET['path'] = "status"; }
        $_GET['path'] = trim($_GET['path'], "/");
        $foundRoute = false;
        $_PATH = $_GET['path'];
        unset($_GET['path']);

        foreach (self::$routeRegistry as $k => $v) {
            $urlParams = [];
            $foundRoute = preg_match("~^".$k."$~", $_PATH, $urlParams);
            if ($foundRoute) {
                array_shift($urlParams);
                if (!isset($v[HTTP_METHOD])) { throw new \errors\EndpointMethod($_PATH, HTTP_METHOD); }
                $clsName = $v[HTTP_METHOD][0];
                $manualParams = [];
                preg_match("~^([\w]*)(?:\(([\w\*,\\\\]*)\))?$~", $v[HTTP_METHOD][1], $manualParams);
                array_shift($manualParams);
                $fnName = array_shift($manualParams);
                if (!empty($manualParams)) {
                    $tmp = array_shift($manualParams);
                    $manualParams = preg_split("~".REGEX_URL_ARGSEP."~", $tmp);
                }
                $paramFlags = $v[HTTP_METHOD][2];
                $enc = $v[HTTP_METHOD][3];
                $theClass = \ResourceManager::get($clsName);
                if (!method_exists($theClass, $fnName)) { throw new \errors\EndpointFunction($clsName, $fnName); }
                $params = array();
                if (!empty($paramFlags)) {
                    foreach ($paramFlags as $f) {
                        if ($f == PARAM_MAN) {
                            if (!empty($manualParams)) {
                                $params[] = array_shift($manualParams);
                            } else {
                                throw new \errors\EndpointParamMapping($clsName, "Manual");
                            }
                        } else if ($f == PARAM_URL) {
                            if (!empty($urlParams)) {
                                $params[] = array_shift($urlParams);
                            } else {
                                throw new \errors\EndpointParamMapping($clsName, "Capture");
                            }
                        } else if ($f == PARAM_GET) {
                            $params[] = $_GET;
                        } else if ($f == PARAM_JSON) {
                            $input = json_decode(file_get_contents("php://input"), true);
                            if (is_null($input)) { throw new \errors\MalformedJson(); }
                            $params[] = $input;
                        } else if ($f == PARAM_RAW) {
                            $params[] = file_get_contents("php://input");
                        } else {
                            throw new \errors\EndpointParamMapping($clsName, "Unknown");
                        }
                    }
                }
                self::setDefaultHeaders();
                self::$payload = call_user_func_array(array($theClass, $fnName), $params);
                self::$enctype = $enc;
                break;
            }
        }
        if (!$foundRoute) {
            throw new \errors\InputEndpoint($_PATH);
        }
        return $foundRoute;
    }

    public static function hasWarnings() {
        return !empty(self::$warnings);
    }

    public static function hasErrors() {
        return !empty(self::$errors);
    }

    public static function warning($warning) {
        if (is_array($warning)) {
            self::$warnings = array_merge(self::$warnings, $warning);
        } else {
            self::$warnings[] = $warning;
        }
    }

    public static function message($text) {
        if (is_array($text)) {
            foreach ($text as $t) {
                self::$messages[] = [
                    "level" => "INF",
                    "text" => $t
                ];
            };
        } else {
            self::$messages[] = [
                "level" => "INF",
                "text" => $text
            ];
        }
    }

    public static function debug($text) {
        if (TTS_MODE == "DEV") {
            if (is_array($text)) {
                foreach ($text as $t) {
                    self::$debugs[] = [
                        "level" => "DBG",
                        "text" => $t
                    ];
                };
            } else {
                self::$debugs[] = [
                    "level" => "DBG",
                    "text" => $text
                ];
            }
        }
    }

    public static function error($error) {
        if (is_array($error)) {
            self::$errors = array_merge(self::$errors, $error);
        } else {
            self::$errors[] = $error;
        }
    }

    public static function setDefaultHeaders() {
        header("Access-Control-Allow-Origin: *");
        header("X-Clacks-Overhead:GNU Terry Pratchett");
        header("X-Powered-By: Robinomicon/".\API_VERSION);
    }

    public static function addRoute($path, $class, $method, $function, $flags = [], $enc = \ENC_JSON) {
        if (is_null($flags)) { $flags = []; }
        if (!is_array($flags)) { $flags = [$flags]; }
        $method = strtoupper($method);
        if (!isset(self::$routeRegistry[$path])) {
            self::$routeRegistry[$path] = [];
        }
        self::$routeRegistry[$path][$method] = [$class, $function, $flags, $enc];
    }

    public static function respond() {
        if (self::$enctype == \ENC_RAW) {
            self::respondRaw();
        } else {
            self::respondJson();
        }
    }

    private static function respondJson() {
        $response = [];
        if (self::hasErrors()) {
            $status = 200;
            $response['errors'] = [];
            foreach (self::$errors as $err) {
                $theErr = [];
                $errType = "UNKNOWN";
                $errMsg = "Unknown error has occured: '".$err."'";
                $errData = [];
                $errLevel = "UNK";
                $errLine = "??";
                $errFile = "Unknown";
                $errTrace = [];
                if ($err instanceof \errors\Generic) {
                    $errType = $err->getType();
                    $errMsg = $err->getMessage();
                    $errData = $err->getData();
                    $errLevel = $err->getLevel();
                    $errLine = $err->getLine();
                    $errFile = $err->getFile();
                    $errTrace = $err->getTrace();
                    $status = max($status, $err->getStatus());
                }
                $theErr['type'] = $errType;
                $theErr['text'] = $errMsg;
                $theErr['level'] = $errLevel;
                $theErr['data'] = $errData;
                if (TTS_MODE == "DEV") {
                    $theErr['debug'] = [
                        "file" => $errFile,
                        "line" => $errLine,
                        "trace" => $errTrace,
                    ];
                }
                $response['errors'][] = $theErr;
            }
            http_response_code($status);
        } else {
            if (!is_array(self::$payload) || !array_key_exists("result", self::$payload)) {
                $response['result'] = self::$payload;
            } else {
                $response = self::$payload;
            }
        }
        if (!empty(self::$warnings)) {
            $response['warnings'] = self::$warnings;
        }
        if (!empty(self::$messages)) {
            $response['messages'] = self::$messages;
        }
        if (!empty(self::$debugs) && TTS_MODE == "DEV") {
            $response['debug'] = self::$debugs;
        }
        if (isset($_GET['pass'])) {
            $response['pass'] = $_GET['pass'];
        }
        $jsonOut = json_encode($response, TTS_JSON_MODE);
        if ($jsonOut === false) {
            throw new \errors\Generic("Unable to parse output to JSON for ".$clName."->".$fnName, [], "OUTPUT_JSON_ERROR", 500);
        }
        self::setDefaultHeaders();
        header("Content-Type: application/json");
        header("Content-Length: ".strlen($jsonOut));
        echo $jsonOut;
    }

    public static function respondRaw() {
        $response = self::$payload;
        if (self::hasErrors()) {
            $status = 200;
            $msg = "";
            $messages = [];
            foreach (self::$errors as $err) {
                if (is_string($err)) {
                    $messages[] = "[FTL] Unknown error has occured: '".$err."'";
                } elseif ($err instanceof \errors\Generic) {
                    $messages[] = "[".\TTS_LOG_TERMS[$err->getLevel()]."] ".$err->getMessage();
                    $status = max($status, $err->getStatus());
                } else {
                    $messages[] = "[FTL] Unknown error has occured.";
                }
            }
            http_response_code($status);
            $response = implode("\n", $messages);
        } else {
            if (!is_null(self::$payload)) {
                $response = "[SCS] Success\n".self::$payload;
            }
        }
        self::setDefaultHeaders();
        header("Content-Type: text/plain");
        header("Content-Length: ".strlen($response));
        echo $response;

    }

}



?>
