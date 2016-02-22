<?php
/**
 * Joomlatools Composer plugin - https://github.com/joomlatools/joomlatools-composer
 *
 * @copyright	Copyright (C) 2011 - 2016 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link		http://github.com/joomlatools/joomlatools-composer for the canonical source repository
 */

namespace Joomlatools\Joomla;

/**
 * Joomla utility class
 *
 * @author  Steven Rombauts <https://github.com/stevenrombauts>
 * @package Joomlatools\Joomla
 */
class Util
{
    /**
     * Validate if the current working directory has a valid Joomla installation
     *
     * @return bool
     */
    public static function isJoomla()
    {
        $directories = array('./libraries/cms', './libraries/joomla', './index.php', './administrator/index.php');

        foreach ($directories as $directory)
        {
            $path = realpath($directory);

            if (!file_exists($path)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the working directory has Joomlatools Platform installed
     *
     * @return bool
     */
    public static function isJoomlatoolsPlatform()
    {
        $manifest = realpath('./composer.json');

        if (file_exists($manifest))
        {
            $contents = file_get_contents($manifest);
            $package  = json_decode($contents);

            if (isset($package->name) && in_array($package->name, array('joomlatools/platform', 'joomlatools/joomla-platform'))) {
                return true;
            }
        }

        return false;
    }
}