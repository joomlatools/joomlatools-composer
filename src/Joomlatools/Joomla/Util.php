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
    private static $__manifests = array();

    public static function getPlatformName()
    {
        return static::isJoomlatoolsPlatform() ? 'Joomlatools Platform' : 'Joomla';
    }

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

    public static function isReusableComponent(\Composer\Package\PackageInterface $package)
    {
        $extra = $package->getExtra();

        return (is_array($extra) && isset($extra['joomlatools-component']));
    }

    /**
     * Find the XML manifest of the installation package
     *
     * @param string $installPath Install path of package
     *
     * @return string Full path to manifest file
     */
    public static function getPackageManifest($installPath)
    {
        if (!array_key_exists($installPath, self::$__manifests))
        {
            self::$__manifests[$installPath] = false;

            $directories = new \RecursiveDirectoryIterator($installPath);
            $iterator    = new \RecursiveIteratorIterator($directories);

            $iterator->setMaxDepth(1);

            $files = new \RegexIterator($iterator, '/.*\.xml$/', \RegexIterator::GET_MATCH);
            $manifests = array();
            foreach($files as $file) {
                $manifests = array_unique(array_merge($manifests, $file));
            }

            if (count($manifests))
            {
                // Sort the results by number of subdirectories (root first, then subdirectories)
                usort($manifests, function ($a, $b) {
                    $partsA = explode(DIRECTORY_SEPARATOR, $a);
                    $partsB = explode(DIRECTORY_SEPARATOR, $b);

                    return count($partsA) - count($partsB);
                });

                foreach ($manifests as $manifest)
                {
                    $xml = simplexml_load_file($manifest);

                    if (!($xml instanceof \SimpleXMLElement)) {
                        continue;
                    }

                    if ($xml->getName() == 'extension') {
                        self::$__manifests[$installPath] = $manifest;
                    }

                    unset($xml);
                }
            }
        }

        return self::$__manifests[$installPath];
    }

    /**
     * Set or overwrite the XML manifest path for the given install package.
     *
     * @param $package  Install path of package
     * @param $filename Full path to XML manifest
     *
     * @return void
     */
    public static function setPackageManifest($package, $filename)
    {
        self::$__manifests[$package] = $filename;
    }

    /**
     * Get the element name from the XML manifest
     *
     * @param string $manifest Path to the installation package
     *
     * @return string|bool   Extension name or FALSE on failure
     */
    public static function getNameFromManifest($installPath)
    {
        $manifest = self::getPackageManifest($installPath);

        if ($manifest === false) {
            return false;
        }

        $xml = simplexml_load_file($manifest);

        if (!($xml instanceof \SimpleXMLElement)) {
            return false;
        }

        $element = false;
        $type    = (string) $xml->attributes()->type;

        switch($type)
        {
            case 'component':
                $name    = strtolower((string) $xml->name);
                $element = preg_replace('/[^A-Z0-9_\.-]/i', '', $name);

                if (substr($element, 0, 4) != 'com_') {
                    $element = 'com_'.$element;
                }
                break;
            case 'module':
            case 'plugin':
                if(count($xml->files->children()))
                {
                    foreach($xml->files->children() as $file)
                    {
                        if ((string) $file->attributes()->$type)
                        {
                            $element = (string) $file->attributes()->$type;
                            break;
                        }
                    }
                }
                break;
            case 'file':
            case 'library':
                $filename = basename($manifest);
                $element  = substr($filename, 0, -strlen('.xml'));
                break;
            case 'package':
                $element = preg_replace('/[^A-Z0-9_\.-]/i', '', $xml->packagename);

                if (substr($element, 0, 4) != 'pkg_') {
                    $element = 'pkg_'.$element;
                }
                break;
            case 'language':
                $element = (string) $xml->tag;
                break;
            case 'template':
                $name    = preg_replace('/[^A-Z0-9_ \.-]/i', '', $xml->name);
                $element = strtolower(str_replace(' ', '_', $name));
                break;
            default:
                break;
        }

        return $element;
    }
}
