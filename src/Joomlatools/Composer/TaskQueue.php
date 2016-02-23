<?php
/**
 * Joomlatools Composer plugin - https://github.com/joomlatools/joomlatools-composer
 *
 * @copyright	Copyright (C) 2011 - 2016 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link		http://github.com/joomlatools/joomlatools-composer for the canonical source repository
 */

namespace Joomlatools\Composer;

use SplQueue;

/**
 * Task queue class
 *
 * @author  Steven Rombauts <https://github.com/stevenrombauts>
 * @package Joomlatools\Composer
 */
class TaskQueue extends SplQueue
{
    private static $__instance = null;

    public function __construct()
    {
        $this->setIteratorMode(SplQueue::IT_MODE_DELETE);
    }

    /**
     * Get instance of this class
     *
     * @return TaskQueue $instance
     */
    public static function getInstance(IOInterface $io = null, Composer $composer = null)
    {
        if (!self::$__instance) {
            self::$__instance = new TaskQueue();
        }

        return self::$__instance;
    }
}
