<?php

namespace Wallhaven;

use DateTime;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use PHPHtmlParser\Dom;
use Wallhaven\Exceptions\LoginException;
use Wallhaven\Exceptions\NotFoundException;
use Wallhaven\Exceptions\ParseException;

/**
 * Wallpaper
 *
 * @package Wallhaven
 */
class Wallpaper
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var bool Cache enabled.
     */
    private $cacheEnabled;

    /**
     * @var Dom Cached DOM.
     */
    private $dom;

    /**
     * @var int Wallpaper ID.
     */
    private $id;

    private $tags;
    private $purity;
    private $resolution;
    private $size;
    private $category;
    private $views;
    private $favorites;
    private $featuredBy;
    private $featuredDate;
    private $uploadedBy;
    private $uploadedDate;

    private $imageUrl;


    /**
     * @param int    $id     Wallpaper's ID.
     * @param Client $client HTTP Client.
     */
    public function __construct($id, Client $client)
    {
        $this->id = $id;
        $this->client = $client;
        $this->cacheEnabled = true;
    }

    /**
     * @return int Wallpaper ID.
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param array $properties Properties.
     */
    public function setProperties(array $properties)
    {
        foreach ($properties as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Enable or disable caching of wallpaper information. It's recommended to leave this enabled (default) unless
     * you really need real-time information. If you disable caching, performance will be severely degraded.
     *
     * @param bool $enabled Whether caching should be enabled.
     */
    public function setCacheEnabled($enabled)
    {
        $this->cacheEnabled = $enabled;
    }

    /**
     * Get wallpaper tags.
     *
     * @return string[] Tags.
     */
    public function getTags()
    {
        if ($this->cacheEnabled && $this->tags !== null) {
            return $this->tags;
        }

        $dom = $this->getDom();

        $this->tags = [];

        foreach ($dom->find('a.tagname') as $e) {
            $this->tags[] = $e->text;
        }

        return $this->tags;
    }

    /**
     * @return Dom
     * @throws LoginException  Thrown if access to the wallpaper was denied.
     * @throws NotFoundException Thrown if the wallpaper was not found.
     */
    private function getDom()
    {
        if ($this->cacheEnabled && $this->dom !== null) {
            return $this->dom;
        }

        try {
            $response = $this->client->get(Wallhaven::URL_HOME . Wallhaven::URL_WALLPAPER . '/' . $this->id)->getBody()
                ->getContents();
        } catch (RequestException $e) {
            $code = $e->getResponse()->getStatusCode();
            if ($code == 403) {
                throw new LoginException("Access to wallpaper is forbidden.");
            } else {
                if ($code == 404) {
                    throw new NotFoundException("Wallpaper not found.");
                } else {
                    throw $e;
                }
            }
        }

        $dom = new Dom();
        $dom->load($response);

        if ($this->cacheEnabled) {
            $this->dom = $dom;
        }

        return $dom;
    }

    /**
     * @return int Purity.
     */
    public function getPurity()
    {
        if ($this->cacheEnabled && $this->purity !== null) {
            return $this->purity;
        }

        $dom = $this->getDom();

        $purityClass = $dom->find('#wallpaper-purity-form')->find('fieldset.framed')->find('input[checked="checked"]')
            ->nextSibling()->getAttribute('class');

        $purityText = preg_split("/purity /", $purityClass)[1];

        $this->purity = constant('Wallhaven\Purity::' . strtoupper($purityText));

        return $this->purity;
    }

    /**
     * @return string Resolution.
     */
    public function getResolution()
    {
        if (!$this->cacheEnabled || $this->resolution === null) {
            $this->resolution = str_replace(' ', '', $this->getSibling("Resolution")->text);
        }

        return $this->resolution;
    }

    /**
     * @param string $contents
     *
     * @return \PHPHtmlParser\Dom\AbstractNode
     * @throws ParseException
     */
    private function getSibling($contents)
    {
        $dom = $this->getDom();

        $result = $dom->find('div[data-storage-id="showcase-info"]')->find('dl')->find('dt');

        foreach ($result as $e) {
            if ($e->text == $contents) {
                return $e->nextSibling();
            }
        }

        throw new ParseException("Element's sibling not found.");
    }

    /**
     * @return string Size of the image.
     */
    public function getSize()
    {
        if (!$this->cacheEnabled || $this->size === null) {
            $this->size = $this->getSibling("Size")->text;
        }

        return $this->size;
    }

    /**
     * @return int Category.
     */
    public function getCategory()
    {
        if (!$this->cacheEnabled || $this->category === null) {
            $this->category = constant('Wallhaven\Category::' . strtoupper($this->getSibling("Category")->text));
        }

        return $this->category;
    }

    /**
     * @return int Number of views.
     */
    public function getViews()
    {
        if (!$this->cacheEnabled || $this->views === null) {
            $this->views = (int)str_replace(',', '', $this->getSibling("Views")->text);
        }

        return $this->views;
    }

    /**
     * @return int Number of favorites.
     */
    public function getFavorites()
    {
        if (!$this->cacheEnabled || $this->favorites === null) {
            $this->favorites = (int)$this->getSibling("Favorites")->find('a')->text;
        }

        return $this->favorites;
    }

    /**
     * @return User User that featured the wallpaper.
     * @throws ParseException
     */
    public function getFeaturedBy()
    {
        if (!$this->cacheEnabled || $this->featuredBy === null) {
            $this->featuredBy = new User($this->getSibling("Featured by")->find('a')->text);
        }

        return $this->featuredBy;
    }

    /**
     * @return DateTime Date and time when the wallpaper was featured.
     * @throws ParseException
     */
    public function getFeaturedDate()
    {
        if (!$this->cacheEnabled || $this->featuredDate === null) {
            $this->featuredDate = new DateTime($this->getSibling("Featured date")->find('time')->getAttribute('datetime'));
        }

        return $this->featuredDate;
    }

    /**
     * @return User User that uploaded the wallpaper.
     */
    public function getUploadedBy()
    {
        if (!$this->cacheEnabled || $this->uploadedBy === null) {
            $this->uploadedBy = new User($this->getSibling("Uploaded by")->find('a')->text);
        }

        return $this->uploadedBy;
    }

    /**
     * @return DateTime Date and time when the wallpaper was uploaded.
     */
    public function getUploadedDate()
    {
        if (!$this->cacheEnabled || $this->uploadedDate === null) {
            $this->uploadedDate = new DateTime($this->getSibling("Added")->find('time')->getAttribute('datetime'));
        }

        return $this->uploadedDate;
    }

    public function __toString()
    {
        return "Wallpaper " . $this->id;
    }

    /**
     * @param bool $assumeJpg Assume the wallpaper is JPG. May speed up the method at the cost of potentially wrong URL.
     *
     * @return string URL.
     * @throws LoginException
     * @throws NotFoundException
     */
    public function getImageUrl($assumeJpg = false)
    {
        if ($assumeJpg) {
            return Wallhaven::URL_IMG_PREFIX . $this->id . '.jpg';
        }

        if (!$this->cacheEnabled || $this->imageUrl === null) {
            $dom = $this->getDom();
            $url = $dom->find('img#wallpaper')->getAttribute('src');
            $this->imageUrl = (parse_url($url, PHP_URL_SCHEME) ?: "http:") . $url;
        }

        return $this->imageUrl;
    }

    /**
     * @return string Thumbnail URL.
     */
    public function getThumbnailUrl()
    {
        return Wallhaven::URL_HOME . Wallhaven::URL_THUMB_PREFIX . $this->id . '.jpg';
    }

    /**
     * Download the wallpaper.
     *
     * @param string $directory Where to download the wallpaper.
     */
    public function download($directory)
    {
        $url = $this->getImageUrl();

        $this->client->get($url, [
            'save_to' => $directory . '/' . basename($url),
        ]);
    }
}
