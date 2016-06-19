<?php
namespace AmazonWishlistExporter\Crawler;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\DomCrawler\Crawler;


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
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var \GuzzleHttp\Client
	 */
	private $client;

	/**
	 * @var integer
	 */
	private $wishlistId = null;

	/**
	 * @var string
	 */
	private $countryCode = null;

	/**
	 * AmazonCrawler constructor.
	 * @param Client $client
	 * @param LoggerInterface $logger
	 * @param $wishlistId
	 * @param $countryCode
	 */
	public function __construct(Client $client, LoggerInterface $logger)
	{
		$this->client = $client;
		$this->logger = $logger;


	}

	/**
	 * @param $countryCode
	 * @param $value
	 * @return mixed
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
		return "{$this->getBaseUrlForCountry($this->getCountryCode())}/registry/wishlist/{$this->getWishlistId()}?layout=standard";
	}

	/**
	 * @return array
	 */
	public function crawlItems()
	{
		$page = 1;
		$lastItemsContent = null;
		$rows = [];
		$baseUrl = $this->getBaseUrlForCountry($this->getCountryCode());
		$wishlistBaseUrl = $this->getWishlistBaseUrl();


		$this->logger->info("Exporting: {$wishlistBaseUrl}");
		while (true) {
			$url = "{$wishlistBaseUrl}&page={$page}";
			$response = $this->client->get($url);
			$responseContent = (string)$response->getBody();
			$crawler = new Crawler($responseContent);
			$items = $crawler->filter('[id^=item_]');


			if ($response->getStatusCode() != 200 || !$items->count()) {
				$this->logger->info('Empty content (are you sure that you set your list as public?)');
				break;
			}

			if ($items->text() == $lastItemsContent) {
				$this->logger->info('Current content is repeating last content');
				break;
			}

			$items->each(function (Crawler $item) use (&$rows, $baseUrl) {
				$name = trim($item->filter('[id^=itemName_]')->text());
				$price = $this->parsePrice($item->filter('[id^=itemPrice_]')->text(), $this->getCountryCode());
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

			$this->logger->info("Parsed page {$page} - Url: {$url}");

			$lastItemsContent = $items->text();
			++$page;
		}

		$this->logger->info("Finished");

		return $rows;
	}

	/**
	 * @return int
	 */
	public function getWishlistId()
	{
		if(null === $this->wishlistId) {
			throw new \Exception('WishlistId not set');
		}
		return $this->wishlistId;
	}

	/**
	 * @param int $wishlistId
	 */
	public function setWishlistId($wishlistId)
	{
		$this->wishlistId = $wishlistId;
	}

	/**
	 * @return string
	 */
	public function getCountryCode()
	{
		if(null === $this->countryCode) {
			throw new \Exception('Country Code not set');
		}
		return $this->countryCode;
	}

	/**
	 * @param string $countryCode
	 */
	public function setCountryCode($countryCode)
	{
		$this->countryCode = $countryCode;
	}


}