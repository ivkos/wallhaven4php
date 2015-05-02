<?php

namespace Wallhaven;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use Wallhaven\Exceptions\WallhavenException;

/**
 * Wallpaper list.
 *
 * @package Wallhaven
 */
class WallpaperList implements \ArrayAccess, \IteratorAggregate, \Countable
{

    /**
     * @var Wallpaper[] Wallpapers.
     */
    private $wallpapers = [];

    /**
     * Download all wallpapers in list.
     *
     * @param string $directory Where to download wallpapers.
     */
    public function downloadAll($directory)
    {
        $client = new Client();

        $requests = [];
        foreach ($this->wallpapers as $w) {
            $url = $w->getImageUrl();

            $requests[] = $client->createRequest('GET', $url, [
                'save_to' => $directory . '/' . basename($url)
            ]);
        }

        $results = Pool::batch($client, $requests);

        // Retry with PNG
        $retryRequests = [];
        foreach ($results->getFailures() as $e) {
            // Delete failed files
            unlink($directory . '/' . basename($e->getRequest()->getUrl()));

            $urlPng = str_replace('.jpg', '.png', $e->getRequest()->getUrl());
            $statusCode = $e->getResponse()->getStatusCode();

            if ($statusCode == 404) {
                $retryRequests[] = $client->createRequest('GET', $urlPng, [
                    'save_to' => $directory . '/' . basename($urlPng)
                ]);
            }
        }

        Pool::batch($client, $retryRequests);
    }


    /**
     *
     * @param $offset
     *
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->wallpapers[$offset]);
    }

    /**
     *
     * @param $offset
     *
     * @return null|Wallpaper
     */
    public function offsetGet($offset)
    {
        return isset($this->wallpapers[$offset]) ? $this->wallpapers[$offset] : null;
    }

    /**
     *
     * @param           $offset
     * @param Wallpaper $value
     *
     * @throws WallhavenException
     */
    public function offsetSet($offset, $value)
    {
        if (!$value instanceof Wallpaper) {
            throw new WallhavenException("Not a Wallpaper object.");
        }

        if (is_null($offset)) {
            $this->wallpapers[] = $value;
        } else {
            $this->wallpapers[$offset] = $value;
        }
    }

    /**
     * @param $offset
     */
    public function offsetUnset($offset)
    {
        unset($this->wallpapers[$offset]);
    }

    /**
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->wallpapers);
    }


    /**
     * @return int Wallpaper count.
     */
    public function count()
    {
        return count($this->wallpapers);
    }
}
