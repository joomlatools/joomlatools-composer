<?php
/**
 * Joomlatools Composer plugin - https://github.com/joomlatools/joomlatools-composer
 *
 * @copyright	Copyright (C) 2011 - 2016 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link		http://github.com/joomlatools/joomlatools-composer for the canonical source repository
 */

namespace Joomlatools\Composer;

use Joomlatools\Joomla\Bootstrapper;
use Joomlatools\Joomla\Util;

use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;

/**
 * Joomla extension installer class
 *
 * @author  Steven Rombauts <https://github.com/stevenrombauts>
 * @package Joomlatools\Composer
 */
class ExtensionInstaller
{
    /** @var IOInterface $io */
    protected $_io = null;

    public function __construct(IOInterface $io)
    {
        $this->_io = $io;
    }

    public function execute()
    {
        $application = Bootstrapper::getInstance()->getApplication();

        if ($application === false)
        {
            $this->_io->write(sprintf('[<error>ERROR</error>] Failed to initialize the %1$s application! %1$s extensions will not be installed or removed. Is the application properly configured?', Util::getPlatformName()));

            return;
        }

        foreach (TaskQueue::getInstance() as $task)
        {
            list($action, $package, $installPath) = $task;

            if (method_exists($this, $action)) {
                call_user_func_array(array($this, $action), array($package, $installPath));
            }
        }
    }

    public function install(PackageInterface $package, $installPath)
    {
        $application = Bootstrapper::getInstance()->getApplication();

        if (Util::isReusableComponent($package)) {
            return $this->_installReusableComponent($package, $installPath);
        }

        $this->_copyManifestFiles($package, $installPath);

        $this->_io->write(sprintf("Installing the %s extension <info>%s</info> <comment>%s</comment>", Util::getPlatformName(), $package->getName(), $package->getFullPrettyVersion()));

        if(!$application->install($installPath))
        {
            // Get all error messages that were stored in the message queue
            $descriptions = $this->_getApplicationMessages();

            $error = 'Error while installing '.$package->getPrettyName();
            if(count($descriptions)) {
                $error .= ':'.PHP_EOL.implode(PHP_EOL, $descriptions);
            }

            throw new \RuntimeException($error);
        }

        $this->_enablePlugin($package, $installPath);
    }

    public function update(PackageInterface $package, $installPath)
    {
        if (Util::isReusableComponent($package)) {
            return $this->_installReusableComponent($package, $installPath);
        }

        $this->_copyManifestFiles($package, $installPath);

        $this->_io->write(sprintf("Updating the %s extension <info>%s</info> to <comment>%s</comment>", Util::getPlatformName(), $package->getName(), $package->getFullPrettyVersion()));

        if(!Bootstrapper::getInstance()->getApplication()->update($installPath))
        {

            // Get all error messages that were stored in the message queue
            $descriptions = $this->_getApplicationMessages();

            $error = 'Error while updating '.$package->getPrettyName();
            if(count($descriptions)) {
                $error .= ':'.PHP_EOL.implode(PHP_EOL, $descriptions);
            }

            throw new \RuntimeException($error);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function uninstall(PackageInterface $package, $installPath)
    {
        if (Util::isReusableComponent($package)) {
            return $this->_uninstallReusableComponent($package, $installPath);
        }

        $file           = Util::getPackageManifest($installPath);

        if($file !== false && file_exists($file))
        {
            $this->_io->write(sprintf("Uninstalling the %s extension <info>%s</info>", Util::getPlatformName(), $package->getName()));

            $manifest = simplexml_load_file($file);

            $type    = (string) $manifest->attributes()->type;
            $element = Util::getNameFromManifest($installPath);

            if (!empty($element))
            {
                $application = Bootstrapper::getInstance()->getApplication();
                $extension   = $application->getExtension($element, $type);

                if ($extension) {
                    $application->uninstall($extension->id, $type);
                }
            }
        }
        else $this->_io->write(sprintf("[<error>WARNING</error>] Can not uninstall the %s extension <info>%s</info>: XML manifest not found.", Util::getPlatformName(), $package->getName()));
    }

    protected function _installReusableComponent(PackageInterface $package, $installPath)
    {
        Bootstrapper::getInstance()->getApplication(); // required to load Joomla libs

        \jimport('joomla.filesystem.folder');
        \jimport('joomla.filesystem.file');

        $this->_io->write(sprintf("Installing the %s reusable component <info>%s</info> <comment>%s</comment>", Util::getPlatformName(), $package->getName(), $package->getFullPrettyVersion()));

        $extra = $package->getExtra();
        $name  = $extra['joomlatools-component'];
        $map = array(
            $installPath                     => JPATH_LIBRARIES.'/joomlatools-components/'.$name,
            $installPath.'/resources/assets' => JPATH_ROOT.'/media/koowa/com_'.$name
        );

        foreach ($map as $from => $to)
        {
            $temp   = $to.'_tmp';

            if (!is_dir($from)) {
                continue;
            }

            if (is_dir($temp)) {
                \JFolder::delete($temp);
            }

            \JFolder::copy($from, $temp);

            if (is_dir($to)) {
                \JFolder::delete($to);
            }

            \JFolder::move($temp, $to);
        }

        $install_sql = JPATH_LIBRARIES.'/joomlatools-components/'.$name.'/resources/install/install.sql';
        if (is_file($install_sql)) {
            $db = \JFactory::getDbo();
            $queries = $db->splitSql(file_get_contents($install_sql));

            if ($queries) {
                foreach ($queries as $query) {
                    $query = trim($query);

                    if ($query != '' && $query{0} != '#') {
                        try {
                            $db->setQuery($query)->execute();
                        } catch (\Exception $e) {
                        }
                    }
                }
            }
        }
    }

    protected function _uninstallReusableComponent(PackageInterface $package, $installPath)
    {
        Bootstrapper::getInstance()->getApplication(); // required to load Joomla libs

        \jimport('joomla.filesystem.folder');
        \jimport('joomla.filesystem.file');

        $this->_io->write(sprintf("Uninstalling the %s reusable component <info>%s</info> <comment>%s</comment>", Util::getPlatformName(), $package->getName(), $package->getFullPrettyVersion()));

        $name    = $package->getExtra()['joomlatools-component'];
        $folders = array(
            JPATH_LIBRARIES.'/joomlatools-components/'.$name,
            JPATH_ROOT.'/media/koowa/com_'.$name
        );

        foreach ($folders as $folder)
        {
            if (is_dir($folder)) {
                \JFolder::delete($folder);
            }
        }
    }

    protected function _copyManifestFiles(PackageInterface $package, $installPath)
    {
        $extra = $package->getExtra();

        if (is_array($extra) && isset($extra['manifest']))
        {
            $manifest_name = basename($extra['manifest']);
            $manifest_dir  = $installPath.'/'.dirname($extra['manifest']);
            $target_dir    = is_dir($installPath.'/code') ? $installPath.'/code' : $installPath;

            copy($manifest_dir.'/'.$manifest_name, $target_dir.'/'.$manifest_name);

            if (is_file($manifest_dir.'/script.php')) {
                copy($manifest_dir.'/script.php', $target_dir.'/script.php');
            }
        }
    }

    /**
     * Fetches the enqueued flash messages from the Joomla application object.
     *
     * @return array
     */
    protected function _getApplicationMessages()
    {
        $messages       = Bootstrapper::getInstance()->getApplication()->getMessageQueue();
        $descriptions   = array();

        foreach($messages as $message)
        {
            if($message['type'] == 'error') {
                $descriptions[] = $message['message'];
            }
        }

        return $descriptions;
    }

    /**
     * Enable all plugins that were installed with this package.
     *
     * @param PackageInterface $package
     * @param string           $subdirectory Subdirectory in package install path to look for plugin manifests
     */
    protected function _enablePlugin(PackageInterface $package, $installPath, $subdirectory = '')
    {
        $path = realpath($installPath . '/' . $subdirectory);
        $file = Util::getPackageManifest($path);

        if($file !== false)
        {
            $manifest = simplexml_load_file($file);
            $type     = (string) $manifest->attributes()->type;

            if ($type == 'plugin')
            {
                $name  = Util::getNameFromManifest($installPath);
                $group = (string) $manifest->attributes()->group;

                $extension = Bootstrapper::getInstance()->getApplication()->getExtension($name, 'plugin', $group);

                if (is_object($extension) && $extension->id > 0)
                {
                    $sql = "UPDATE `#__extensions`"
                        ." SET `enabled` = 1"
                        ." WHERE `extension_id` = ".$extension->id;

                    \JFactory::getDbo()->setQuery($sql)->execute();
                }
            }
            elseif ($type == 'package')
            {
                foreach($manifest->files->children() as $file)
                {
                    if ((string) $file->attributes()->type == 'plugin') {
                        $this->_enablePlugin($package, $installPath, (string) $file);
                    }
                }
            }
        }
    }
}
