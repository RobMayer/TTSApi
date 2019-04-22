<?php

namespace lib {
    class VersionManager {
        public static function token($item) {
            return \lib\JWT::encode([
                'class' => $item,
                'version' => \UTILITY_VERSIONS[$item][0]['version'],
            ]);
        }
    }
}

?>
