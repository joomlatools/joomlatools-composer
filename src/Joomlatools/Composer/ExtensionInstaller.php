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
    }

    public function getInstallPath(PackageInterface $package)
    {
        return 'tmp/' . $package->getPrettyName();
    }

    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        // Install the package into the temporary directory, so we can access all it's files
        parent::install($repo, $package);

        // Now install into the Joomla environment
        $this->_bootstrap();

        $installer = new \JInstaller();

        if(!$installer->install($this->getInstallPath($package)))
        {
            $descriptions = $this->_getApplicationMessages();

            $error = 'Error while installing '.$package->getPrettyName();
            if(count($descriptions)) {
                $error .= ':'.PHP_EOL.implode(PHP_EOL, $descriptions);
            }

            throw new \UnexpectedValueException($error);
        }

        // Clean-up to prevent PHP calling the session object's __destruct() method;
        // which will burp out Fatal Errors all over the place 'cos the MySQLI connection
        // has already closed at tha point.
        $session = \JFactory::$session;
        if(!is_null($session) && is_a($session, 'JSession')) {
            $session->close();
        }
    }

    public function supports($packageType)
    {
        return 'joomlatools-extension' === $packageType;
    }

    protected function _bootstrap()
    {
        if(defined('_JEXEC')) {
            return;
        }

        $_SERVER['HTTP_HOST']   = 'localhost';

        define('_JEXEC', 1);
        define('DS', DIRECTORY_SEPARATOR);

        define('JPATH_BASE', realpath('.'));
        require_once JPATH_BASE . '/includes/defines.php';

        require_once JPATH_BASE . '/includes/framework.php';
        require_once JPATH_LIBRARIES . '/import.php';

        require_once JPATH_LIBRARIES . '/cms.php';

        $this->_application = new Application();
        $this->_application->initialise();

        \JFactory::$application = $this->_application;

        $lang = \JFactory::getLanguage();
        $lang->load('lib_joomla', JPATH_ADMINISTRATOR, null, true);
        $lang->load('com_installer', JPATH_ADMINISTRATOR, null, true);

        $this->_authenticate();
    }

    protected function _authenticate()
    {
        $user = \JFactory::getUser();

        $properties = array(
            'name'      => 'root',
            'username'  => 'root',
            'groups'    => array(8),
            'email'     => 'root@localhost.home'
        );

        foreach($properties as $property => $value) {
            $user->{$property} = $value;
        }

        return true;
    }

    protected function _getApplicationMessages()
    {
        $messages = \JFactory::getApplication()->getMessageQueue();
        $descriptions = array();

        foreach($messages as $message)
        {
            if($message['type'] == 'error') {
                $descriptions[] = $message['message'];
            }
        }

        return $descriptions;
    }
}