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
			'url' => 'http://www.amazon.com',
			'delimiter' => '.',
			'currencySign' => '$',
		),
		'DE' => array(
			'url' => 'http://www.amazon.de',
			'delimiter' => ',',
			'currencySign' => 'EUR',
		),
		'UK' => array(
			'url' => 'http://www.amazon.co.uk',
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
		foreach ($this->configuration as $element) {
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
	 * @var integer
	 */
	private $wishlistId;

	/**
	 * @var string
	 */
	private $countryCode;

	/**
	 * @param ClientInterface $client
	 * @param LoggerInterface $logger
	 * @param $wishlistId
	 */
	public function __construct(ClientInterface $client, LoggerInterface $logger, $wishlistId, $countryCode)
	{
		$this->client = $client;
		$this->logger = $logger;
		$this->wishlistId = $wishlistId;
		$this->countryCode = $countryCode;

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
		$baseUrl = $this->getConfiguration($countryCode, 'url');

		if (!$baseUrl) {
			throw new \InvalidArgumentException("Country code {$this->countryCode} is not supported.");
		}
		return $baseUrl;
	}

	/**
	 * @param $countryCode
	 * @return mixed
	 * @throws \Exception
	 */
	private function getPriceDelimiterByCountryCode($countryCode)
	{
		return $this->getConfiguration($countryCode, 'delimiter');
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

	/**
	 * @param $wishlistBaseUrl
	 * @return string
	 */
	public function crawlTitle()
	{
		$response = $this->client->get($this->getWishlistBaseUrl());
		$responseContent = (string)$response->getBody();
		$crawler = new Crawler($responseContent);
		return trim($crawler->filter('span.a-size-extra-large')->text());
	}

	/**
	 * @return string
	 */
	public function crawlOwnerName()
	{
		$response = $this->client->get($this->getWishlistBaseUrl());
		$responseContent = (string)$response->getBody();
		$crawler = new Crawler($responseContent);
		return trim($crawler->filter('span.g-profile-name')->text());
	}

	/**
	 * @return string
	 */
	protected function getWishlistBaseUrl()
	{
		return "{$this->getBaseUrlForCountry($this->countryCode)}/registry/wishlist/{$this->wishlistId}?layout=standard";
	}

	/**
	 * @return array
	 */
	public function crawlItems()
	{
		$page = 1;
		$lastItemsContent = null;
		$rows = [];
		$baseUrl = $this->getBaseUrlForCountry($this->countryCode);
		$wishlistBaseUrl = $this->getWishlistBaseUrl();


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
				$price = $this->parsePrice($item->filter('[id^=itemPrice_]')->text(), $this->countryCode);
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