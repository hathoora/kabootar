# Kabootar PHP SMTP Framework

Based on async I/O (Ratchet)

## Installation

To install place this code inside app/hathoora/kabootar

## EventEmitter Events

* `MAIL`: Emitted whenever client sends a valid "MAIL FROM" command
* `RCPT`: Emitted whenever client sends a valid "RCPT TO" command
* `DATA`: Emitted whenever client sends a valid "DATA" command
* `DATA-INCOMING`: Emitted whenever client sends data
* `DATA-END`: Emitted whenever client sends .CRLF
* `QUIT`: Emitted whenever client sends "QUIT" command

## Usage

    $loop = \React\EventLoop\Factory::create();
    $socket = new \React\Socket\Server($loop);

    $arrConfig = array(
        'port' => 25,
        'listenIP' => '0.0.0.0',
        'hostname' => 'hathoora.org',
        'maxMailSize' => 10000); // in bytes

    $smtp = new \hathoora\kabootar\lib\smtp\server($socket, $arrConfig);

    $smtp->on('MAIL', function($email)
    {
        $deferred = new Deferred();
        $email->answer($deferred->promise());

        // ascyn SPF records check..
        $x = function($email) use($deferred)
        {
            // store SPF info in header
            $email->email->storeRawHeader('Received-SPF:.....');

            $spfRecord = someAsyncMethod();
            if ($spfRecord == 'pass')
            {
                $deferred->resolve(); // let mail server handle success message

                # or send a custom message
                # $deferred->resolve(array(array(200, 'SPF recond is clean')));
            }
            else
                $deferred->reject(array(array(354, 'error')));
        };
        $x($email);
    });

    $smtp->on('DATA-END', function($email)
    {
        $raw = $email->getEmail()->getRaw();
    });

    $loop->run();