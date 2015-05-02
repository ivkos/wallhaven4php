<?php

use Wallhaven\Category;
use Wallhaven\Order;
use Wallhaven\Purity;
use Wallhaven\Sorting;
use Wallhaven\Wallhaven;

class WallhavenTest extends PHPUnit_Framework_TestCase
{
    public function testGetToken()
    {
        $wh = new Wallhaven();
        $initClient = self::getProtectedMethod('Wallhaven\Wallhaven', 'initClient');
        $getToken = self::getProtectedMethod('Wallhaven\Wallhaven', 'getToken');

        $initClient->invoke($wh);
        $token = $getToken->invoke($wh);

        $this->assertNotEmpty($token);
    }

    private static function getProtectedMethod($class, $method)
    {
        $m = new ReflectionMethod($class, $method);
        $m->setAccessible(true);

        return $m;
    }

    public function testNoLogin()
    {
        new Wallhaven();
    }

    public function testLogin()
    {
        new Wallhaven(self::getEnvUsername(), self::getEnvPassword());
    }

    private static function getEnvUsername()
    {
        $username = getenv('WALLHAVEN_USERNAME');

        if (empty($username)) {
            self::fail("Cannot get username from environment variable.");
        }

        return $username;
    }

    private static function getEnvPassword()
    {
        $password = getenv('WALLHAVEN_PASSWORD');

        if (empty($password)) {
            self::fail("Cannot get password from environment variable.");
        }

        return $password;
    }

    /**
     * @expectedException \Wallhaven\Exceptions\LoginException
     * @expectedExceptionMessage Incorrect username or password.
     */
    public function testLoginWithEmptyPassword()
    {
        new Wallhaven(self::getEnvUsername());
    }

    /**
     * @expectedException \Wallhaven\Exceptions\LoginException
     * @expectedExceptionMessage Incorrect username or password.
     */
    public function testLoginWithIncorrectCredentials()
    {
        new Wallhaven("this user should not exist", "wrong password");
    }

    public function testSearch()
    {
        $wh = new Wallhaven();

        $wallpapers = $wh->search("macro",
            Category::PEOPLE,
            Purity::SFW,
            Sorting::RELEVANCE,
            Order::DESC
        );

        $this->assertNotEmpty($wallpapers);
    }

    public function testSearchLoggedIn()
    {
        $wh = new Wallhaven(self::getEnvUsername(), self::getEnvPassword());

        $wallpapers = $wh->search("macro",
            Category::PEOPLE,
            Purity::SFW,
            Sorting::RELEVANCE,
            Order::DESC
        );

        $this->assertNotEmpty($wallpapers);
    }

    public function testSearchNsfwLoggedIn()
    {
        $wh = new Wallhaven(self::getEnvUsername(), self::getEnvPassword());

        $wallpapers = $wh->search("",
            Category::ALL,
            Purity::NSFW,
            Sorting::RANDOM
        );

        $this->assertNotEmpty($wallpapers);
    }

    public function testSearchNsfwIsEmptyWhenNotLoggedIn()
    {
        $wh = new Wallhaven();

        $wallpapers = $wh->search("",
            Category::ALL,
            Purity::NSFW,
            Sorting::RANDOM
        );

        $this->assertEmpty($wallpapers);
    }

    public function testWallpaperInformationLoggedIn()
    {
        $wh = new Wallhaven(self::getEnvUsername(), self::getEnvPassword());
        $w = $wh->wallpaper(198320);

        $this->assertNotEmpty($w->getTags());
        $this->assertEquals(Purity::SFW, $w->getPurity());
        $this->assertEquals("1920x1080", $w->getResolution());
        $this->assertEquals("374.1 KiB", $w->getSize());
        $this->assertEquals(Category::GENERAL, $w->getCategory());
        $this->assertNotEmpty($w->getViews());
        $this->assertNotEmpty($w->getFavorites());

        $this->assertNotEmpty($w->getFeaturedBy());
        $this->assertInstanceOf("DateTime", $w->getFeaturedDate());

        $this->assertNotEmpty($w->getUploadedBy());
        $this->assertInstanceOf("DateTime", $w->getUploadedDate());
    }

    /**
     * @expectedException \Wallhaven\Exceptions\LoginException
     * @expectedExceptionMessage Access to wallpaper is forbidden.
     */
    public function testWallpaperInformationNsfwLoggedOut()
    {
        (new Wallhaven())->wallpaper(8273)->getUploadedDate();
    }

    public function testWallpaperInformationNsfwLoggedIn()
    {
        $wh = new Wallhaven(self::getEnvUsername(), self::getEnvPassword());
        $wh->wallpaper(8273)->getUploadedDate();
    }

    /**
     * @expectedException \Wallhaven\Exceptions\NotFoundException
     * @expectedExceptionMessage Wallpaper not found.
     */
    public function testNonExistentWallpaperInformation()
    {
        (new Wallhaven())->wallpaper(300000000)->getUploadedDate();
    }

    public function testCurrentUser()
    {
        $wh = new Wallhaven(self::getEnvUsername(), self::getEnvPassword());

        $this->assertEquals(self::getEnvUsername(), $wh->user()->getUsername());
    }

    public function testAnotherUser()
    {
        $wh = new Wallhaven();

        $this->assertEquals('Gandalf', $wh->user('Gandalf')->getUsername());
    }

    public function testImageUrlPng() {
        $wh = new Wallhaven();

        $url = $wh->wallpaper(43118)->getImageUrl();

        $this->assertEquals('http://wallpapers.wallhaven.cc/wallpapers/full/wallhaven-43118.png', $url);
    }

    public function testThumbnailUrl()
    {
        $wh = new Wallhaven();

        $thumbUrl = $wh->wallpaper(198320)->getThumbnailUrl();

        $this->assertEquals('http://alpha.wallhaven.cc/wallpapers/thumb/small/th-198320.jpg', $thumbUrl);
    }

    public function testCachedIsFaster()
    {
        $wh = new Wallhaven();

        $start1 = microtime(true);
        $w1 = $wh->search("cars")[0];
        $w1->setCacheEnabled(false);
        $w1->getFavorites();
        $time1 = microtime(true) - $start1;
        echo "Cache disabled: " . round($time1 * 1000) . " ms" . PHP_EOL;

        $start2 = microtime(true);
        $w1 = $wh->search("cars")[0];
        // Cache is implicitly enabled
        $w1->getFavorites();
        $time2 = microtime(true) - $start2;
        echo "Cache enabled:  " . round($time2 * 1000) . " ms" . PHP_EOL;

        $this->assertTrue($time2 < $time1);
    }
}
