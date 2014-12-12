Wallhaven
=========

[![Build Status](https://travis-ci.org/ivkos/Wallhaven.svg)](https://travis-ci.org/ivkos/Wallhaven)

## Description
Using this PHP class, you can search with any or all of the following criteria and list matching [Wallhaven](http://wallhaven.cc) wallpapers:
* tags
* categories
* purity
* sorting by relevance, date added, views, favorites, or random
* order of results
* resolution
* ratio

The class also allows you to optionally login with your Wallhaven username and password, so that it list all available wallpapers, otherwise inaccessible if not logged in.

## Requirements
* PHP 5.4
* cURL library for PHP

## Examples
For more detailed documentation, consult the PHPDoc of the methods.

``` php
<?php

require 'Wallhaven.php';

try {
  // Initialize
  $wh = new Wallhaven();
  
  // You can also login using your credentials
  $whLogin = new Wallhaven("YOUR_USERNAME", "YOUR_PASSWORD");
  
  // Get information for a specific wallpaper
  $wallpaperInfo = $wh->getWallpaperInformation(109965);
  
  // Get a list of random wallpapers
  $wallpapersRandom = $wh->getRandom();
  
  // Get a list of top wallpapers (most favorites)
  $wallpapersTop = $wh->getTop();
  
  // Search for specific wallpapers
  $cars = $wh->search(
      "#cars",
      WH_CATEGORY_GENERAL | WH_CATEGORY_PEOPLE,
      WH_PURITY_SAFE | WH_PURITY_SKETCHY,
      "relevance",
      "desc",
      array("1920x1080", "1280x720"),
      array("16x9")
  );
  
  $macro = $wh->search(
      "macro",
      WH_CATEGORY_GENERAL,
      WH_PURITY_SAFE,
      "random",
      "desc",
      array("1920x1080")
  );
} catch (Exception $e) {
  die("Caught exception: " . $e->getMessage());
}

print_r($macro);
```

The code above will output something similar to this:

```
Array
(
    [0] => Array
        (
            [id] => 48903
            [url] => http://alpha.wallhaven.cc/wallpaper/48903
            [imgUrl] => http://alpha.wallhaven.cc/wallpapers/full/wallhaven-48903.jpg
            [mimeType] => image/jpeg
        )

    [1] => Array
        (
            [id] => 4063
            [url] => http://alpha.wallhaven.cc/wallpaper/4063
            [imgUrl] => http://alpha.wallhaven.cc/wallpapers/full/wallhaven-4063.jpg
            [mimeType] => image/jpeg
        )
)
```
