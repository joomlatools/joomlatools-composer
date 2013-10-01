<?php
namespace Joomlatools\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Installer\LibraryInstaller;

class ExtensionInstaller extends LibraryInstaller
{
    protected $_application = null;

    public function __construct(IOInterface $io, Composer $composer, $type = 'library')
    {
        parent::__construct($io, $composer, $type);

        $this->_initialize();
    }

    protected function _initialize()
    {
        $this->_bootstrap();
    }

    public function getInstallPath(PackageInterface $package)
    {
        return 'tmp/' . $package->getPrettyName();
    }

    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        parent::install($repo, $package);

        $this->_setupExtmanSupport($package);

        $this->io->write('    <fg=cyan>Installing</fg=cyan> into Joomla'.PHP_EOL);

        if(!$this->_application->install($this->getInstallPath($package)))
        {
            // Get all error messages that were stored in the message queue
            $descriptions = $this->_getApplicationMessages();

            $error = 'Error while installing '.$package->getPrettyName();
            if(count($descriptions)) {
                $error .= ':'.PHP_EOL.implode(PHP_EOL, $descriptions);
            }

            throw new \RuntimeException($error);
        }
    }

    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        parent::update($repo, $initial, $target);

        $this->_setupExtmanSupport($target);

        $this->io->write('    <fg=cyan>Updating</fg=cyan> Joomla extension'.PHP_EOL);

        if(!$this->_application->update($this->getInstallPath($target)))
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

    public function supports($packageType)
    {
        return $packageType === 'joomlatools-installer';
    }

    public function isInstalled(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $installer = $this->_application->getInstaller();
        $installer->setPath('source', $this->getInstallPath($package));

        $manifest = $installer->getManifest();

        if($manifest)
        {
            $type    = (string) $manifest->attributes()->type;
            $element = $this->_getElementFromManifest($manifest);

            return !empty($element) ? $this->_application->hasExtension($element, $type) : false;
        }

        return false;
    }

    protected function _bootstrap()
    {
        if(!defined('_JEXEC'))
        {
            $_SERVER['HTTP_HOST']   = 'localhost';

            define('_JEXEC', 1);
            define('DS', DIRECTORY_SEPARATOR);

            define('JPATH_BASE', realpath('.'));
            require_once JPATH_BASE . '/includes/defines.php';

            require_once JPATH_BASE . '/includes/framework.php';
            require_once JPATH_LIBRARIES . '/import.php';

            require_once JPATH_LIBRARIES . '/cms.php';
        }

        if(!($this->_application instanceof Application))
        {
            $this->_application = new Application();
            $this->_application->authenticate();
        }
    }

    protected function _getApplicationMessages()
    {
        $messages       = $this->_application->getMessageQueue();
        $descriptions   = array();

        foreach($messages as $message)
        {
            if($message['type'] == 'error') {
                $descriptions[] = $message['message'];
            }
        }

        return $descriptions;
    }

    protected function _setupExtmanSupport(PackageInterface $target)
    {
        // If we are installing a Joomlatools extension, make sure to load the ComExtmanDatabaseRowExtension class
        $name = strtolower($target->getPrettyName());
        $parts = explode('/', $name);
        if($parts[0] == 'joomlatools' && $parts[1] != 'extman')
        {
            if(class_exists('Koowa') && !class_exists('ComExtmanDatabaseRowExtension')) {
                \KObjectManager::getInstance()->getObject('com://admin/extman.database.row.extension');
            }
        }
    }

    protected function _getElementFromManifest($manifest)
    {
        $element    = '';
        $type       = (string) $manifest->attributes()->type;
        $prefix     = isset($this->_prefixes[$type]) ? $this->_prefixes[$type].'_' : 'com_';

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
            default:
                $element = strtolower((string) $manifest->name);
                $element = preg_replace('/[^A-Z0-9_\.-]/i', '', $element);

                if(substr($element, 0, 4) != 'com_') {
                    $element = 'com_'.$element;
                }
                break;
        }

        return $element;
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