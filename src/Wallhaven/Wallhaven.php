<?php

namespace Wallhaven;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\TransferStats;
use PHPHtmlParser\Dom;
use Wallhaven\Exceptions\LoginException;
use Wallhaven\Exceptions\WallhavenException;

/**
 * Wallhaven
 *
 * @package Wallhaven
 */
class Wallhaven
{
    const URL_HOME         = "https://alpha.wallhaven.cc";
    const URL_WALLPAPER    = "/wallpaper";
    const URL_LOGIN        = "/auth/login";
    const URL_SEARCH       = "/search";
    const URL_THUMB_PREFIX = "/wallpapers/thumb/small/th-";
    const URL_IMG_PREFIX   = "https://wallpapers.wallhaven.cc/wallpapers/full/wallhaven-";

    /**
     * @var Client HTTP Client.
     */
    private $client;

    /**
     * @var string Username.
     */
    private $username;

    /**
     * Create an instance of Wallhaven. Login credentials are optional.
     *
     * @param string $username
     * @param string $password
     *
     * @throws LoginException
     */
    public function __construct($username = null, $password = null)
    {
        if (!empty($username)) {
            $this->login($username, $password);
        } else {
            $this->initClient();
        }
    }

    /**
     * Login to Wallhaven.
     *
     * @param string $username Username.
     * @param string $password Password.
     *
     * @throws LoginException
     */
    public function login($username, $password)
    {
        if (empty($username) || empty($password)) {
            throw new LoginException("Incorrect username or password.");
        }

        $this->initClient(true);

        $login = $this->client->post(self::URL_LOGIN, [
            'form_params' => [
                '_token'   => $this->getToken(),
                'username' => $username,
                'password' => $password
            ],
            'on_stats' => function (TransferStats $stats) use (&$url) {
                $url = $stats->getEffectiveUri();
            }
        ]);

        if ($url == self::URL_HOME . self::URL_LOGIN) {
            throw new LoginException("Incorrect username or password.");
        }

        $this->username = $username;
    }

    /**
     * Initialize HTTP client.
     *
     * @param bool $withCookies Whether cookies should be enabled.
     */
    private function initClient($withCookies = false)
    {

        if ($withCookies) {
            $jar = new CookieJar();
            $this->client = new Client(
                [
                    'base_uri' => self::URL_HOME,
                    'cookies'  => $jar
                ]);
        } else {
            $this->client = new Client(['base_uri' => self::URL_HOME]);
        }
    }

    /**
     * Get token for login.
     *
     * @return string Token.
     * @throws WallhavenException Thrown if no token is found.
     */
    private function getToken()
    {
        $body = $this->client->get('/')->getBody()->getContents();

        $dom = new Dom();
        $dom->load($body);

        $token = $dom->find('input[name="_token"]')[0]->value;

        if (empty($token)) {
            throw new LoginException("Cannot find login token on Wallhaven's homepage.");
        }

        return $token;
    }

    /**
     * User.
     *
     * @param string $username Username. If empty, returns the current user.
     *
     * @return User User.
     */
    public function user($username = null)
    {
        return new User($username ?: $this->username);
    }

    /**
     * Search for wallpapers.
     *
     * @param string   $query       What to search for. Searching for specific tags can be done with #tagname, e.g.
     *                              <samp>#cars</samp>
     * @param int      $categories  Categories to include. This is a bit field, e.g.: <samp>Category::GENERAL |
     *                              Category::PEOPLE</samp>
     * @param int      $purity      Purity of wallpapers. This is a bit field, e.g.: <samp>Purity::SFW |
     *                              Purity::NSFW</samp>
     * @param string   $sorting     Sorting, e.g. <samp>Sorting::RELEVANCE</samp>
     * @param string   $order       Order of results. Can be <samp>Order::ASC</samp> or <samp>Order::DESC</samp>
     * @param string[] $resolutions Array of resolutions in the format of WxH, e.g.: <samp>['1920x1080',
     *                              '1280x720']</samp>
     * @param string[] $ratios      Array of ratios in the format of WxH, e.g.: <samp>['16x9', '4x3']</samp>
     * @param int      $page        The id of the page to fetch. This is <em>not</em> a total number of pages to
     *                              fetch.
     *
     * @return WallpaperList Wallpapers.
     */
    public function search(
        $query,
        $categories = Category::ALL,
        $purity = Purity::SFW,
        $sorting = Sorting::RELEVANCE,
        $order = Order::DESC,
        $resolutions = [],
        $ratios = [],
        $page = 1
    ) {
        $result = $this->client->get(self::URL_SEARCH, [
            'query'   => [
                'q'           => $query,
                'categories'  => self::getBinary($categories),
                'purity'      => self::getBinary($purity),
                'sorting'     => $sorting,
                'order'       => $order,
                'resolutions' => implode(',', $resolutions),
                'ratios'      => implode(',', $ratios),
                'page'        => $page
            ],
            'headers' => [
                'X-Requested-With' => 'XMLHttpRequest'
            ]
        ]);

        $body = $result->getBody()->getContents();
        $dom = new Dom();
        $dom->load($body);

        $figures = $dom->find('figure.thumb');

        $wallpapers = new WallpaperList();

        foreach ($figures as $figure) {
            $id = preg_split('#' . self::URL_HOME . self::URL_WALLPAPER . '/#',
                $figure->find('a.preview')->getAttribute('href'))[1];

            $classText = $figure->getAttribute('class');
            preg_match("/thumb thumb-(sfw|sketchy|nsfw) thumb-(general|anime|people)/", $classText, $classMatches);

            $purity = constant('Wallhaven\Purity::' . strtoupper($classMatches[1]));
            $category = constant('Wallhaven\Category::' . strtoupper($classMatches[2]));
            $resolution = str_replace(' ', '', trim($figure->find('span.wall-res')->text));
            $favorites = (int)$figure->find('.wall-favs')->text;

            $w = new Wallpaper($id, $this->client);

            $w->setProperties([
                'purity'     => $purity,
                'category'   => $category,
                'resolution' => $resolution,
                'favorites'  => $favorites
            ]);

            $wallpapers[] = $w;
        }

        return $wallpapers;
    }

    /**
     * Convert a bit field into Wallhaven's format.
     *
     * @param int $bitField Bit field.
     *
     * @return string Converted to binary.
     */
    private static function getBinary($bitField)
    {
        return str_pad(decbin($bitField), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Wallpaper.
     *
     * @param int $id Wallpaper's ID.
     *
     * @return Wallpaper Wallpaper.
     */
    public function wallpaper($id)
    {
        return new Wallpaper($id, $this->client);
    }

    /**
     * Returns a new Filter object to use as a fluent interface.
     *
     * @return Filter
     */
    public function filter()
    {
        return new Filter($this);
    }
}
