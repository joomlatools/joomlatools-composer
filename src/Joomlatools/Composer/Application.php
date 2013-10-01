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
    protected $_options      = array();

    public function __construct($options = array(), JInputCli $input = null, JRegistry $config = null, JDispatcher $dispatcher = null)
    {
        $this->_options = $options;

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

    public function authenticate($credentials)
    {
        $user = JFactory::getUser();

        foreach($credentials as $key => $value) {
            $user->$key = $value;
        }
    }

    public function hasExtension($element, $type = 'component')
    {
        $db = JFactory::getDbo();
        $sql = "SELECT `extension_id`, `state` FROM `#__extensions`"
                ." WHERE `element` = ".$db->quote($element)." AND `type` = ".$db->quote($type);

        $extension = $db->setQuery($sql)->loadObject();

        return ($extension && $extension->state != -1);
    }

    public function install($path)
    {
        $installer = $this->getInstaller();

        return $installer->install($path);
    }

    public function update($path)
    {
        $installer = $this->getInstaller();

        return $installer->update($path);
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
        // instead of instantiating a new object each time.
        // Re-using the same instance for multiple installations will fail.
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

    public function loadConfiguration($data)
    {
        parent::loadConfiguration($data);

        JFactory::$config = $this->config;

        return $this;
    }
}