<?php

namespace lib {

  class Logger {

	private static $levelmap = [
		\TTS_LOG_UNK => "UNK",
		\TTS_LOG_FTL => "FTL",
		\TTS_LOG_ERR => "ERR",
		\TTS_LOG_WRN => "WRN",
		\TTS_LOG_INF => "INF",
		\TTS_LOG_DBG => "DBG",
	];

    public static function log($msg, $level, $status, $file = null, $line = null) {

		if (\TTS_LOG_LEVEL & $level == $level) {
			if (is_array($msg)) { $msg = json_encode($msg, JSON_PRETTY_PRINT); }
			$trace = debug_backtrace(false);
			$file = $file ?? $trace[1]['file'] ?? "unknown";
			$line = $line ?? $trace[1]['line'] ?? "??";
			$now = date("Y-m-d H:i:s");
			file_put_contents(\PATH_LOGS."TTS_".date('Ym').".log", "[".self::$levelmap[$level].":".$status."] ".$now." <".$file." (".$line.")> ".$msg."\n", FILE_APPEND);
		}

    }

    public static function debug($msg, $status = "200") { self::log($msg, \TTS_LOG_DBG, $status); }
    public static function info($msg, $status = "200") { self::log($msg, \TTS_LOG_INF, $status); }
    public static function warning($msg, $status = "200") { self::log($msg, \TTS_LOG_WRN, $status); }
    public static function error($msg, $status = "500") { self::log($msg, \TTS_LOG_ERR, $status); }
    public static function fatal($msg, $status = "500") { self::log($msg, \TTS_LOG_FTL, $status); }
    public static function unknown($msg, $status = "520") { self::log($msg, \TTS_LOG_UNK, $status); }

  }

}

?>
