<?php
namespace hathoora\kabootar\lib\email\smtp\parts
{
    use hathoora\kabootar\lib\email\smtp\emailConnection,
        hathoora\kabootar\lib\email\smtp\emailHelper,
        Evenement\EventEmitter;

    /**
     * TO, CC, BCC - person getting the email
     *
     * @package hathoora\kabootar\lib\mail\smtp
     */
    class emailRecipient extends EventEmitter
    {
        /**
         * Holds email connection
         */
        private $emailConnection;

        /**
         * Email of the sender
         *
         * @var null
         */
        private $email = null;

        /**
         * When we verify that sender address is valid
         *
         * @var bool
         */
        private $isValid = false;

        /**
         * @param emailConnection $emailConnection
         * @param $email
         */
        public function __construct(emailConnection &$emailConnection, $email)
        {
            $this->emailConnection = $emailConnection;
            $this->email = $email;
        }

        /**
         * Get email address of sender
         */
        public function getAddress()
        {
            return $this->email;
        }

        /**
         * Returns the part after @ of email address
         */
        public function getDomain()
        {
            return emailHelper::getDomainFromEmailAddress($this->email);
        }

        /**
         * Marks the sender as valid
         */
        public function isValid()
        {
            $this->isValid = true;

            // this should be done in emailConnection class
            $this->emailConnection->respond(250, 'OK', '2.1.0');
        }

        /**
         * Marks the sender as invalid
         */
        public function isInvalid()
        {
            $this->isValid = false;

            // this should be done in emailConnection class
            $this->emailConnection->respond(503, 'Bad destination mailbox address syntax', '5.1.3');
        }
    }
}