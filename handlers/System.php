<?php

/*

{
    "mini.injector":[
        {"version": "3.0.19rc4", "changes":[
            "Added changelog to API, laid groundwork for injector",
            "Something else here...",
        ]},
        {"version": "3.0.19rc3", "changes":[

        ]}
    ],
}


*/

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
            $result = [];
            foreach (UTILITY_VERSIONS as $theClass => $history) {
                $result[$theClass] = $history[0]['version'];
            }
            return $result;
        }

        public function getSpecific($input) {
            if (is_null($input) || $input == "") { throw new \errors\MalformedToken($input); }
            $data = \lib\JWT::decode($input);
            if (is_null($data)) { throw new \errors\MalformedToken($input); }
            if (!isset(UTILITY_VERSIONS[$data['class']])) { throw new \errors\UnknownTTSClass($data['class']); }

            $newVersion = UTILITY_VERSIONS[$data['class']][0]['version'];
            $changes = UTILITY_VERSIONS[$data['class']][0]['changes'] ?? [];

            return [
        		'update' => version_compare($data['version'], $newVersion, '<'),
        		'current' => $data['version'],
        		'new' => $newVersion,
                'changes' => $changes
        	];

        }

    }
}

?>
