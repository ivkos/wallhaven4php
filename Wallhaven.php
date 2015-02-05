<?php

// Purity
define("WH_PURITY_SAFE",    4);
define("WH_PURITY_SKETCHY", 2);
define("WH_PURITY_NSFW",    1);

// Categories
define("WH_CATEGORY_GENERAL", 4);
define("WH_CATEGORY_ANIME",   2);
define("WH_CATEGORY_PEOPLE",  1);

/**
 * Class Wallhaven
 *
 * @version 1.1.0
 */
class Wallhaven
{
    const URL_HOME         = "http://alpha.wallhaven.cc/";
    const URL_WALLPAPER    = "http://alpha.wallhaven.cc/wallpaper";
    const URL_LOGIN        = "http://alpha.wallhaven.cc/auth/login";
    const URL_SEARCH       = "http://alpha.wallhaven.cc/search";
    const URL_IMG_PREFIX   = "http://alpha.wallhaven.cc/wallpapers/full/wallhaven-";
    const URL_THUMB_PREFIX = "http://alpha.wallhaven.cc/wallpapers/thumb/small/th-";

    private $_login;
    private $_cookiesTmp;

    /**
     * Wallhaven constructor.
     *
     * @param string $username Username.
     * @param string $password Password.
     *
     * @throws Exception
     */
    public function __construct($username = null, $password = null)
    {
        if (empty($username))
        {
            $this->_login = false;

            return;
        }

        // Create temporary file for cookies
        $this->_cookiesTmp = tempnam(sys_get_temp_dir(), "wallhaven_cookies-");

        // Get token
        $home = $this->_curlQuery(self::URL_HOME, "GET", null, true);
        preg_match('/<input name="_token" type="hidden" value="(\w+)">/', $home, $token);

        $this->_curlQuery(self::URL_LOGIN, "POST", [
            "_token"   => $token[1],
            "username" => $username,
            "password" => $password
        ], true);

        $this->_login = true;
    }

    public function __destruct()
    {
        if ($this->_login)
        {
            unlink($this->_cookiesTmp);
        }
    }

    /**
     * Gets information for a specific wallpaper.
     *
     * @param int $wallpaperId Wallpaper ID.
     *
     * @return array Wallpaper information.
     * @throws Exception
     */
    public function getWallpaperInformation($wallpaperId)
    {
        if (!is_int($wallpaperId) || $wallpaperId <= 0)
        {
            throw new Exception("Invalid wallpaper ID.");
        }

        $response = $this->_curlQuery(self::URL_WALLPAPER . "/" . $wallpaperId, "GET", null, true, false);

        $regex = [];

        // Image type (JPG or PNG)
        $regex[] = preg_match(
            '/<img id="wallpaper"\s*src=".*\.(png|jpg)"/',
            $response, $type
        );

        // Purity
        if ($this->_login) {
            $regex[] = preg_match(
                '/<input id="(sfw|sketchy|nsfw)" checked="checked" name="purity" type="radio" value="(sfw|sketchy|nsfw)">/',
                $response, $purity
            );
        } else {
            $regex[] = preg_match(
                '/<input type="radio" checked="checked"><label class="purity (sfw|sketchy)">.+<\/label>/',
                $response, $purity
            );
        }

        // Resolution
        $regex[] = preg_match(
            '/<dt>Resolution<\/dt>\s*<dd>\s*(\d+)\s*x\s*(\d+)\s*<\/dd>/',
            $response, $resolution
        );

        // Size
        $regex[] = preg_match(
            '/<dt>Size<\/dt>\s*<dd>\s*(\d+\.?\d* (MiB|KiB))\s*<\/dd>/',
            $response, $size
        );

        // Category
        $regex[] = preg_match(
            '/<dt>Category<\/dt>\s*<dd>\s*(General|Anime|People)\s*<\/dd>/',
            $response, $category
        );

        // Views count
        $regex[] = preg_match(
            '/<dt>Views<\/dt>\s*<dd>\s*([\d\,]+)\s*<\/dd>/',
            $response, $views
        );

        // Favorites count
        $regex[] = preg_match(
            '/<dt>Favorites<\/dt>\s*<dd>\s*(<a.*>)?([\d\,]+)\s*(<\/a>)?\s*<\/dd>/s',
            $response, $favorites
        );

        // Added time
        $regex[] = preg_match(
            '/<dt>Added<\/dt>\s*<dd>\s*<time.+datetime="(.+)">.*<\/time>\s*<\/dd>/s',
            $response, $addedTime
        );

        foreach ($regex as $result)
        {
            if ($result === 0 || $result === false)
            {
                throw new Exception("Could not parse wallpaper info. Has Wallhaven changed design?");
            }
        }

        return [
            "infoUrl"    => self::URL_WALLPAPER . "/" . $wallpaperId,
            "imgUrl"     => self::URL_IMG_PREFIX . $wallpaperId . "." . $type[1],
            "thumbUrl"   => self::URL_THUMB_PREFIX . $wallpaperId . ".jpg",
            "type"       => $type[1],
            "purity"     => $purity[1],
            "resolution" => $resolution[1] . "x" . $resolution[2],
            "size"       => $size[1],
            "category"   => $category[1],
            "views"      => str_replace(",", "", $views[1]),
            "favorites"  => str_replace(",", "", $favorites[2]),
            "addedTime"  => strtotime($addedTime[1])
        ];
    }

    /**
     * Searches for wallpapers.
     *
     * @param string   $query              What to search for. Searching for specific tags can be done with #tagname
     *                                     (e.g. #cars).
     * @param int      $categories         Categories to include. This is a bitfield (e.g. WH_CATEGORY_GENERAL |
     *                                     WH_CATEGORY_PEOPLE).
     * @param int      $purity             Purity of wallpapers. This is a bitfield (e.g. WH_PURITY_SAFE |
     *                                     WH_PURITY_SKETCHY).
     * @param string   $sorting            Sorting (can be relevance/random/date_added/views/favorites).
     * @param string   $order              Order of results (can be desc/asc).
     * @param string[] $resolutions        Resolutions in the format of WxH (e.g. 1920x1080).
     * @param string[] $ratios             Ratios in the format of WxH (e.g. 16x9).
     * @param bool     $pngCheck           If FALSE, all images are considered JPGs, which speeds up the search,
     *                                     at the expense of potential 404 errors for PNG images.
     *
     * @return array List of wallpapers matching the search criteria.
     * @throws Exception
     */
    public function search($query, $categories = 7, $purity = 6, $sorting = "relevance", $order = "desc",
                           $resolutions = [], $ratios = [], $pngCheck = true)
    {
        $result = $this->_curlQuery(self::URL_SEARCH, "GET", [
            "q"           => $query,
            "categories"  => str_pad(decbin($categories), 3, "0", STR_PAD_LEFT),
            "purity"      => str_pad(decbin($purity), 3, "0", STR_PAD_LEFT),
            "sorting"     => $sorting,
            "order"       => $order,
            "resolutions" => implode(",", $resolutions),
            "ratios"      => implode(",", $ratios)
        ], $this->_login, true);

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($result);
        libxml_use_internal_errors(false);
        $figures = $dom->getElementsByTagName("figure");

        $hrefIndex = $this->_login ? 2 : 1;

        $wallpapers = [];
        foreach ($figures as $figure)
        {
            $url = $figure->childNodes->item($hrefIndex)->getAttribute("href");

            if (!preg_match('/wallpaper\/(\d+)/', $url, $id))
            {
                throw new Exception("Could not parse wallpaper URL. Has Wallhaven changed design?");
            }

            $id = $id[1];

            $imgUrl   = self::URL_IMG_PREFIX . $id . ".jpg";
            $mimeType = "image/jpeg";

            // Check if image exists (gets rid of 404s for PNGs)
            if ($pngCheck)
            {
                $curl = curl_init();

                curl_setopt_array($curl, [
                    CURLOPT_URL            => $imgUrl,
                    CURLOPT_NOBODY         => true,
                    CURLOPT_RETURNTRANSFER => false,
                    CURLOPT_HEADER         => false
                ]);

                curl_exec($curl);
                $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                curl_close($curl);

                if ($httpCode == 404)
                {
                    // .jpg does not exist, assume it is .png
                    $imgUrl   = self::URL_IMG_PREFIX . $id . ".png";
                    $mimeType = "image/png";
                }
            }

            $wallpapers[] = [
                "id"       => (int) $id,
                "url"      => $url,
                "imgUrl"   => $imgUrl,
                "mimeType" => $mimeType
            ];
        }

        return $wallpapers;
    }

    /**
     * Gets a list of random wallpapers.
     *
     * @param int      $categories  Categories to include. This is a bitfield (e.g. WH_CATEGORY_GENERAL |
     *                              WH_CATEGORY_PEOPLE).
     * @param int      $purity      Purity of wallpapers. This is a bitfield (e.g. WH_PURITY_SAFE | WH_PURITY_SKETCHY).
     * @param string[] $resolutions Resolutions in the format of WxH (e.g. 1920x1080).
     * @param string[] $ratios      Ratios in the format of WxH (e.g. 16x9).
     *
     * @return array List of wallpapers.
     * @throws Exception
     */
    public function getRandom($categories = 7, $purity = 6, $resolutions = [], $ratios = [])
    {
        return $this->search(null, $categories, $purity, "random", "desc", $resolutions, $ratios);
    }

    /**
     * Gets a list of top wallpapers (most favorites).
     *
     * @param int      $categories  Categories to include. This is a bitfield (e.g. WH_CATEGORY_GENERAL |
     *                              WH_CATEGORY_PEOPLE).
     * @param int      $purity      Purity of wallpapers. This is a bitfield (e.g. WH_PURITY_SAFE | WH_PURITY_SKETCHY).
     * @param string[] $resolutions Resolutions in the format of WxH (e.g. 1920x1080).
     * @param string[] $ratios      Ratios in the format of WxH (e.g. 16x9).
     *
     * @return array List of wallpapers.
     * @throws Exception
     */
    public function getTop($categories = 7, $purity = 6, $resolutions = [], $ratios = [])
    {
        return $this->search(null, $categories, $purity, "favorites", "desc", $resolutions, $ratios);
    }

    /**
     * Sends an HTTP query using cURL.
     *
     * @param string   $url     URL to send the request to.
     * @param string   $method  HTTP method.
     * @param string[] $data    Request data.
     * @param bool     $cookies Use cookies.
     * @param bool     $xhr     Send the request with a header X-Requested-With: XMLHttpRequest
     *
     * @return string Server's response.
     * @throws Exception
     */
    private function _curlQuery($url, $method, $data = null, $cookies = false, $xhr = false)
    {
        $curl = curl_init();

        if ($method == "GET" && $data !== null)
        {
            $url .= "?" . http_build_query($data);
        }

        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 2
        ]);

        if ($cookies)
        {
            curl_setopt_array($curl, [
                CURLOPT_COOKIEFILE => $this->_cookiesTmp,
                CURLOPT_COOKIEJAR  => $this->_cookiesTmp
            ]);
        }

        if ($xhr)
        {
            curl_setopt_array($curl, [
                CURLOPT_HTTPHEADER => [
                    "X-Requested-With: XMLHttpRequest"
                ]
            ]);
        }

        $response  = curl_exec($curl);
        $curlError = curl_error($curl);
        $httpCode  = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($response === false)
        {
            throw new Exception("cURL Error: " . $curlError);
        }

        // A bit hacky, because of Wallhaven login quirks
        if ($httpCode >= 400 && $httpCode != 405)
        {

            throw new Exception("HTTP Error " . $httpCode);
        }

        return $response;
    }
}