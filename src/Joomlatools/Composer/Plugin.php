<?php
/**
 * Joomlatools Composer plugin - https://github.com/joomlatools/joomlatools-composer
 *
 * @copyright	Copyright (C) 2011 - 2016 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link		http://github.com/joomlatools/joomlatools-composer for the canonical source repository
 */

namespace Joomlatools\Composer;

use Joomlatools\Joomla\Bootstrapper;
use Joomlatools\Joomla\Util;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

/**
 * Composer plugin class
 *
 * @author  Steven Rombauts <https://github.com/stevenrombauts>
 * @package Joomlatools\Composer
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{
    /** @var Composer $composer */
    protected $_composer;
    /** @var IOInterface $io */
    protected $_io;

    /**
     * Apply plugin modifications to composer
     *
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        //still firing postAutoloadDump for standalone so this needs to be set
        $this->_composer = $composer;
        $this->_io = $io;

        //if neither must be standalone
        if (!Util::isJoomla() && !Util::isJoomlatoolsPlatform()) {
            return true;
        }

        $credentials = $this->_composer->getConfig()->get('joomla');

        if(is_null($credentials) || !is_array($credentials)) {
            $credentials = array();
        }

        $bootstrapper = Bootstrapper::getInstance();
        $bootstrapper->setIO($this->_io);
        $bootstrapper->setCredentials($credentials);

        $installer = new ComposerInstaller($this->_io, $this->_composer);
        $composer->getInstallationManager()->addInstaller($installer);
    }

    public function deactivate(Composer $composer, IOInterface $io) {}

    /**
     * Prepare the plugin to be uninstalled
     *
     * This will be called after deactivate.
     *
     * @param Composer    $composer
     * @param IOInterface $io
     */
    public function uninstall(Composer $composer, IOInterface $io) {}

    public static function getSubscribedEvents()
    {
        return array(
            ScriptEvents::POST_AUTOLOAD_DUMP => 'postAutoloadDump'
        );
    }

    public function postAutoloadDump(Event $event)
    {
        $extensionInstaller = new ExtensionInstaller($this->_io);
        $extensionInstaller->execute();
    }

}