<?php
namespace AmazonWishlistExporter\Crawler;

use AmazonWishlistExporter\Logger\LoggerInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * User: ms
 * Date: 11.10.15
 * Time: 19:31
 */
class AmazonCrawler
{

    /**
     * @var \AmazonWishlistExporter\Logger\LoggerInterface
     */
    private $logger;

    /**
     * @var \GuzzleHttp\ClientInterface
     */
    private $client;


    public function __construct(ClientInterface $client, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->client = $client;

    }

    /**
     * @param $countryCode
     * @return string|null
     */
    private function getBaseUrlForCountry($countryCode)
    {
        $countryCode = strtoupper($countryCode);
        $baseUrlsByCountry = [
            'US' => 'http://www.amazon.com',
            'DE' => 'http://www.amazon.de',
            'UK' => 'http://www.amazon.co.uk',
        ];

        $baseUrl = !empty($baseUrlsByCountry[$countryCode]) ? $baseUrlsByCountry[$countryCode] : null;

        return $baseUrl;
    }

    public function crawl($wishlistId, $countryCode)
    {
        $page = 1;
        $lastItemsContent = null;
        $rows = [];
        $baseUrl = $this->getBaseUrlForCountry($countryCode);
        $wishlistBaseUrl = "{$baseUrl}/registry/wishlist/{$wishlistId}?layout=standard";

        if (!$baseUrl) {
            throw new \InvalidArgumentException("Country code {$countryCode} is not supported.");
        }

        $this->logger->log("Exporting: {$wishlistBaseUrl}");

        while (true) {
            $url = "{$wishlistBaseUrl}&page={$page}";
            $response = $this->client->get($url);
            $responseContent = (string)$response->getBody();
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
                $price = (float)str_replace('$', '', trim($item->filter('[id^=itemPrice_]')->text()));

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
}