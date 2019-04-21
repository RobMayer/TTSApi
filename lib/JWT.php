<?php

namespace lib {

    class JWT {

        public static function encode($obj, $secret = \SALT) {
            $header = json_encode(['typ' => "TRH", "alg" => "HS256"]);
            $payload = json_encode($obj);
            $b64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
            $b64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
            $signature = hash_hmac('sha256', $b64Header.".".$b64Payload, $secret, true);
            $b64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
            return $b64Header.".".$b64Payload.".".$b64Signature;
        }

        public static function decode($token, $secret = \SALT) {
            $parts = explode(".", $token);
            if (hash_hmac('sha256', $parts[0].".".$parts[1], $secret, true) != base64_decode(str_replace(['-', '_', ''], ['+', '/', '='], $parts[2]))) {
                return null;
            }
            return json_decode(base64_decode(str_replace(['-', '_', ''], ['+', '/', '='], $parts[1])), true);
        }

        public static function verify($token, $secret = \SALT) {
            $parts = explode(".", $token);
            return hash_hmac('sha256', $parts[0].".".$parts[1], $secret, true) == base64_decode(str_replace(['-', '_', ''], ['+', '/', '='], $parts[2]));
        }

    }

}

?>
