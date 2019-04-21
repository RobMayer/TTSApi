<?php

const TTS_LOG_NON = 0;
const TTS_LOG_UNK = 1;
const TTS_LOG_FTL = 2;
const TTS_LOG_ERR = 4;
const TTS_LOG_WRN = 8;
const TTS_LOG_INF = 16;
const TTS_LOG_DBG = 32;
const TTS_LOG_ALL = 63;

const PARAM_URL = 1;
const PARAM_GET = 2;
const PARAM_JSON = 3;
const PARAM_RAW = 4;
const PARAM_MAN = 5;

const ENC_RAW = 1;
const ENC_JSON = 2;

const TTS_LOG_TERMS = [
	TTS_LOG_NON => "SCS",
	TTS_LOG_UNK => "UNK",
	TTS_LOG_FTL => "FTL",
	TTS_LOG_ERR => "ERR",
	TTS_LOG_WRN => "WRN",
	TTS_LOG_INF => "INF",
	TTS_LOG_DBG => "DBG",
];

const TTS_JSON_MODE = JSON_UNESCAPED_SLASHES;

const API_VERSION = "0.0.1a";

?>
