<?php

namespace Sintattica\Atk\Session;


/**
 * This class implements the ATK message queue for showing messages
 * at the top of a page.
 *
 * @author Patrick van der Velden <patrick@ibuildings.nl>
 */
class MessageQueue
{
    /**
     * Message queue flags.
     */
    const AMQ_GENERAL = 0;
    const AMQ_SUCCESS = 1;
    const AMQ_WARNING = 2;
    const AMQ_FAILURE = 3;

    /** @var  SessionManager $sessionManager */
    protected $sessionManager;


    public function __construct(SessionManager $sessionManager)
    {
        $this->sessionManager = $sessionManager;
    }

    /**
     * Add message to queue.
     *
     * @static
     *
     * @param string $txt
     * @param int $type
     *
     * @return bool Success
     */
    public function addMessage($txt, $type = self::AMQ_GENERAL)
    {
        return $this->_addMessage($txt, $type);
    }

    /**
     * Get the name of the message type.
     *
     * @param int $type The message type
     *
     * @return string The name of the message type
     */
    public function _getTypeName($type)
    {
        if ($type == self::AMQ_SUCCESS) {
            return 'success';
        } else {
            if ($type == self::AMQ_FAILURE) {
                return 'failure';
            } else {
                if ($type == self::AMQ_WARNING) {
                    return 'warning';
                } else {
                    return 'general';
                }
            }
        }
    }

    /**
     * Add message to queue (private).
     *
     * @param string $txt
     * @param int $type
     *
     * @return bool Success
     */
    public function _addMessage($txt, $type)
    {
        $q = &$this->getQueue();
        $q[] = array('message' => $txt, 'type' => $this->_getTypeName($type));

        return true;
    }

    /**
     * Get first message from queue and remove it.
     *
     * @static
     *
     * @return string message
     */
    public function getMessage()
    {
        return $this->_getMessage();
    }

    /**
     * Get first message from queue and remove it (private).
     *
     * @return string message
     */
    public function _getMessage()
    {
        $q = &$this->getQueue();

        return array_shift($q);
    }

    /**
     * Get all messages from queue and empty the queue.
     *
     * @return array messages
     */
    public function getMessages()
    {
        return $this->_getMessages();
    }

    /**
     * Get all messages from queue and empty the queue (private).
     *
     * @return array messages
     */
    public function _getMessages()
    {
        $q = &$this->getQueue();
        $queue_copy = $q;
        $q = [];

        return $queue_copy;
    }

    /**
     * Get the queue.
     *
     * @return array The message queue
     */
    public function &getQueue()
    {
        $session = &$this->sessionManager->getSession();
        if (!isset($session['atkmessagequeue'])) {
            $session['atkmessagequeue'] = [];
        }

        return $session['atkmessagequeue'];
    }
}
