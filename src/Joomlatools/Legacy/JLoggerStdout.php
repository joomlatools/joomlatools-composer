<?php
class JLoggerStdout extends JLogger
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

        fwrite(STDOUT, $message);
    }
}
