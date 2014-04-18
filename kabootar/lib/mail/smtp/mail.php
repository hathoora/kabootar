<?php
namespace hathoora\kabootar\lib\mail\smtp
{
    use Evenement\EventEmitter,
        React\Socket\ConnectionInterface;

    /**
     * Class mail object
     * @package hathoora\kabootar\lib\mail\smtp
     */
    class mail extends EventEmitter
    {
        /**
         * @var \React\Socket\ConnectionInterface
         */
        private $conn;

        /**
         * Session id
         */
        private $sessionId;

        /**
         * State of session (EHLO, MAIL, RCPT, DATA)
         * @var
         */
        private $sessionState;

        /**
         * $mailData consists of two things:
         * A mail object contains an envelope and content @ http://tools.ietf.org/html/rfc5321#section-2.3.1
         *
         */
        private $envelop;

        /**
         * $mailData consists of two things:
         * A mail object contains an envelope and content @ http://tools.ietf.org/html/rfc5321#section-2.3.1
         */
        private $content;

        public function __construct(ConnectionInterface $conn)
        {
            $this->conn = $conn;
            $this->sessionId = $this->generateSessionId();
        }

        /**
         * Makes sense of incoming mail data and emit events accordingly
         *
         * Order of commands @ http://tools.ietf.org/html/rfc5321#page-44
         * EHLO -> Clear state -> MAIL -> RCPT (multiple) -> DATA
         * NOOP, HELP, EXPN, VRFY, and RSET commands can be used at any time during a session
         * @param $data
         */
        public function feed($data)
        {
            if (preg_match('/^RSET\r\n$/i', $data))
            {
                $this->reset();
                $this->write(250, 'Flushed', '2.1.5');
            }
            else if (preg_match('/^(EHLO|HELO)/i', $data))
            {
                $this->reset();
                $this->sessionState = 'EHLO';
            }

            $this->emit('stream', array($this));
        }

        /**
         * Write to stream
         */
        public function write($code, $message, $extendedCode = null)
        {
            if ($extendedCode)
                $extendedCode = $extendedCode .' ';

            return $this->conn->write($code . ' ' . $extendedCode . mailParser::messageln($message . ' - '. $this->getSessionId()));
        }

        /**
         * Returns session id
         */
        public function getSessionId()
        {
            return $this->sessionId;
        }

        /**
         * Returns session id
         */
        public function getSessionState()
        {
            return $this->sessionState;
        }

        /**
         * Reset's sessions state
         */
        public function reset()
        {
            $this->sessionState = $this->envelop = $this->content = null;
        }

        /**
         * Generate a uniq smtp id
         */
        private function generateSessionId()
        {
            return uniqid('', true);
        }
    }
}

