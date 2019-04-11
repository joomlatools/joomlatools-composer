<?php
/**
 * Joomlatools Composer plugin - https://github.com/joomlatools/joomlatools-composer
 *
 * @copyright	Copyright (C) 2011 - 2016 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link		http://github.com/joomlatools/joomlatools-composer for the canonical source repository
 */

namespace Joomlatools\Joomla;

use Composer\IO\IOInterface;
use Symfony\Component\Console\Output\OutputInterface;
/**
 * Joomla bootstrapper class
 *
 * @author  Steven Rombauts <https://github.com/stevenrombauts>
 * @package Joomlatools\Joomla
 */
class Bootstrapper
{
    /** @var Bootstrapper $__instance */
    private static $__instance = null;

    /** @var IOInterface $_io */
    protected $_io = null;
    /** @var Application $__application */
    protected $_application;
    /** @var bool $_bootstrapped */
    protected $_bootstrapped = false;

    protected $_verbosity   = OutputInterface::VERBOSITY_NORMAL;
    protected $_credentials = array();

    /**
     * Get instance of this class
     *
     * @return Bootstrapper $instance
     */
    public static function getInstance()
    {
        if (!self::$__instance) {
            self::$__instance = new Bootstrapper();
        }

        return self::$__instance;
    }

    public function setIO(IOInterface $io)
    {
        if ($this->_bootstrapped)
        {
            if ($this->_io->isVeryVerbose()) {
                $io->write('Application has already been bootstrapped. Can not set different IOInterface.', true);
            }

            return;
        }

        $this->_io = $io;

        if ($io->isDebug()) {
            $this->_verbosity = OutputInterface::VERBOSITY_DEBUG;
        } elseif ($io->isVeryVerbose()) {
            $this->_verbosity = OutputInterface::VERBOSITY_VERY_VERBOSE;
        } elseif ($io->isVerbose()) {
            $this->_verbosity = OutputInterface::VERBOSITY_VERBOSE;
        }
    }

    public function setCredentials(array $credentials)
    {
        if (!($this->_io instanceof IOInterface)) {
            throw new \RuntimeException('Bootstrapper instance requires IOInterface instance. Please call setIO() first.');
        }

        if ($this->_bootstrapped)
        {
            if ($this->_io->isVeryVerbose()) {
                $this->_io->write('Application has already been bootstrapped. Can not set new credentials.', true);
            }

            return;
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
     * Get the application instance.
     * If it 's not initialised yet, bootstrap the application.
     *
     * @return \Joomlatools\Joomla\Application/bool $application Application instance on success or false on failure
     * @throws Exception $exception
     */
    public function getApplication()
    {
        if (!($this->_io instanceof IOInterface)) {
            throw new \RuntimeException('Bootstrapper instance requires IOInterface instance. Please call setIO() first.');
        }

        if (!$this->_bootstrapped) {
            $this->_bootstrap();
        }

        if (!($this->_application instanceof Application))
        {
            $options = array(
                'root_user' => $this->_credentials['username'],
                'loglevel'  => $this->_verbosity,
                'platform'  => Util::isJoomlatoolsPlatform()
            );

            try
            {
                $this->_application = new Application($options);
                $this->_application->authenticate($this->_credentials);
            }
            catch (\Exception $ex)
            {
                $this->_io->write("<error>Failed to initialize the Joomla application</error>");

                if ($this->_io->isVerbose()) {
                    $this->_io->write($ex->getMessage());
                }

                if ($this->_io->isDebug()) {
                    throw $ex;
                }

                $this->_application = false;
            }
        }

        return $this->_application;
    }

    /**
     * Bootstraps the Joomla application
     *
     * @return void
     */
    protected function _bootstrap()
    {
        if($this->_bootstrapped) {
            return;
        }

        $_SERVER['HTTP_HOST']   = 'localhost';
        $_SERVER['HTTP_USER_AGENT'] = 'Composer';

        define('DS', DIRECTORY_SEPARATOR);

        $base = realpath('.');

        if (Util::isJoomlatoolsPlatform())
        {
            define('JPATH_WEB'   , $base.'/web');
            define('JPATH_ROOT'  , $base);
            define('JPATH_BASE'  , JPATH_ROOT . '/app/administrator');
            define('JPATH_CACHE' , JPATH_ROOT . '/cache/site');
            define('JPATH_THEMES', __DIR__.'/templates');

            // Joomlatools Platform <= v1.0.2 defined the _JEXEC constant
            // in the web/index.php and web/administrator/index.php files.
            // In later versions it was moved to app/defines.php.
            // If app/defines.php does not contain the define() call, set it manually.
            // Reading the JVersion file is impossible either as we would need to
            // define JPATH_PLATFORM twice (throwing more errors).
            $string = file_get_contents(JPATH_ROOT.'/app/defines.php');
            if (strpos($string, "define('_JEXEC', 1)") === false) {
                define('_JEXEC', 1);
            }

            require_once JPATH_ROOT . '/app/defines.php';
            require_once JPATH_ROOT . '/app/bootstrap.php';

            require_once JPATH_LIBRARIES . '/import.php';
        }
        else
        {
            define('_JEXEC', 1);
            define('JPATH_BASE', $base);

            require_once JPATH_BASE . '/includes/defines.php';
            require_once JPATH_BASE . '/includes/framework.php';
        }

        // cache the current phar stream wrapper
        $wrappers = stream_get_wrappers();

        require_once JPATH_LIBRARIES . '/cms.php';

        if (in_array('phar', stream_get_wrappers()) && isset($wrappers['phar']))
        {
            stream_wrapper_unregister('phar');
            stream_wrapper_register('phar', $wrappers['phar']);
        }


        $this->_bootstrapped = true;


    }
}