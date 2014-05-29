<?php

namespace AmazonWishlistExporter\Command;

use AmazonWishlistExporter\Logger\LoggerInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DomCrawler\Crawler;

class ExportCommand implements CommandInterface
{
    /**
     * @var string
     */
    private $countryCode;

    /**
     * @var string
     */
    private $whishlistId;

    /**
     * @var \GuzzleHttp\ClientInterface
     */
    private $client;

    /**
     * @var \AmazonWishlistExporter\Logger\LoggerInterface
     */
    private $logger;

    public function __construct(
        $countryCode,
        $wishlistId,
        ClientInterface $client,
        LoggerInterface $logger
    ) {
        $this->countryCode = $countryCode;
        $this->whishlistId = $wishlistId;
        $this->client = $client;
        $this->logger = $logger;
    }

    public function execute()
    {
        $page = 1;
        $lastItemsContent = null;
        $rows = [];
        $baseUrl = $this->getBaseUrlForCountry($this->countryCode);
        $wishlistBaseUrl = "{$baseUrl}/registry/wishlist/{$this->whishlistId}?layout=standard";
        
        if (!$baseUrl) {
            throw new \InvalidArgumentException("Country code {$this->countryCode} is not supported.");
        }

        $this->logger->log("Exporting: {$wishlistBaseUrl}");

        while (true) {
            $url = "{$wishlistBaseUrl}&page={$page}";
            $response = $this->client->get($url);
            $responseContent = (string) $response->getBody();
            $crawler = new Crawler($responseContent);
            $items = $crawler->filter('[id^=item_]');

            if ($response->getStatusCode() != 200 || !$items->count()) {
                $this->logger->log('Empty content (are you sure that you set your list as public?)');
                break;
            }

            if ($items->text() == $lastItemsContent) {
                $this->logger->log('Current content is repeating last content');
                break;
            }

            $items->each(function (Crawler $item) use (&$rows, $baseUrl) {
                $name = trim($item->filter('[id^=itemName_]')->text());
                $price = (float) str_replace('$', '', trim($item->filter('[id^=itemPrice_]')->text()));

                $url = 
                    $item->filter('[id^=itemName_]')->attr('href') ?
                        $baseUrl . $item->filter('[id^=itemName_]')->attr('href') : 
                        $item->filter('[id^=itemInfo_] .a-link-normal')->attr('href');

                $image = trim($item->filter('[id^=itemImage_] img')->attr('src'));
                $rows[] = array($name, $price, $url, $image);
            });

            $this->logger->log("Parsed page {$page} - Url: {$url}");

            $lastItemsContent = $items->text();
            ++$page;
        }

        $this->logger->log("Finished");

        return $rows;
    }

    /**
     * @param $countryCode
     * @return string|null
     */
    private function getBaseUrlForCountry($countryCode)
    {
        $baseUrlsByCountry = [
            'US' => 'http://www.amazon.com',
            'UK' => 'http://www.amazon.co.uk',
        ];

        $baseUrl = !empty($baseUrlsByCountry[$countryCode]) ? $baseUrlsByCountry[$countryCode] : null;

        return $baseUrl;
    }
}