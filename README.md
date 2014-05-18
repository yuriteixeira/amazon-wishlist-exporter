# Amazon Whishlist Exporter

Simple script that parses a public amazon whishlist and export the items to a CSV file.

## Requirements 

- PHP 5.4

## Installing

- Clone this repository
- Go to the cloned repo directory
- Download composer: `php -r "readfile('https://getcomposer.org/installer');" | php`
- Run `php composer.phar install`

## Running

- Just type inside the cloned repo directory: `php run.php <your_amazon_whishlist_id>` (you can get the id visiting your wishlist, it's part of the URL).


## Next steps

- Make this project available on packagist (easy install, always available on any directory)
- Export to Google Docs (with images of the products, options to convert the price into any other currency)
- Access private lists