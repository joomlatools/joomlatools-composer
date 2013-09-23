<?php
namespace Joomlatools\Composer;

use \JApplicationCli as JApplicationCli;
use \JDispatcher as JDispatcher;
use \JFactory as JFactory;
use \JInstaller as JInstaller;
use \JPluginHelper as JPluginHelper;

class Application extends JApplicationCli
{
    protected $_messageQueue = array();

    public function __construct($options = array(), JInputCli $input = null, JRegistry $config = null, JDispatcher $dispatcher = null)
    {
        parent::__construct($input, $config, $dispatcher);

        $this->_initialize();
    }

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

        // Load plugins
        JPluginHelper::importPlugin('system');

        // Load required languages
        $lang = JFactory::getLanguage();
        $lang->load('lib_joomla', JPATH_ADMINISTRATOR, null, true);
        $lang->load('com_installer', JPATH_ADMINISTRATOR, null, true);
    }

    public function authenticate()
    {
        $user = JFactory::getUser();

        $properties = array(
            'name'      => 'root',
            'username'  => 'root',
            'groups'    => array(8),
            'email'     => 'root@localhost.home'
        );

        foreach($properties as $property => $value) {
            $user->{$property} = $value;
        }
    }

    public function hasExtension($identifier)
    {
        $db = JFactory::getDbo();
        $sql = "SELECT extension_id, state FROM #__extensions WHERE element = ".$db->quote($identifier);

        $extension = $db->setQuery($sql)->loadObject();

        return ($extension && $extension->state != -1);
    }

    public function install($path)
    {
        $installer = $this->getInstaller();

        return $installer->install($path);
    }

    public function getCfg($varname, $default = null)
    {
        return JFactory::getConfig()->get('' . $varname, $default);
    }

    public function enqueueMessage($msg, $type = 'message')
    {
        $this->_messageQueue[] = array('message' => $msg, 'type' => strtolower($type));
    }

    public function getMessageQueue()
    {
        return $this->_messageQueue;
    }

    public function getInstaller()
    {
        // @TODO keep one instance available per install package
        // and not per composer run, as this will break multiple installations in one go.
        return new JInstaller();
    }

    public function getTemplate()
    {
        return 'system';
    }

    public function getName()
    {
        return 'cli';
    }

    public function isSite()
    {
        return false;
    }

    public function isAdmin()
    {
        return true;
    }

    protected function fetchConfigurationData($file = '', $class = 'JConfig')
    {
        $config = parent::fetchConfigurationData($file, $class);

        // Inject the root user configuration
        if (is_array($config)) {
            $config['root_user'] = 'root';
        }
        elseif (is_object($config)) {
            $config->root_user = 'root';
        }

        return $config;
    }

    public function loadConfiguration($data)
    {
        parent::loadConfiguration($data);

        JFactory::$config = $this->config;

        return $this;
    }
}