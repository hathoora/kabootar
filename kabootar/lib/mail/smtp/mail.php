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
         * Current mail command (EHLO, MAIl, RCPT, DATA etc..)
         *
         * @var
         */
        private $command;

        /**
         * State of session (EHLO, MAIL, RCPT, DATA)
         *
         * For a given command, say MAIL the value of this variable will only be
         * MAIL when client has passed correction information (syntax) and we have
         * validated it.
         *
         * In other words, for a valid command $this->command == $this->sessionState
         * @var
         */
        private $sessionState;

        /**
         * To keep track of client errors during connection
         * This value is not reset with RSET or EHLO command
         *
         * @var int
         */
        private $totalClientErrors = 0;

        /**
         * This is for debugging to store of server <-> client transaction
         */
        private $completeTransaction;

        /**
         * @var
         */
        private $debug = false;

        /**
         * Keep auto generated repsonse from kabootar mail message in buffer
         * to allow dev to implement their own logic via "stream" emit
         *
         * This is reset with every respond() call
         */
        private $respondBuffer;

        /**
         * Keeping track of client errors
         * this is reset with RSET & EHLO command
         */
        private $arrClientErrors = array();

        /**
         * MAIL FROM user info
         */
        private $fromEmailAddress = null;
        private $fromEmailName = null;

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
         * @url Enhanced Mail System Status Codes https://tools.ietf.org/html/rfc3463
         * @param $data
         */
        public function feed($data)
        {
            $this->logTransaction('C', $data);

            preg_match('/^(\w+)/', $data, $arrMatches);
            $mailCmd = strtoupper(@array_pop($arrMatches));
            $this->command = $arrSuccess = $arrError = null;

            /**
                RSET @ http://tools.ietf.org/html/rfc5321#section-4.1.1.5
                Syntax -> rset = "RSET" CRLF
                S: 250
             */
            if ($mailCmd == 'RSET' && preg_match('/^RSET\r\n$/i', $data))
            {
                $this->reset();
                $this->command = 'RSET';
                $arrSuccess = array(array(250, 'Flushed', '2.1.5'));
            }
            /**
                S: 250
                E: 504 (a conforming implementation could return this code only
                in fairly obscure cases), 550, 502 (permitted only with an old-
                style server that does not support EHLO)
             */
            else if ($mailCmd == 'EHLO' || $mailCmd == 'HELO')
            {
                $this->reset();
                $this->sessionState = $this->command = 'EHLO';

                $arrSuccess = array(array('250-', 'at your service,' . $this->conn->getRemoteAddress()),
                                    array('250-', 'SIZE 4999') /*. $this->getConfig('maxMailSize'))*/,
                                    array('250-', '8BITMIME'),
                                    array('250-', 'ENHANCEDSTATUSCODES'),
                                    array(250, 'CHUNKING'));
            }
            /**
                MAIL @ http://tools.ietf.org/html/rfc5321#section-3.3
                syntax is MAIL FROM:<reverse-path> [SP <mail-parameters> ] <CRLF>
                with size extension is also valid @ MAIL FROM:<userx@test.ex> SIZE=1000000000
                S: 250
                E: 552, 451, 452, 550, 553, 503, 455, 555
             */
            else if ($mailCmd == 'MAIL')
            {
                $this->command = 'MAIL';
                // check if we are in valid session state
                if ($this->sessionState == 'EHLO')
                {
                    // allow space in between MAIL FROM & <address@domain> as I have seen
                    // other smtps allowing it
                    if (preg_match('/^MAIL\sFROM:\s?<(.+?)>\s{0,}(SIZE=(\d+))?/i', $data, $arrMatches))
                    {
                        $size = null;
                        $from = $arrMatches[1];
                        if (isset($arrMatches[3])) // for checking SIZE=VALUE
                            $size = $arrMatches[3];

                        // validate $from address
                        if (mailParser::validateEmailAddress($from))
                        {
                            $arrSuccess = array(array('250', 'Ok', '2.1.0'));
                            $this->sessionState = 'MAIL';
                        }
                        // invalid email address format
                        else
                            $arrError = array(array(503, 'Bad sender\'s mailbox address', '5.1.8'));
                    }
                    else
                        $arrError = array(array(503, 'Syntax error', '5.5.2'));
                }
                // EHLO not initialized
                else if (!$this->sessionState)
                    $arrError = array(array(503, 'EHLO/HELO first', '5.5.1'));
                else
                    $arrError = array(array(503, 'Invalid command', '5.5.1'));
            }
            /**
                S: 250, 251 (but see Section 3.4 for discussion of 251 and 551)
                E: 550, 551, 552, 553, 450, 451, 452, 503, 455, 555
             */
            else if ($mailCmd == 'RCPT')
            {
                $this->command = 'RCPT';
                // check if we are in valid session state
                if ($this->sessionState == 'MAIL')
                {
                    if (preg_match('/^RCPT\sTO:\s?<(.+?)>/i', $data, $arrMatches))
                    {
                        $from = $arrMatches[1];

                        // validate $from address
                        if (mailParser::validateEmailAddress($from))
                        {
                            // let the "stream" emit decide what needs to happen here
                        }
                        // invalid email address format
                        else
                            $arrError = array(array(503, 'Bad destination mailbox address syntax', '5.1.3'));
                    }
                    else
                        $arrError = array(array(503, 'Syntax error', '5.5.2'));
                }
                else
                    $arrError = array(array(503, 'Mail first', '5.5.1'));
            }
            /**
                I: 354 -> data -> S: 250
                       E: 552, 554, 451, 452
                       E: 450, 550 (rejections for policy reasons)
                 E: 503, 554
             */
            else if ($mailCmd == 'DATA')
            {

            }
            else
                $arrError = array(array(503, 'Invalid command', '5.5.1'));

            // there were errors?
            if (is_array($arrError))
                $this->logClientErrors($arrError);

            // let devs reply to stream (via emit "stream")
            $this->respondBuffer = (array) $arrSuccess + (array) $arrError;

            // notify so one can implement their own handlers
            $this->emit('stream', array($this));

            if (is_array($this->respondBuffer) && count($this->respondBuffer))
            {
                // copy to local var as respond would reset $this->respondBuffer
                $respondBuffer = $this->respondBuffer;

                foreach ($respondBuffer as $_arrMsg)
                {
                    call_user_func_array(array($this, 'respond'), (array) $_arrMsg);
                }
            }
        }

        /**
         * Write to stream
         */
        public function respond($code, $message, $extendedCode = null)
        {
            $this->respondBuffer = null;

            if (preg_match('/-$/', $code))
                $extendedCode = null;
            else
            {
                $code = $code .' ';

                if ($extendedCode)
                    $extendedCode = $extendedCode .' ';
            }

            if ($this->sessionState != 'EHLO')
                $message .= ' - '. $this->getSessionId();

            $message = $code . $extendedCode . mailParser::messageln($message);

            $this->logTransaction('S', $message);

            return $this->conn->write($message);
        }

        /**
         * Returns session id
         */
        public function getSessionId()
        {
            return $this->sessionId;
        }

        /**
         * Sets session state
         *
         * @param $state
         */
        public function setSessionState($state)
        {
            $this->sessionState = $state;
        }
        /**
         * Returns session id
         */
        public function getSessionState()
        {
            return $this->sessionState;
        }

        /**
         * Returns current command
         */
        public function getCommand()
        {
            return $this->command;
        }

        /**
         * Returns true when client has send valid command, in sequence without
         * any syntax errors
         */
        public function isValidCommand()
        {
            return $this->command == $this->sessionState;
        }

        /**
         * Reset's sessions state
         */
        public function reset()
        {
            $this->sessionState = $this->envelop = $this->content = null;
            $this->arrClientErrors = array();
        }

        /**
         * Generate a uniq smtp id
         */
        private function generateSessionId()
        {
            return uniqid();
        }

        /**
         * log transaction for debugging
         *
         * @param string $who (S)erver, (C)lient
         */
        private function logTransaction($who, $data)
        {
            if ($this->debug)
                $this->completeTransaction .= $data;
        }

        /**
         * log errors
         *
         *
         */
        private function logClientErrors($arrError)
        {
            $this->totalClientErrors++;
            if ($this->debug && is_array($arrError))
                array_push($this->arrClientErrors, array_pop($arrError));
        }
    }
}