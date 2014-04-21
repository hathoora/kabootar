<?php
namespace hathoora\kabootar\lib\mail\smtp
{
    use Evenement\EventEmitter,
        React\Socket\ServerInterface as SocketServerInterface;

    /** @event request */
    class server extends EventEmitter implements serverInterface
    {
        /**
         * @var \React\Socket\ServerInterface
         */
        private $socket;
        private $config;
        private $version = 0.1;

        public function __construct(SocketServerInterface $socket, array $config = array())
        {
            $this->socket = $socket;

            // default configs
            if (!isset($config['port']))
                $config['port'] = 25;
            if (!isset($config['listenIP']))
                $config['listenIP'] = 'localhost';
            if (!isset($config['hostname']))
                $config['hostname'] = 'kabootar mail';
            if (!isset($config['maxMailSize']))
                $config['maxMailSize'] = 35882577;

            $this->config = $config;
            $this->socket->listen(
                                    $this->getConfig('port'),
                                    $this->getConfig('listenIP'));

            $this->socket->on('connection', function($conn)
            {
                // ref http://cr.yp.to/smtp/ehlo.html
                $mail = new mail($conn);
                $mail->respond(220, $this->getconfig('hostname') .' Kabootar Mail Server v' . $this->version);

                $mail->on('stream', function($mail)
                {
                    $this->emit($mail->getCommand(), array($mail));
                });

                $conn->on('data', array($mail, 'feed'));
            });
        }

        /**
         * Get config
         */
        public function getConfig($key, $defaultValue = null)
        {
            $value = $defaultValue;

            if (array_key_exists($key, $this->config))
                $value = $this->config[$key];

            return $value;
        }




        ################################################
        ##
        ##      Helper functions
        ##

        /**
         * Log level
         *
         * @param $message
         * @param string $level
         */
        private function log($message, $level = 'debug')
        {
           //echo '--->' . $message . "\r\n";
        }
    }
}