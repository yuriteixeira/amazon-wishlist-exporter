<?php

namespace AmazonWishlistExporter\Logger;

class StandardOutputLogger implements LoggerInterface
{
	public function log($message)
	{
		echo "{$message}\n";
	}
}