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
     * @var array
     */
    private $configuration = array(
        'US' => array(
            'url' =>'http://www.amazon.com',
            'delimiter' => '.',
            'currencySign' => '$',
        ),
        'DE' => array(
            'url' =>'http://www.amazon.de',
            'delimiter' => ',',
            'currencySign' => 'EUR',
        ),
        'UK' => array(
            'url' =>'http://www.amazon.co.uk',
            'delimiter' => ',',
            'currencySign' => '£',
        ),
    );


    /**
     * @return array
     */
    private function getCurrencyUnits()
    {
        $units = array();
        foreach($this->configuration as $element) {
            $units[] = $element ['currencySign'];
        }
        return $units;
    }

    /**
     * @var \AmazonWishlistExporter\Logger\LoggerInterface
     */
    private $logger;

    /**
     * @var \GuzzleHttp\ClientInterface
     */
    private $client;

    /**
     * @param ClientInterface $client
     * @param LoggerInterface $logger
     */
    public function __construct(ClientInterface $client, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->client = $client;

    }

    /**
     * @return array
     */
    public function getConfiguration($countryCode, $value)
    {
        $countryCode = strtoupper($countryCode);
        if (false === array_key_exists($countryCode, $this->configuration)) {
            throw new \InvalidArgumentException(sprintf('Country Code %s not found!', $countryCode));
        }
        if (false === array_key_exists($value, $this->configuration[$countryCode])) {
            throw new \InvalidArgumentException(sprintf('Value Code %s not found!', $value));
        }
        return $this->configuration[$countryCode][$value];
    }
    /**
     * @param $countryCode
     * @return string|null
     */
    private function getBaseUrlForCountry($countryCode)
    {
        return $this->getConfiguration($countryCode, 'url');
    }

    /**
     * @param $countryCode
     * @return mixed
     * @throws \Exception
     */
    private function getPriceDelimiterByCountryCode($countryCode)
    {
        return $this->getConfiguration($countryCode,'delimiter');
    }

    /**
     * @param string $priceString
     * @param string $countryCode
     * @return float
     */
    private function parsePrice($priceString, $countryCode)
    {
        $priceString = str_replace($this->getCurrencyUnits(), '', trim($priceString));
        $priceString = trim(str_replace($this->getPriceDelimiterByCountryCode($countryCode), '.', $priceString));
        return (float)$priceString;
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

            $items->each(function (Crawler $item) use (&$rows, $baseUrl, $countryCode) {
                $name = trim($item->filter('[id^=itemName_]')->text());

                $price = $this->parsePrice($item->filter('[id^=itemPrice_]')->text(), $countryCode);
                //$price = str_replace($this->getCurrencyUnits(), '', trim($item->filter('[id^=itemPrice_]')->text()));
                $url =
                    $item->filter('[id^=itemName_]')->attr('href') ?
                        $baseUrl . $item->filter('[id^=itemName_]')->attr('href') :
                        $item->filter('[id^=itemInfo_] .a-link-normal')->attr('href');

                $image = trim($item->filter('[id^=itemImage_] img')->attr('src'));
                $rows[] = array(
                    'name' => $name,
                    'price' => $price,
                    'url' => $url,
                    'image' => $image
                );
            });

            $this->logger->log("Parsed page {$page} - Url: {$url}");

            $lastItemsContent = $items->text();
            ++$page;
        }

        $this->logger->log("Finished");

        return $rows;
    }
}