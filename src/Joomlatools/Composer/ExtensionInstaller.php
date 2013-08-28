<?php
namespace Joomlatools\Composer;

use Joomlatools\Application as Application;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Installer\LibraryInstaller;

class ExtensionInstaller extends LibraryInstaller
{
    protected $_credentials = array();

    public function __construct(IOInterface $io, Composer $composer, $type = 'library')
    {
        parent::__construct($io, $composer, $type);

        $username = $composer->getConfig()->get('joomla-username');
        $password = $composer->getConfig()->get('joomla-password');

        if(!empty($username) && !empty($password))
        {
            $this->_credentials = array(
                'username' => $composer->getConfig()->get('joomla-username'),
                'password' => $composer->getConfig()->get('joomla-password'),
            );
        }
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

        $application = \JFactory::getApplication('administrator');
        $application->initialise();

        $this->_authenticate();
    }

    protected function _authenticate()
    {
        $application = \JFactory::getApplication();

        if(empty($this->_credentials['username']) || empty($this->_credentials['password'])) {
            return false;
        }

        if($application->login($this->_credentials) !== true) {
            throw new \KException('Login failed for user ' . $this->_credentials['username'], \KHttpResponse::UNAUTHORIZED);
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