<?php

namespace AmazonWishlistExporter\Test\Logger;

use AmazonWishlistExporter\Logger\StandardOutputLogger;

class StandardOutputLoggerTest extends \PHPUnit_Framework_TestCase
{
    public function testLog()
    {
        $messageFixture = 'Some test';
        $this->expectOutputString("$messageFixture\n");
        $logger = new StandardOutputLogger();
        $logger->log($messageFixture);
    }
}