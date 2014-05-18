<?php

require __DIR__ . '/vendor/autoload.php';

if (empty($argv[1])) die("Inform the ID of your Amazon Whishlist.\n");
if (empty($argv[2])) $argv[2] = 'US';
if (empty($argv[3])) $argv[3] = 'amazon_whishlist_' . date('Ymd_His') . '.csv';

$logger = new \AmazonWishlistExporter\Logger\StandardOutputLoggerTest();
$client = new \GuzzleHttp\Client();
$wishlistId = $argv[1];
$countryCode = $argv[2];
$pathToSave = $argv[3];

$command = new \AmazonWishlistExporter\Command\CsvExportCommandTest($countryCode, $wishlistId, $client, $logger);
$items = $command->execute();

$fh = fopen($pathToSave, 'w');
fputcsv($fh, $items);
fclose($fh);