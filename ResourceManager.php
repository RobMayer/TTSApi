<?php

class ResourceManager {

    private static $loadedResources = [];

    public static function get($className) {
        $path = pathinfo($className);
        $cls = str_replace("/", "\\", $className);
        if (!array_key_exists($cls, self::$loadedResources)) {
            if (!file_exists(\PATH_ROOT."".$path['dirname']."/".$path['basename'].".php")) {
                throw new \errors\EndpointLoad($cls);
            }
            include(PATH_ROOT."".$path['dirname']."/".$path['basename'].".php");
            self::$loadedResources[$cls] = new $cls();
        }
        return self::$loadedResources[$cls];
    }

}

?>
