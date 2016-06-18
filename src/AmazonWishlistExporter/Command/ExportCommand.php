<?php

namespace AmazonWishlistExporter\Command;

use AmazonWishlistExporter\Crawler\AmazonCrawler;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

class ExportCommand implements CommandInterface
{
	/**
	 * @var string
	 */
	private $countryCode;

	/**
	 * @var string
	 */
	private $wishlistId;

	/**
	 * @var \GuzzleHttp\Client
	 */
	private $client;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var string
	 */
	private $pathToSave;

	/**
	 * ExportCommand constructor.
	 * @param $countryCode
	 * @param $wishlistId
	 * @param Client $client
	 * @param LoggerInterface $logger
	 * @param $pathToSave
	 */
	public function __construct(
		$countryCode,
		$wishlistId,
		Client $client,
		LoggerInterface $logger,
		$pathToSave
	)
	{
		$this->countryCode = $countryCode;
		$this->wishlistId = $wishlistId;
		$this->client = $client;
		$this->logger = $logger;
		$this->pathToSave = $pathToSave;

	}

	/**
	 * fetch wishlist and write CSV
	 */
	public function execute()
	{
		$amazonCrawler = new AmazonCrawler($this->client, $this->logger, $this->wishlistId, $this->countryCode);
		$items = $amazonCrawler->crawlItems();

		$items = array_merge([['Name', 'Price', 'Url', 'Image']], $items);

		// Saving each item row to the file
		$fh = fopen($this->pathToSave, 'w');
		foreach ($items as $item) {
			fputcsv($fh, $item);
		}

		fclose($fh);
	}


}