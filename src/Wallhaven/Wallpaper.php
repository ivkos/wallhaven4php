<?php

namespace Wallhaven;

use DateTime;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use PHPHtmlParser\Dom;
use Wallhaven\Exceptions\DownloadException;
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
            $response = $this->client->get(Wallhaven::URL_WALLPAPER . '/' . $this->id)->getBody()->getContents();
        } catch (RequestException $e) {
            $code = $e->getCode();
            if ($code == 403) {
                throw new LoginException("Access to wallpaper is forbidden.");
            } else if ($code == 404) {
                throw new NotFoundException("Wallpaper not found.");
            } else {
                throw $e;
            }
        }

        $dom = new Dom();
        $dom->load($response);

        $this->dom = $this->cacheEnabled ? $dom : null;

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

        $purityClass
            = $dom->find('#wallpaper-purity-form')[0]->find('fieldset.framed')[0]->find('input[checked="checked"]')[0]
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
            $resolutionElement = $this->getDom()->find('h3.showcase-resolution');
            $this->resolution = str_replace(' ', '', $resolutionElement->text);
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

        $result = $dom->find('div[data-storage-id="showcase-info"]')[0]->find('dl')[0]->find('dt');

        foreach ($result as $e) {
            if ($e->text == $contents) {
                return $e->nextSibling();
            }
        }

        throw new ParseException("Sibling of element with content \"" . $contents . "\" not found.");
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
            $favsLink = $this->getSibling("Favorites")->find('a');

            if (!$favsLink[0]) {
                $this->favorites = 0;
            } else {
                $this->favorites = (int)$favsLink[0]->text;
            }
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
            $usernameElement = $this->getDom()
                ->find("footer.sidebar-section")
                ->find(".username");

            if ($usernameElement != null) {
                $this->featuredBy = new User($usernameElement->text);
            }
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
            $featuredDateElement = $this->getDom()
                ->find("footer.sidebar-section")
                ->find("time");

            if ($featuredDateElement != null) {
                $this->featuredDate = new DateTime($featuredDateElement->getAttribute('datetime'));
            }
        }

        return $this->featuredDate;
    }

    /**
     * @return User User that uploaded the wallpaper.
     */
    public function getUploadedBy()
    {
        if (!$this->cacheEnabled || $this->uploadedBy === null) {
            $username = $this->getDom()
                ->find(".showcase-uploader")
                ->find("a.username")
                ->text;

            $this->uploadedBy = new User($username);
        }

        return $this->uploadedBy;
    }

    /**
     * @return DateTime Date and time when the wallpaper was uploaded.
     */
    public function getUploadedDate()
    {
        if (!$this->cacheEnabled || $this->uploadedDate === null) {
            $timeElement = $this->getDom()->find(".showcase-uploader > time:nth-child(4)")[0];

            $this->uploadedDate = new DateTime($timeElement->getAttribute('datetime'));
        }

        return $this->uploadedDate;
    }

    public function __toString()
    {
        return "Wallpaper " . $this->id;
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
     *
     * @throws DownloadException Thrown if the download directory cannot be created.
     */
    public function download($directory)
    {
        if (!file_exists($directory)) {
            if (!@mkdir($directory, null, true)) {
                throw new DownloadException("The download directory cannot be created.");
            }
        }

        $url = $this->getImageUrl();

        $this->client->get($url, [
            'save_to' => $directory . '/' . basename($url),
        ]);
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
            $url = $dom->find('img#wallpaper')[0]->getAttribute('src');
            $this->imageUrl = (parse_url($url, PHP_URL_SCHEME) ?: "https:") . $url;
        }

        return $this->imageUrl;
    }
}
