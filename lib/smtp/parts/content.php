<?php
namespace hathoora\kabootar\lib\smtp\parts
{
    use hathoora\kabootar\lib\smtp\emailConnection,
        hathoora\kabootar\lib\smtp\helper\emailper;

    /**
     * Content of email
     */
    class content
    {
        /**
         * Holds email connection
         */
        private $emailConnection;

        /**
         * Raw content
         */
        private $content;

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
        public function __construct(emailConnection &$emailConnection, $content)
        {
            $this->emailConnection = $emailConnection;
            $this->content = $content;
        }

        /**
         * Marks the sender as valid
         */
        public function isValid()
        {
            $this->isValid = true;
        }

        /**
         * Marks the sender as invalid
         */
        public function isInvalid()
        {
            $this->isValid = false;

            // this should be done in emailConnection class
            $this->emailConnection->respond(503, 'Bad sender\'s mailbox address', '5.1.8');
        }
    }
}