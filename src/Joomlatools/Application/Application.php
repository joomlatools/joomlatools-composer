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

        //JPluginHelper::importPlugin('system');
    }

    public function login($credentials, $options = array())
    {
        jimport('joomla.user.authentication');

        $authenticate = JAuthentication::getInstance();
        $response	  = $authenticate->authenticate($credentials, $options);

        if($response->status === JAUTHENTICATE_STATUS_SUCCESS)
        {
            $session = JFactory::getSession();

            // we fork the session to prevent session fixation issues
            $session->fork();

            $this->_createSession($session->getId());

            // Import the user plugin group
            JPluginHelper::importPlugin('user');

            $results = $this->triggerEvent('onLoginUser', array((array)$response, $options));

            return true;
        }

        return false;
    }

    protected function _createSession($name)
    {
        $options = array();
        $options['name'] = $name;

        $session = JFactory::getSession($options);

        jimport('joomla.database.table');
        $storage = JTable::getInstance('session');
        $storage->purge($session->getExpire());

        // Session exists and is not expired, update time in session table
        if ($storage->load($session->getId())) {
            $storage->update();
            return $session;
        }

        //Session doesn't exist yet, initalise and store it in the session table
        $session->set('registry',	new \JRegistry('session'));
        $session->set('user',		new \JUser());

        if(!$storage->insert( $session->getId(), 1)) {
            jexit( $storage->getError());
        }

        return $session;
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