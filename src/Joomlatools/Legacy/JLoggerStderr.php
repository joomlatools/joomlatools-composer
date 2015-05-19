<?php
/**
 * Joomlatools Composer plugin - https://github.com/joomlatools/joomla-composer
 *
 * @copyright	Copyright (C) 2011 - 2013 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link		http://github.com/joomlatools/joomla-composer for the canonical source repository
 */

/**
 * STDERR Joomla Logger
 *
 * This class adds legacy support for Joomla 2.5 logging to STDERR.
 *
 * @author  Steven Rombauts <https://github.com/stevenrombauts>
 * @package Joomlatools\Composer
 */
class JLoggerStderr extends JLogger
{
    protected $priorities = array(
        JLog::EMERGENCY => 'EMERGENCY',
        JLog::ALERT => 'ALERT',
        JLog::CRITICAL => 'CRITICAL',
        JLog::ERROR => 'ERROR',
        JLog::WARNING => 'WARNING',
        JLog::NOTICE => 'NOTICE',
        JLog::INFO => 'INFO',
        JLog::DEBUG => 'DEBUG'
    );

    public function addEntry(JLogEntry $entry)
    {
        $message = $this->priorities[$entry->priority] . ': ' . $entry->message . (empty($entry->category) ? '' : ' [' . $entry->category . ']') . "\n";

        fwrite(STDERR, $message);
    }
}
