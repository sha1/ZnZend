<?php
/**
 * ZnZend
 *
 * @author Zion Ng <zion@intzone.com>
 * @link   http://github.com/zionsg/ZnZend for canonical source repository
 */

namespace ZnZend\Listener;

use Zend\EventManager\EventInterface;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\Log\LoggerInterface;
use Zend\Mvc\MvcEvent;

/**
 * A simple listener to listen to logging events and exceptions
 *
 * This is used to show sample code in Module.php on how to set up a log listener.
 * This can also be easily extended by overriding the log() and logException() methods.
 */
class LogListener extends AbstractListenerAggregate
{
    /**
     * Events to listen to
     *
     * @var array
     */
    protected $logEvents = array('emerg', 'alert', 'crit', 'err', 'warn', 'notice', 'info', 'debug');

    /**
     * Logger
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Constructor
     *
     * Logger is made optional for flexibility's sake as the log() method may not use a logger.
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * Defined in ListenerAggregateInterface via AbstractListenerAggregate; Attach one or more listeners
     *
     * Once attached, the listener will listen to the events named after the RFC5424 severity levels,
     * eg. when the following code is run in a controller:
     *     $this->getEventManager()->trigger('log', $this, array('param' => 'value'));
     *
     * Listens for exceptions as well as Zend\Log\Logger::registerExceptionHandler() does not seem to work.
     *
     * @param EventManagerInterface $events
     * @return void
     */
    public function attach(EventManagerInterface $events)
    {
        $sharedEvents      = $events->getSharedManager(); // must use shared manager else it will not work
        $this->listeners[] = $sharedEvents->attach('*', $this->logEvents, array($this, 'log'));
        $this->listeners[] = $sharedEvents->attach(
            'Zend\Mvc\Application',
            array(MvcEvent::EVENT_DISPATCH_ERROR, MvcEvent::EVENT_RENDER_ERROR),
            array($this, 'logException')
        );
    }

    /**
     * Write log event
     *
     * Method must be public due to callback in attach().
     *
     * @param  EventInterface $e
     * @return void
     */
    public function log(EventInterface $e)
    {
        $logLevel = $e->getName();
        if (null == $this->logger || !in_array($logLevel, $this->logEvents)) {
            return;
        }

        $params = $e->getParams();
        $this->logger->{$logLevel}(sprintf('%s: %s', $logLevel, json_encode($params)));
    }

    /**
     * Log exception
     *
     * Method must be public due to callback in attach().
     *
     * @param  EventInterface $e
     * @return void
     */
    public function logException(EventInterface $e)
    {
        $exception = $e->getResult()->exception;
        if (!$exception) {
            return;
        }

        $trace = $exception->getTraceAsString();
        $messages = array();
        $i = 1;
        do {
            $messages[] = $i++ . ': ' . $exception->getMessage();
        } while ($exception = $exception->getPrevious());

        $logMessage = "Exception:\n" . implode("\n", $messages)
                    . "\n\nTrace:\n" . $trace;

        $this->logger->crit($logMessage); // passing $e->getParams() as 2nd argument will hang the application
    }
}
