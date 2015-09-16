<?php
namespace BCC\ResqueBundle;

/**
 * Resque default logger PSR-3 compliant
 *
 * @package		Resque/Stat
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class ResqueDBLogger extends \Psr\Log\AbstractLogger
{
    public $verbose;

    /** @var Worker */
    public $worker;

    public function __construct($verbose = false, $worker) {
        $this->verbose = $verbose;
        $this->worker = $worker;
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed   $level    PSR-3 log level constant, or equivalent string
     * @param string  $message  Message to log, may contain a { placeholder }
     * @param array   $context  Variables to replace { placeholder }
     * @return null
     */
    public function log($level, $message, array $context = array())
    {
        $string = "";



        if ($this->verbose) {
            $string = '[' . $level . '] [' . strftime('%T %Y-%m-%d') . '] ' . $this->interpolate($message, $context) . PHP_EOL;
        }else if (!($level === Psr\Log\LogLevel::INFO || $level === Psr\Log\LogLevel::DEBUG)) {
            $string = '[' . $level . '] ' . $this->interpolate($message, $context) . PHP_EOL;
        }
        fwrite(
            STDOUT, $string
        );

        $currentJob = $this->worker->getCurrentJob();

        if (!is_null($currentJob)) {
            $statusPacket = json_decode(\Resque::redis()->get('job:' . $currentJob->job->payload['id'] . ':log'), true);

            if (is_null($statusPacket)) {
                $statusPacket = array();
            }

            if (!isset($statusPacket['log'])) {
                $statusPacket['log'] = '';
            }

            $statusPacket['log'] = $statusPacket['log'].PHP_EOL.$string;

            $items = array(
                'log' => $statusPacket['log']
            );

            \Resque::redis()->set('job:' . $currentJob->job->payload['id'] . ':log', json_encode($items));
        }
    }

    /**
     * Fill placeholders with the provided context
     * @author Jordi Boggiano j.boggiano@seld.be
     *
     * @param  string  $message  Message to be logged
     * @param  array   $context  Array of variables to use in message
     * @return string
     */
    public function interpolate($message, array $context = array())
    {
        // build a replacement array with braces around the context keys
        $replace = array();
        foreach ($context as $key => $val) {
            $replace['{' . $key . '}'] = $val;
        }

        // interpolate replacement values into the message and return
        return strtr($message, $replace);
    }
}