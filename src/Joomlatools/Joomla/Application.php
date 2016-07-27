<?php
/**
 * Joomlatools Composer plugin - https://github.com/joomlatools/joomlatools-composer
 *
 * @copyright	Copyright (C) 2011 - 2016 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link		http://github.com/joomlatools/joomlatools-composer for the canonical source repository
 */

namespace Joomlatools\Joomla;

use Symfony\Component\Console\Output\OutputInterface;

use \JApplicationCli as JApplicationCli;
use \JDispatcher as JDispatcher;
use \JFactory as JFactory;
use \JInstaller as JInstaller;
use \JSession as JSession;
use \JRouter as JRouter;
use \JVersion as JVersion;
use \JLog as JLog;

/**
 * Application extending Joomla CLI class.
 *
 * @author  Steven Rombauts <https://github.com/stevenrombauts>
 * @package Joomlatools\Composer
 */
class Application extends JApplicationCli
{
    protected $_messageQueue = array();
    protected $_options      = array();
    protected $_is_platform  = false;

    /**
     * Class constructor.
     *
     * @param   array  $options     An array of configuration settings.
     * @param   mixed  $input       An optional argument to provide dependency injection for the application's
     *                              input object.
     * @param   mixed  $config      An optional argument to provide dependency injection for the application's
     *                              config object.
     * @param   mixed  $dispatcher  An optional argument to provide dependency injection for the application's
     *                              event dispatcher.
     * @return  void
     *
     * @see JApplicationCli
     */
    public function __construct($options = array(), JInputCli $input = null, JRegistry $config = null, JDispatcher $dispatcher = null)
    {
        $this->_options = $options;

        if (isset($this->_options['platform'])) {
            $this->_is_platform = $this->_options['platform'];
        }

        if (isset($this->_options['loglevel'])) {
            $this->_setupLogging($this->_options['loglevel']);
        }

        parent::__construct($input, $config, $dispatcher);

        $this->_initialize();
    }

    /**
     * Initialise the application.
     *
     * Loads the necessary Joomla libraries to make sure
     * the Joomla application can run and sets up the JFactory properties.
     *
     * @param   array  $options  An optional associative array of configuration settings.
     * @return  void
     */
    protected function _initialize()
    {
        // Load dependencies
        jimport('joomla.application.component.helper');
        jimport('joomla.application.menu');

        jimport('joomla.environment.uri');

        jimport('joomla.event.dispatcher');

        jimport('joomla.utilities.utility');
        jimport('joomla.utilities.arrayhelper');

        jimport('joomla.application.module.helper');

        // Tell JFactory where to find the current application object
        JFactory::$application = $this;

        // Start a new session and tell JFactory where to find it if we're on Joomla 3
        if(version_compare(JVERSION, '3.0.0', '>=')) {
            JFactory::$session = $this->_startSession();
        }

        // Load required languages
        $lang = JFactory::getLanguage();
        $lang->load('lib_joomla', JPATH_ADMINISTRATOR, null, true);
        $lang->load('com_installer', JPATH_ADMINISTRATOR, null, true);
    }

    /**
     * Authenticates the Joomla user.
     *
     * This method will load the default user object and change its guest status to logged in.
     * It will then simply copy all the properties defined by key in the $credentials argument
     * onto this JUser object, allowing you to completely overwrite the user information.
     *
     * @param array $credentials    Associative array containing user object properties.
     *
     * @return void
     */
    public function authenticate($credentials)
    {
        $user = JFactory::getUser();
        $user->guest = 0;

        foreach($credentials as $key => $value) {
            $user->$key = $value;
        }

        JFactory::getSession()->set('user', $user);
    }

    /**
     * Get the extension info from Joomla's #__extensions table
     *
     * @param string $element   The name of the element
     * @param string $type      The type of extension
     * @param string $group     Only for plugins
     *
     * @return bool
     */
    public function getExtension($element, $type = 'component', $group = null)
    {
        $db = JFactory::getDbo();
        $sql = "SELECT `extension_id` AS `id`, `state` FROM `#__extensions`"
                ." WHERE `element` = ".$db->quote($element)." AND `type` = ".$db->quote($type);

        if ($type == 'plugin' && !empty($group)) {
            $sql .= ' AND `folder` =' . $db->quote($group);
        }

        $extension = $db->setQuery($sql)->loadObject();

        if ($extension && $extension->state != -1); {
            return $extension;
        }

        return false;
    }

    /**
     * Installs an extension from the given path.
     *
     * @param string $path Path to the extracted installation package.
     *
     * @return bool
     */
    public function install($path)
    {
        $installer = $this->getInstaller();

        return $installer->install($path);
    }

    /**
     * Updates an existing extension from the given path.
     *
     * @param string $path Path to the extracted installation package.
     *
     * @return bool
     */
    public function update($path)
    {
        $installer = $this->getInstaller();

        return $installer->update($path);
    }

    /**
     * Uninstalls the given extension
     *
     * @param int    $id     ID of the extension row
     * @param string $type   Type of extension (component, module, ..)
     *
     * @return bool
     */
    public function uninstall($id, $type)
    {
        $installer = $this->getInstaller();

        return $installer->uninstall($type, $id);
    }

    /**
     * Retrieves value from the Joomla configuration.
     *
     * @param string $varname   Name of the configuration property
     * @param mixed  $default   Default value
     *
     * @return mixed
     */
    public function getCfg($varname, $default = null)
    {
        return JFactory::getConfig()->get($varname, $default);
    }

    /**
     * Enqueue flash message.
     *
     * @param string $msg   The message
     * @param string $type  Type of message (can be message/notice/error)
     *
     * @return void
     */
    public function enqueueMessage($msg, $type = 'message')
    {
        $this->_messageQueue[] = array('message' => $msg, 'type' => strtolower($type));
    }

    /**
     * Return all currently enqueued flash messages.
     *
     * @return array
     */
    public function getMessageQueue()
    {
        return $this->_messageQueue;
    }

    /**
     * Get the JInstaller object.
     *
     * @return JInstaller
     */
    public function getInstaller()
    {
        // @TODO keep one instance available per install package
        // instead of instantiating a new object each time.
        // Re-using the same instance for multiple installations will fail.
        return new JInstaller();
    }

    /**
     * Get the current template name.
     * Always return 'system' as template.
     *
     * @return string
     */
    public function getTemplate()
    {
        return 'system';
    }

    /**
     * Get the current application name.
     * Always returns 'cli'.
     *
     * @return string
     */
    public function getName()
    {
        return 'cli';
    }

    /**
     * Checks if interface is site or not.
     *
     * @return  bool
     */
    public function isSite()
    {
        return false;
    }

    /**
     * Checks if interface is admin or not.
     *
     * @return  bool
     */
    public function isAdmin()
    {
        return true;
    }

    /**
     * Method to load a PHP configuration class file based on convention and return the instantiated data object.  You
     * will extend this method in child classes to provide configuration data from whatever data source is relevant
     * for your specific application.
     * Additionally injects the root_user into the configuration.
     *
     * @param   string  $file   The path and filename of the configuration file. If not provided, configuration.php
     *                          in JPATH_BASE will be used.
     * @param   string  $class  The class name to instantiate.
     *
     * @return  mixed   Either an array or object to be loaded into the configuration object.
     *
     * @since   11.1
     */
    protected function fetchConfigurationData($file = '', $class = 'JConfig')
    {
        $config = parent::fetchConfigurationData($file, $class);

        // Inject the root user configuration
        if(isset($this->_options['root_user']))
        {
            $root_user = isset($this->_options['root_user']) ? $this->_options['root_user'] : 'root';

            if (is_array($config)) {
                $config['root_user'] = $root_user;
            }
            elseif (is_object($config)) {
                $config->root_user = $root_user;
            }
        }

        return $config;
    }

    /**
     * Creates a new Joomla session.
     *
     * @return JSession
     */
    protected function _startSession()
    {
        $name     = md5($this->getCfg('secret') . get_class($this));
        $lifetime = $this->getCfg('lifetime') * 60 ;
        $handler  = $this->getCfg('session_handler', 'none');

        $options = array(
            'name' => $name,
            'expire' => $lifetime
        );

        $session = JSession::getInstance($handler, $options);
        $session->initialise($this->input, $this->dispatcher);

        if ($session->getState() == 'expired') {
            $session->restart();
        } else {
            $session->start();
        }

        return $session;
    }

    /**
     * Load an object or array into the application configuration object.
     *
     * @param   mixed  $data  Either an array or object to be loaded into the configuration object.
     *
     * @return  Application  Instance of $this
     */
    public function loadConfiguration($data)
    {
        parent::loadConfiguration($data);

        JFactory::$config = $this->config;

        return $this;
    }

    /**
     * Determine if we are using a secure (SSL) connection.
     *
     * This method is a stub; Joomla 3.2.x requires this method to be available in the application object.
     *
     * @return  boolean  false
     * @since   12.2
     */
    public function isSSLConnection()
    {
        return false;
    }
    
    /**
     * Does nothing
     * 
     * This method is a stub; Some extensions use JFactory::getApplication()->redirect() inside their installscripts (such as NoNumberInstallerHelper)
     */
    public function redirect()
    {
    }

    /**
     * Flush the media version to refresh versionable assets
     *
     * @return  void
     *
     * @since   3.2
     */
    public function flushAssets()
    {
        $version = new JVersion();
        $version->refreshMediaVersion();
    }

    /**
     * Returns the application JRouter object.
     *
     * @param   string  $name     The name of the application.
     * @param   array   $options  An optional associative array of configuration settings.
     *
     * @return  JRouter
     *
     * @since   3.2
     */
    public static function getRouter($name = 'administrator', array $options = array())
    {
        if (!isset($name))
        {
            $app = JFactory::getApplication();
            $name = $app->getName();
        }

        try
        {
            $router = JRouter::getInstance($name, $options);
        }
        catch (Exception $e)
        {
            return null;
        }

        return $router;
    }

    /**
     * Enable logging to stdout of Joomla system messages.
     *
     * @param   int  $loglevel  The log level
     * @return  void
     */
    protected function _setupLogging($loglevel)
    {
        require_once JPATH_LIBRARIES . '/joomla/log/log.php';

        if ($loglevel == OutputInterface::VERBOSITY_NORMAL) {
            return;
        }

        switch ($loglevel)
        {
            case OutputInterface::VERBOSITY_DEBUG:
                $priority = JLog::ALL;
                break;
            case OutputInterface::VERBOSITY_VERY_VERBOSE:
                $priority = JLog::ALL & ~JLog::DEBUG;
                break;
            case OutputInterface::VERBOSITY_VERBOSE:
                $priority = JLog::ALL & ~JLog::DEBUG & ~JLog::INFO & ~JLog::NOTICE;
                break;
        }

        if ($this->_is_platform === true || version_compare(JVERSION, '3.0.0', '>='))
        {
            $callback = function ($entry) {
                $priorities = array(
                    JLog::EMERGENCY => 'EMERGENCY',
                    JLog::ALERT => 'ALERT',
                    JLog::CRITICAL => 'CRITICAL',
                    JLog::ERROR => 'ERROR',
                    JLog::WARNING => 'WARNING',
                    JLog::NOTICE => 'NOTICE',
                    JLog::INFO => 'INFO',
                    JLog::DEBUG => 'DEBUG'
                );

                $message = $priorities[$entry->priority] . ': ' . $entry->message . (empty($entry->category) ? '' : ' [' . $entry->category . ']') . "\n";

                fwrite(STDERR, $message);
            };

            $options = array('logger' => 'callback', 'callback' => $callback);
        }
        else
        {
            require_once dirname(__DIR__) . '/Joomla/Legacy/JLoggerStderr.php';

            $options = array('logger' => 'stderr');
        }


        JLog::addLogger($options, $priority);
    }

    public function __destruct()
    {
        // Clean-up to prevent PHP calling the session object's __destruct() method;
        // which will burp out Fatal Errors all over the place because the MySQLI connection
        // has already closed at that point.
        $session = \JFactory::$session;

        if(!is_null($session) && is_a($session, 'JSession')) {
            $session->close();
        }
    }
}

/**
 * Workaround for Joomla 3.4+
 * 
 * Fix Fatal error: Call to undefined function Composer\Autoload\includeFile() in /libraries/ClassLoader.php on line 43
 *
 * Fix Fatal error: Cannot redeclare Composer\Autoload\includeFile() (previously declared in
 *  phar:///usr/bin/composer/vendor/composer/ClassLoader.php:410) in /vendor/joomlatools/installer/src/Joomlatools/Composer/Application.php
 *  on line 403
 */
namespace Composer\Autoload;

if( !function_exists('Composer\Autoload\includeFile') )
{
    function includeFile($file)
    {
        include $file;
    }
}
