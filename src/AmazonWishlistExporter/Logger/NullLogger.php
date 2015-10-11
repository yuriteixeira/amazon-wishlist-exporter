<?php

namespace AmazonWishlistExporter\Logger;

class NullLogger implements LoggerInterface
{
    public function log($message)
    {

    }
}