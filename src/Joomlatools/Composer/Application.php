<?php
/**
 * Joomlatools Composer plugin - https://github.com/joomlatools/joomla-composer
 *
 * @copyright	Copyright (C) 2011 - 2013 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link		http://github.com/joomlatools/joomla-composer for the canonical source repository
 */

namespace Joomlatools\Composer;

use \JApplicationCli as JApplicationCli;
use \JDispatcher as JDispatcher;
use \JFactory as JFactory;
use \JInstaller as JInstaller;
use \JPluginHelper as JPluginHelper;
use \JSession as JSession;
use \JRouter as JRouter;
use \JVersion as JVersion;

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
     * Checks if this Joomla installation has a certain element installed.
     *
     * @param string $element   The name of the element
     * @param string $type      The type of extension
     *
     * @return bool
     */
    public function hasExtension($element, $type = 'component')
    {
        $db = JFactory::getDbo();
        $sql = "SELECT `extension_id`, `state` FROM `#__extensions`"
                ." WHERE `element` = ".$db->quote($element)." AND `type` = ".$db->quote($type);

        $extension = $db->setQuery($sql)->loadObject();

        return ($extension && $extension->state != -1);
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
