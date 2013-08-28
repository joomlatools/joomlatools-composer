<?php
namespace Joomlatools\Application;

use \JApplicationCli as JApplicationCli;
use \JFactory as JFactory;
use \JDispatcher as JDispatcher;
use \JPluginHelper as JPluginHelper;
use \JAuthentication as JAuthentication;

class Application extends JApplicationCli
{
    protected $_messageQueue = array();

    public function initialise($options = array())
    {
        jimport('joomla.application.component.helper');
        jimport('joomla.application.menu');
        jimport('joomla.environment.uri');
        jimport('joomla.event.dispatcher');
        jimport('joomla.utilities.utility');
        jimport('joomla.utilities.arrayhelper');

        jimport('joomla.application.module.helper');
    }

    public function login($credentials, $options = array())
    {
        return true;
    }

    public function getCfg($varname, $default = null)
    {
        return \JFactory::getConfig()->get('' . $varname, $default);
    }

    public function enqueueMessage($msg, $type = 'message')
    {
        $this->_messageQueue[] = array('message' => $msg, 'type' => strtolower($type));
    }

    public function getMessageQueue()
    {
        return $this->_messageQueue;
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
}