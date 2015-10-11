<?php

namespace AmazonWishlistExporter\Command;

use AmazonWishlistExporter\Logger\LoggerInterface;
use AmazonWishlistExporter\Crawler\AmazonCrawler;
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

    /**
     * @var string
     */
    private $pathToSave;

    public function __construct(
        $countryCode,
        $wishlistId,
        ClientInterface $client,
        LoggerInterface $logger,
        $pathToSave
    )
    {
        $this->countryCode = $countryCode;
        $this->whishlistId = $wishlistId;
        $this->client = $client;
        $this->logger = $logger;
        $this->pathToSave = $pathToSave;

    }

    /**
     * fetch wishlist and write CSV
     */
    public function execute()
    {
        $amazonCrawler = new AmazonCrawler($this->logger, $this->client);
        $items = $amazonCrawler->crawl($this->whishlistId, $this->countryCode);

        $items = array_merge([['Name', 'Price', 'Url','Image']], $items);

        // Saving each item row to the file
        $fh = fopen($this->pathToSave, 'w');
        foreach ($items as $item) {
            fputcsv($fh, $item);
        }

        fclose($fh);
    }


}