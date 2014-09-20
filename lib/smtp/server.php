<?php
namespace hathoora\kabootar\lib\smtp
{
    use Evenement\EventEmitter,
        React\EventLoop\LoopInterface,
        React\Socket\Server as SocketServer;

    /** @event request */
    class server extends EventEmitter implements serverInterface
    {
        /**
         * @var \React\EventLoop\LoopInterface
         */
        private $loop;

        /**
         * @var \React\Socket\ServerInterface
         */
        private $socket;
        private $config;
        private $version = 0.1;
        private $arrEmailConnections = array();

        public function __construct(LoopInterface $loop, array $config = array())
        {
            $this->loop = $loop;
            $this->socket = new SocketServer($this->loop);

            // default configs
            if (!isset($config['port']))
                $config['port'] = 25;
            if (!isset($config['listenIP']))
                $config['listenIP'] = 'localhost';
            if (!isset($config['hostname']))
                $config['hostname'] = 'Kabootar Mail';
            if (!isset($config['maxMailSize']))
                $config['maxMailSize'] = 35882577;
            if (!isset($config['maxIdleTime']))
                $config['maxIdleTime'] = 20;
            if (!isset($config['maxClientErrors']))
                $config['maxClientErrors'] = 15;

            $this->config = $config;
            $this->socket->listen(
                                    $this->getConfig('port'),
                                    $this->getConfig('listenIP'));

            $this->socket->on('connection', function($conn)
            {
                $emailConnection = new emailConnection($conn, $this->config);
                $sessionid = $emailConnection->getSessionId();

                // @var \React\EventLoop\Timer\TimerInterface
                $emailConnectionTimeoutTimer =  $this->loop->addPeriodicTimer(10, function() use($emailConnection)
                {
                    $time = time();
                    $idle = (time() - $emailConnection->timeLastActivityAt);
                    echo "Connection timeout check for ". $emailConnection->getSessionId() .": idle for $idle secs \n";
                    if ($idle > $this->getConfig('maxIdleTime'))
                        $emailConnection->closeTimeout();
                });

                $this->arrEmailConnections[$sessionid] = array(
                                                                'emailConnection' => $emailConnection,
                                                                'timeoutTimer' => $emailConnectionTimeoutTimer);

                $emailConnection->on('stream', function($emailConnection)
                {
                    $this->emit($emailConnection->getSessionState(), array($emailConnection));
                });

                $conn->on('data', function($data) use($emailConnection)
                {
                    $emailConnection->feed($data);
                });

                // we need to add some wait before killing the connection to flush out all the messages to client
                // so "close-delay" was emitted
                $emailConnection->on('close-delay', function($conn)
                {
                    $this->loop->addTimer(rand(1,2), function() use($conn)
                    {
                       $conn->close();
                    });
                });

                $conn->on('close', function($conn) use ($emailConnection)
                {
                    echo '-------------------' . "\n" . $emailConnection->getEmail()->getRaw() . "\n" . '-------------------';
                    $emailConnection->close();
                    $sessionid = $emailConnection->getSessionId();
                    if (isset($this->arrEmailConnections[$sessionid]))
                    {
                        // clear timeout connection timer
                        if (isset($this->arrEmailConnections[$sessionid]['timeoutTimer']))
                            $this->loop->cancelTimer($this->arrEmailConnections[$sessionid]['timeoutTimer']);

                        unset($this->arrEmailConnections[$sessionid]);
                    }

                    $this->emit('close', array($emailConnection));
                });
            });

            $this->loop->addPeriodicTimer(50, function ()
            {
                $memory = memory_get_usage() / 1024;
                $formatted = number_format($memory, 3).'K';
                echo ' -> ' . "Current memory usage: {$formatted}\n";

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