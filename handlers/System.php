<?php

namespace handlers {
    class System {

        public function status() {
            return \TTS_MODE != "MAINT";
        }

        public function getVersions() {
            return UTILITY_VERSIONS;
        }

        public function getSpecific($input) {
            if (is_null($input) || $input == "") { throw new \errors\MalformedToken($input); }
            $data = \lib\JWT::decode($input);
            if (is_null($data)) { throw new \errors\MalformedToken($input); }
            if (!isset(UTILITY_VERSIONS[$data['class']])) { throw new \errors\UnknownTTSClass($data['class']); }

            return [
        		'update' => version_compare($data['version'], UTILITY_VERSIONS[$data['class']], '<'),
        		'current' => $data['version'],
        		'new' => UTILITY_VERSIONS[$data['class']],
        	];

        }

    }
}

?>
