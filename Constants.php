<?php

const SYS_LOG_NON = 0;
const SYS_LOG_UNK = 1;
const SYS_LOG_FTL = 2;
const SYS_LOG_ERR = 4;
const SYS_LOG_WRN = 8;
const SYS_LOG_INF = 16;
const SYS_LOG_DBG = 32;
const SYS_LOG_ALL = 63;

const PARAM_URL = 1;
const PARAM_GET = 2;
const PARAM_JSON = 3;
const PARAM_RAW = 4;
const PARAM_MAN = 5;

const ENC_RAW = 1;
const ENC_JSON = 2;

const SYS_LOG_TERMS = [
	SYS_LOG_NON => "SCS",
	SYS_LOG_UNK => "UNK",
	SYS_LOG_FTL => "FTL",
	SYS_LOG_ERR => "ERR",
	SYS_LOG_WRN => "WRN",
	SYS_LOG_INF => "INF",
	SYS_LOG_DBG => "DBG",
];

const SYS_JSON_MODE = JSON_UNESCAPED_SLASHES;



?>
