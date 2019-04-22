<?php

namespace handlers {
    class System {

        public function deploy($input) {
            if (isset($input['key'])) {
                if ($input['key'] == \DEPLOY_KEY) {
                    $reset = explode("\n", shell_exec("cd ".rtrim(\PATH_ROOT, "/")." && git reset --hard 2>&1"));
                    $pull = explode("\n", shell_exec("cd ".rtrim(\PATH_ROOT, "/")." && git pull 2>&1"));
                    return array_filter(array_merge($reset,$pull));
                } else {
                    throw new \errors\NotAuthorized();
                }
            } else {
                throw new \errors\NotFound();
            }
        }

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
