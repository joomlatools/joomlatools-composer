<?php
/**
 * Joomlatools Composer plugin - https://github.com/joomlatools/joomlatools-composer
 *
 * @copyright	Copyright (C) 2011 - 2013 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link		http://github.com/joomlatools/joomlatools-composer for the canonical source repository
 */

namespace Joomlatools\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Installer\LibraryInstaller;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Composer installer class
 *
 * @author  Steven Rombauts <https://github.com/stevenrombauts>
 * @package Joomlatools\Composer
 */
class ExtensionInstaller extends LibraryInstaller
{
    protected $_config      = null;
    protected $_verbosity   = OutputInterface::VERBOSITY_NORMAL;
    protected $_application = null;
    protected $_credentials = array();

    /**
     * {@inheritDoc}
     */
    public function __construct(IOInterface $io, Composer $composer, $type = 'library')
    {
        parent::__construct($io, $composer, $type);

        $this->_config = $composer->getConfig();

        if (!$this->_isJoomla() && !$this->_isJoomlatoolsPlatform()) {
            throw new \RuntimeException('Working directory is not a valid Joomla installation');
        }

        if ($io->isDebug()) {
            $this->_verbosity = OutputInterface::VERBOSITY_DEBUG;
        } elseif ($io->isVeryVerbose()) {
            $this->_verbosity = OutputInterface::VERBOSITY_VERY_VERBOSE;
        } elseif ($io->isVerbose()) {
            $this->_verbosity = OutputInterface::VERBOSITY_VERBOSE;
        }

        $this->_initialize();
    }

    /**
     * Initializes extension installer.
     *
     * @return void
     */
    protected function _initialize()
    {
        $credentials = $this->_config->get('joomla');

        if(is_null($credentials) || !is_array($credentials)) {
            $credentials = array();
        }

        $defaults = array(
            'name'      => 'root',
            'username'  => 'root',
            'groups'    => array(8),
            'email'     => 'root@localhost.home'
        );

        $this->_credentials = array_merge($defaults, $credentials);
    }

    /**
     * Get the application object.
     * If it 's not initialised yet, bootstrap the application.
     */
    public function getApplication()
    {
        if (!$this->_application)
        {
            $this->_bootstrap();
        }

        return $this->_application;
    }

    /**
     * {@inheritDoc}
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        parent::install($repo, $package);

        $this->_setupExtmanSupport($package);

        $this->io->write('    <fg=cyan>Installing</fg=cyan> into Joomla'.PHP_EOL);

        if(!$this->getApplication()->install($this->getInstallPath($package)))
        {
            // Get all error messages that were stored in the message queue
            $descriptions = $this->_getApplicationMessages();

            $error = 'Error while installing '.$package->getPrettyName();
            if(count($descriptions)) {
                $error .= ':'.PHP_EOL.implode(PHP_EOL, $descriptions);
            }

            throw new \RuntimeException($error);
        }

        $this->_enablePlugin($package);
    }

    /**
     * {@inheritDoc}
     */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        parent::update($repo, $initial, $target);

        $this->_setupExtmanSupport($target);

        $this->io->write('    <fg=cyan>Updating</fg=cyan> Joomla extension'.PHP_EOL);

        if(!$this->getApplication()->update($this->getInstallPath($target)))
        {
            // Get all error messages that were stored in the message queue
            $descriptions = $this->_getApplicationMessages();

            $error = 'Error while updating '.$target->getPrettyName();
            if(count($descriptions)) {
                $error .= ':'.PHP_EOL.implode(PHP_EOL, $descriptions);
            }

            throw new \RuntimeException($error);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        if (!$repo->hasPackage($package)) {
            throw new \InvalidArgumentException('Package is not installed: '.$package);
        }

        $installPath = $this->getInstallPath($package);
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

        parent::uninstall($repo, $package);

        $this->io->write('    <fg=cyan>Removing</fg=cyan> Joomla extension'.PHP_EOL);
    }

    /**
     * {@inheritDoc}
     */
    public function supports($packageType)
    {
        return in_array($packageType, array('joomlatools-composer', 'joomlatools-installer', 'joomla-installer'));
    }

    /**
     * {@inheritDoc}
     */
    public function isInstalled(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $installPath = $this->getInstallPath($package);
        $manifest    = $this->_getManifest($installPath);

        if($manifest)
        {
            $type    = (string) $manifest->attributes()->type;
            $element = $this->_getElementFromManifest($manifest);

            if (empty($element)) {
                return false;
            }

            $extension = $this->getApplication()->getExtension($element, $type);

            return $extension !== false;
        }

        return false;
    }

    /**
     * Bootstraps the Joomla application
     *
     * @return void
     */
    protected function _bootstrap()
    {
        if(!defined('_JEXEC'))
        {
            $_SERVER['HTTP_HOST']   = 'localhost';
            $_SERVER['HTTP_USER_AGENT'] = 'Composer';

            define('DS', DIRECTORY_SEPARATOR);

            $base = realpath('.');

            if ($this->_isJoomlatoolsPlatform())
            {
                define('JPATH_WEB'   , $base.'/web');
                define('JPATH_ROOT'  , $base);
                define('JPATH_BASE'  , JPATH_ROOT . '/app/administrator');
                define('JPATH_CACHE' , JPATH_ROOT . '/cache/site');
                define('JPATH_THEMES', __DIR__.'/templates');

                require_once JPATH_ROOT . '/app/defines.php';
                require_once JPATH_ROOT . '/app/bootstrap.php';
            }
            else
            {
                define('_JEXEC', 1);
                define('JPATH_BASE', $base);

                require_once JPATH_BASE . '/includes/defines.php';
                require_once JPATH_BASE . '/includes/framework.php';
            }

            require_once JPATH_LIBRARIES . '/import.php';
            require_once JPATH_LIBRARIES . '/cms.php';
        }

        if(!($this->_application instanceof Application))
        {
            $options = array(
                'root_user' => $this->_credentials['username'],
                'loglevel'  => $this->_verbosity,
                'platform'  => $this->_isJoomlatoolsPlatform()
            );

            $this->_application = new Application($options);
            $this->getApplication()->authenticate($this->_credentials);
        }
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
    protected function _enablePlugin(PackageInterface $package, $subdirectory = '')
    {
        $path     = realpath($this->getInstallPath($package) . '/' . $subdirectory);
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
                        $this->_enablePlugin($package, (string) $file);
                    }
                }
            }
        }
    }

    /**
     * Make sure to load the ComExtmanDatabaseRowExtension class
     * if we are installing a Joomlatools extension
     *
     * @param PackageInterface $target
     */
    protected function _setupExtmanSupport(PackageInterface $target)
    {
        $name = strtolower($target->getPrettyName());
        $parts = explode('/', $name);
        if($parts[0] == 'joomlatools' && $parts[1] != 'extman')
        {
            \JPluginHelper::importPlugin('system', 'koowa');

            if(class_exists('Koowa') && !class_exists('ComExtmanDatabaseRowExtension')) {
                \KObjectManager::getInstance()->getObject('com://admin/extman.database.row.extension');
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

    /**
     * Validate if the current working directory has a valid Joomla installation
     *
     * @return bool
     */
    protected function _isJoomla()
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
    protected function _isJoomlatoolsPlatform()
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

    public function __destruct()
    {
        if(!defined('_JEXEC')) {
            return;
        }

        // Clean-up to prevent PHP calling the session object's __destruct() method;
        // which will burp out Fatal Errors all over the place because the MySQLI connection
        // has already closed at that point.
        $session = \JFactory::$session;
        if(!is_null($session) && is_a($session, 'JSession')) {
            $session->close();
        }
    }
}
