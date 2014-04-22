<?php
namespace hathoora\kabootar\lib\email\smtp
{
    use hathoora\kabootar\lib\email\smtp\parts\emailSender,
        hathoora\kabootar\lib\email\smtp\parts\emailRecipient,
        React\Socket\ConnectionInterface,
        Evenement\EventEmitter;

    /**
     * Class mail object
     * @package hathoora\kabootar\lib\mail\smtp
     */
    class emailConnection extends EventEmitter
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
         * Store parsed goodies
         */
        private $theEmail = null;

        public function __construct(ConnectionInterface $conn)
        {
            $this->conn = $conn;
            $this->sessionId = $this->generateSessionId();
            $this->theEmail = new \stdClass();
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
            $this->command = $emitSecondObject = $arrSuccess = $arrError = null;

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
                    $arrParseFeed = emailHelper::parseFROMFeed($data);
                    if (is_array($arrParseFeed) && ($from = $arrParseFeed['email']))
                    {
                        $this->sessionState = 'MAIL';

                        /**
                         * Let the "stream" emit decide what needs to happen here
                         *
                         * we are passing $this to emailAddress, which would respond to
                         * emailSender->isValid() & emailSender->isInvalid() functions
                         */
                        $emitSecondObject = new emailSender($this, $from);
                        $this->theEmail->from = $emitSecondObject;
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
                    $arrParseFeed = emailHelper::parseTOFeed($data);
                    if (is_array($arrParseFeed) && ($to = $arrParseFeed['email']))
                    {
                        $this->sessionState = 'RCPT';

                        /**
                         * Let the "stream" emit decide what needs to happen here
                         *
                         * we are passing $this to emailAddress, which would respond to
                         * emailSender->isValid() & emailSender->isInvalid() functions
                         */
                        $emitSecondObject = new emailRecipient($this, $to);

                        if (!isset($this->theEmail->to))
                            $this->theEmail->to = array();
                        $this->theEmail->to[$to] = $emitSecondObject;
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
                $this->command = 'DATA';

                // check if we are in valid session state
                if ($this->sessionState == 'RCPT')
                {
                    $this->sessionState = 'DATA';
                    $arrSuccess = array(array(354, 'Go ahead'));
                }
                // EHLO not initialized
                else if (!$this->sessionState)
                    $arrError = array(array(503, 'EHLO/HELO first', '5.5.1'));
                else
                    $arrError = array(array(503, 'Invalid command', '5.5.1'));
            }
            // we are getting data
            else if ($this->sessionState == 'DATA')
            {
                $this->command = $this->sessionState = 'DATA-INCOMING';

                if (!isset($this->theEmail->content))
                    $this->theEmail->content = $data;
            }
            // we have got all the data
            else if ($this->sessionState == 'DATA-INCOMING')
            {
                // this is end of email
                if (preg_match('/^\.\r\n$/', $data))
                {
                    $this->command = $this->sessionState = 'DATA-END';
                    $arrSuccess = array(array(250, 'OK'));
                }
            }
            else if ($mailCmd == 'QUIT')
            {
                $this->command = $this->sessionState = 'QUIT';
                $arrSuccess = array(array(221, 'closing connection', '2.0.0'));
            }
            else
                $arrError = array(array(503, 'Invalid command', '5.5.1'));

            // there were errors?
            if (is_array($arrError))
                $this->logClientErrors($arrError);

            // let devs reply to stream (via emit "stream")
            $this->respondBuffer = (array) $arrSuccess + (array) $arrError;

            // notify so one can implement their own handlers
            $this->emit('stream', array($this, $emitSecondObject));

            // if steam emit was not overwritten, proceed with buffered response
            $this->sendBufferedResponse();
        }
        /**
         * Sends buffered response
         */
        private function sendBufferedResponse()
        {
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

            $message = $code . $extendedCode . emailHelper::messageln($message);

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
         * check if stream is readable
         */
        public function isReadable()
        {
            return $this->conn->isReadable();
        }

        /**
         * Close conn
         */
        public function close()
        {
            return $this->conn->close();
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

            echo "$who: $data";
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