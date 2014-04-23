<?php
namespace hathoora\kabootar\lib
{
    class smtpRun
    {
        public static function run()
        {
            $loop = \React\EventLoop\Factory::create();
            $socket = new \React\Socket\Server($loop);

            $arrConfig = array(
                'port' => 25,
                'listenIP' => '0.0.0.0',
                'hostname' => 'hathoora.org');

            $smtp = new \hathoora\kabootar\lib\smtp\server($socket, $arrConfig);

            $smtp->on('connection', function($conn)
            {
            });

            $smtp->on('MAIL', function($email)
            {
                if ($email->isValidCommand())
                {
                    //$sender->isValid();
                }
            });

            $smtp->on('RCPT', function($email)
            {
                if ($email->isValidCommand())
                {
                    //$recipient->isValid();
                }
            });


            $loop->run();
        }
    }
}