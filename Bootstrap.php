<?php

include_once("Constants.php");
include_once("Config.php");

define("UTILITY_VERSIONS", json_decode(file_get_contents(\PATH_ROOT."utility_versions.json"), true));

define("HEADERS", getallheaders());
define("HTTP_METHOD", strtoupper($_SERVER['REQUEST_METHOD']));

spl_autoload_register(function($theClass) {
    $path = rtrim(str_replace("\\", "/", $theClass), "/");
    if (file_exists(\PATH_ROOT.$path.".php")) {
        include_once(\PATH_ROOT.$path.".php");
    } else {
        header("Access-Control-Allow-Methods: *");
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Allow-Headers: Origin, Content-Type");
        header("X-Clacks-Overhead:GNU Terry Pratchett");
        header("X-Powered-By: Robinomicon/".\SYS_VERSION);
        http_response_code(500);

        $trace = debug_backtrace();

        file_put_contents(PATH_LOGS.SYS_LOG_PREFIX."_".date('Ym').".log", "[FTL:000] ".date('')." <".$trace[1]['file']." (".$trace[1]['line'].")> Problem loading class '".$theClass."'\n", FILE_APPEND);

        die();
    }
});

set_error_handler(function($code, $msg, $file, $line) {

	$now = date("Y-m-d H:i:s");
	$level = SYS_LOG_UNK;
	switch ($code) {
		case E_ERROR:
		case E_PARSE:
		case E_CORE_ERROR:
		case E_COMPILE_ERROR:
		$level = SYS_LOG_FTL; break;
		case E_USER_ERROR:
		case E_RECOVERABLE_ERROR:
		$level = SYS_LOG_FTL; break;
		case E_NOTICE:
		case E_WARNING:
		case E_CORE_WARNING:
		case E_COMPILE_WARNING:
		case E_USER_WARNING:
		case E_USER_NOTICE:
		$level = SYS_LOG_WRN; break;
		case E_STRICT:
		case E_USER_DEPRECATED:
		case E_DEPRECATED:
		$level = SYS_LOG_INF; break;
		default:
		$level = SYS_LOG_UNK; break;
	}

	$trace = debug_backtrace();

	if (SYS_LOG_LEVEL & $level == $level) {
		file_put_contents(PATH_LOGS.SYS_LOG_PREFIX."_".date('Ym').".log", "[".SYS_LOG_TERMS[$level].":000] ".$now." <".$file." (".$line.")> ".$msg."\n", FILE_APPEND);
	}

	header("Access-Control-Allow-Origin: *");
	header("Content-Type: application/json");
	header("X-Clacks-Overhead: GNU Terry Pratchett");
	header("X-Powered-By: Robinomicon/".\SYS_VERSION);
	http_response_code(500);

	if (SYS_SEND_LEVEL & $level == $level) {

		$response = [
			"errors" => [
				[
					"type" => "INTERNAL",
					"level" => SYS_LOG_TERMS[$level],
					"text" => SYS_MASK_LEVEL & $level == $level ? "There was a problem server-side. The error has been logged and ThatRobHuman has been notified." : $msg,
					"time" => $now,
					"data" => [],
				]
			]
		];

		if (SYS_MODE == "DEV") {
			$response['errors'][0]['debug'] = [
				"file" => $file,
				"line" => $line,
				"trace" => debug_backtrace(),
			];
		}
		echo json_encode($response, SYS_JSON_MODE);
	}

	die();


}, E_ALL);


set_exception_handler(function($e) {

	$now = date("Y-m-d H:i:s");

	header("Access-Control-Allow-Methods: OPTIONS");
	header("Access-Control-Allow-Origin: *");
	header("Content-Type: application/json");
	header("X-Powered-By: Robinomicon/".\SYS_VERSION);
	header("X-Clacks-Overhead:GNU Terry Pratchett");

	$type = "INTERNAL";
	$msg = $e->getMessage();
	$status = 500;
	$level = SYS_LOG_UNK;
	$data = [];

	if ($e instanceof \errors\Generic) {
		$msg = $e->getMessage();
		$status = $e->getStatus();
		$type = $e->getType();
		$data = $e->getData();
		$level = $e->getLevel();
		if (SYS_LOG_LEVEL & $level == $level) {
			\lib\Logger::log($msg, $level, $status, $e->getFile(), $e->getLine());
		}
	} else {
		if (SYS_LOG_LEVEL & $level == $level) {
			\lib\Logger::log($msg, $level, $status, $e->getFile(), $e->getLine());
		}
	}

	http_response_code($status);

	if (SYS_SEND_LEVEL & $level == $level) {
		$response = [
			"level" => SYS_LOG_TERMS[$level],
			"time" => $now,
			"data" => $data,
		];
		if (SYS_MODE == "DEV") {
			$response['type'] = $type;
			$response['text'] = $msg;
			$response['debug'] = [
				"file" => $e->getFile(),
				"line" => $e->getLine(),
				"trace" => $e->getTrace(),
			];
		} else {
			$response['type'] = (SYS_MASK_LEVEL & $level == $level ? "INTERNAL" : $type);
			$response['text'] = (SYS_MASK_LEVEL & $level == $level ? "There was a problem server-side. The error has been logged and ThatRobHuman has been notified" : $msg);
		}
		echo json_encode(['errors' => [$response]], SYS_JSON_MODE);
	}

	die();

});

if (!isset($_GET['path']) || strlen($_GET['path']) == 0 || $_GET['path'] == "/") {
	throw new \errors\NotFound();
} else {
	$_GET['path'] = trim($_GET['path'], "/");
}

include_once(PATH_ROOT."Routes.php");

\Central::startup();
\Central::handleRequest();
\Central::respond();

?>
