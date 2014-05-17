<?php

use Symfony\Component\DomCrawler\Crawler;

require __DIR__ . '/vendor/autoload.php';

if (empty($argv[1])) exit("Inform the ID of your Amazon Whishlist.\n");

echo "Exporting...\n";

$page = 1;
$client = new \GuzzleHttp\Client();
$lastItemsContent = null;

unlink('amazon_whishlist.csv');

while (true) {
    $response = $client->get("http://www.amazon.com/registry/wishlist/{$argv[1]}?layout=standard&page={$page}");
    $crawler = new Crawler((string) $response->getBody());
    $items = $crawler->filter('[id^=item_]');

    if ($response->getStatusCode() != 200 || $items->text() == $lastItemsContent) {
        break;
    }

    $items->each(function (Crawler $item) {
        $name = trim($item->filter('[id^=itemName_]')->text());
        $price = (float) str_replace('$', '', trim($item->filter('[id^=itemPrice_]')->text()));
        $image = trim($item->filter('[id^=itemImage_] img')->attr('src'));
        $url =
            'http://amazon.com'
            . $item->filter('[id^=itemName_]')->attr('href') ?: $item->filter('[id^=itemInfo_] .a-link-normal')->attr('href');

        $fh = fopen('amazon_whishlist.csv', 'a');
        $row = compact('name', 'price', 'url', 'image');
        fputcsv($fh, $row);
        fclose($fh);
    });

    echo "Parsed page {$page}\n";

    $lastItemsContent = $items->text();
    ++$page;
}

echo "Finished.\n";