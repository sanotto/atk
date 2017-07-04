<?php

namespace Sintattica\Atk\Core;

class Atk
{
    const VERSION = 'v9.2.0-dev';

    public static $ATK_VARS;

    public static function &createAtkVarsFromGlobals()
    {
        //decode data
        self::$ATK_VARS = array_merge($_GET, $_POST);
        Tools::atkDataDecode(self::$ATK_VARS);

        // inject $_FILES
        $atkfiles = $_FILES;
        Tools::atkDataDecode($atkfiles);
        self::$ATK_VARS['atkfiles'] = $_FILES;
        foreach ($atkfiles as $k => $v) {
            self::$ATK_VARS[$k]['atkfiles'] = $v;
        }

        if (array_key_exists('atkfieldprefix', self::$ATK_VARS) && self::$ATK_VARS['atkfieldprefix'] != '') {
            self::$ATK_VARS = self::$ATK_VARS[self::$ATK_VARS['atkfieldprefix']];
        }

        self::$ATK_VARS['p'] = $_POST;
        self::$ATK_VARS['g'] = $_GET;
        self::$ATK_VARS['r'] = $_REQUEST;
        self::$ATK_VARS['f'] = $_FILES;

        return self::$ATK_VARS;
    }
}
