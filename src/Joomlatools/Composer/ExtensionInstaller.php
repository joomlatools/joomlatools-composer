<?php
/**
 * Joomlatools Composer plugin - https://github.com/joomlatools/joomlatools-composer
 *
 * @copyright	Copyright (C) 2011 - 2016 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link		http://github.com/joomlatools/joomlatools-composer for the canonical source repository
 */

namespace Joomlatools\Composer;

use Composer\IO\IOInterface;
use Joomlatools\Joomla\Util;

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
        foreach (Taskqueue::getInstance() as $task)
        {
            list($action, $package, $installPath) = $task;

            if (method_exists($this, $action)) {
                call_user_func_array(array($this, $action), array($package, $installPath));
            }
        }
    }

    public function install(PackageInterface $package, $installPath)
    {
        $application = Util::isJoomlatoolsPlatform() ? 'Joomlatools Platform' : 'Joomla';

        $this->_io->write(sprintf("    - Installing the %s extension <info>%s</info> <comment>%s</comment>", $application, $package->getName(), $package->getFullPrettyVersion()));

        if(!$this->getApplication()->install($installPath))
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
        $application = Util::isJoomlatoolsPlatform() ? 'Joomlatools Platform' : 'Joomla';

        $this->_io->write(sprintf("    - Updating the %s extension <info>%s</info> to <comment>%s</comment>", $application, $package->getName(), $package->getFullPrettyVersion()));

        if(!$this->getApplication()->update($installPath))
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
        $manifest    = $this->_getManifest($installPath);

        if($manifest)
        {
            $type    = (string) $manifest->attributes()->type;
            $element = $this->_getElementFromManifest($manifest);

            if (!empty($element))
            {
                $extension = $this->getApplication()->getExtension($element, $type);

                if ($extension) {
                    $this->getApplication()->uninstall($extension->id, $type);
                }
            }
        }

        $this->_io->write('    <fg=cyan>Removing</fg=cyan> Joomla extension'.PHP_EOL);
    }

    /**
     * {@inheritDoc}
     */
    public function supports($packageType)
    {
        return in_array($packageType, array('joomlatools-composer', 'joomlatools-installer', 'joomla-installer'));
    }
    
    /**
     * Fetches the enqueued flash messages from the Joomla application object.
     *
     * @return array
     */
    protected function _getApplicationMessages()
    {
        $messages       = $this->getApplication()->getMessageQueue();
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
        $path     = realpath($installPath . '/' . $subdirectory);
        $manifest = $this->_getManifest($path);

        if($manifest)
        {
            $type = (string) $manifest->attributes()->type;

            if ($type == 'plugin')
            {
                $name  = $this->_getElementFromManifest($manifest);
                $group = (string) $manifest->attributes()->group;

                $extension = $this->getApplication()->getExtension($name, 'plugin', $group);

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

    /**
     * Find the xml manifest of the package
     *
     * @param string Install path of package
     *
     * @return object  Manifest object
     */
    protected function _getManifest($installPath)
    {
        if (!is_dir($installPath)) {
            return false;
        }

        $installer = $this->getApplication()->getInstaller();
        $installer->setPath('source', $installPath);

        return $installer->getManifest();
    }

    /**
     * Get the element's name from the XML manifest
     *
     * @param object  Manifest object
     *
     * @return string
     */
    protected function _getElementFromManifest($manifest)
    {
        $element    = '';
        $type       = (string) $manifest->attributes()->type;

        switch($type)
        {
            case 'module':
                if(count($manifest->files->children()))
                {
                    foreach($manifest->files->children() as $file)
                    {
                        if((string) $file->attributes()->module)
                        {
                            $element = (string) $file->attributes()->module;
                            break;
                        }
                    }
                }
                break;
            case 'plugin':
                if(count($manifest->files->children()))
                {
                    foreach($manifest->files->children() as $file)
                    {
                        if ((string) $file->attributes()->$type)
                        {
                            $element = (string) $file->attributes()->$type;
                            break;
                        }
                    }
                }
                break;
            case 'component':
                $element = strtolower((string) $manifest->name);
                $element = preg_replace('/[^A-Z0-9_\.-]/i', '', $element);

                if(substr($element, 0, 4) != 'com_') {
                    $element = 'com_'.$element;
                }
                break;
            default:
                $element = strtolower((string) $manifest->name);
                $element = preg_replace('/[^A-Z0-9_\.-]/i', '', $element);
                break;
        }

        return $element;
    }
}
