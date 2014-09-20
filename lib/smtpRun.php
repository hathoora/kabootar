<?php
namespace hathoora\kabootar\lib
{
    class smtpRun
    {
        public static function run()
        {
            $loop = \React\EventLoop\Factory::create();

            $arrConfig = array(
                'port' => 25,
                'listenIP' => '0.0.0.0',
                'hostname' => 'hathoora.org');

            $smtp = new \hathoora\kabootar\lib\smtp\server($loop, $arrConfig);

            $smtp->on('connection', function($conn)
            {
            });

            $smtp->on('MAIL', function($email)
            {

            });

            $smtp->on('RCPT', function($email)
            {
            });

            $smtp->on('close', function($email)
            {

            });

            $loop->run();
        }
    }
}