<?php

require 'Wallhaven.php';

class WallhavenTest extends PHPUnit_Framework_TestCase
{
    public function testLogin()
    {
        new Wallhaven(getenv('WALLHAVENUSERNAME'), getenv('WALLHAVENPASSWORD'));
    }

    /**
     * @expectedException Exception
     */
    public function testLoginWrongCredentials()
    {
        new Wallhaven("this user should not exist", "wrong password");
    }

    public function testSearch()
    {
        $wh = new Wallhaven();

        $result = $wh->search("macro", WH_CATEGORY_GENERAL, WH_PURITY_SAFE, "relevance", "desc", [], [], false);

        $this->assertNotEmpty($result);
    }

    public function testNsfwNotLoggedIn()
    {
        $wh = new Wallhaven();

        $result = $wh->getRandom(WH_CATEGORY_GENERAL, WH_PURITY_NSFW);

        $this->assertEmpty($result);
    }

    public function testNsfwLoggedIn()
    {
        $wh = new Wallhaven(getenv('WALLHAVENUSERNAME'), getenv('WALLHAVENPASSWORD'));

        $result = $wh->search(null, WH_CATEGORY_GENERAL, WH_PURITY_NSFW, "random", "desc", [], [], false);

        $this->assertNotEmpty($result);
    }

    public function testWallpaperInformation()
    {
        $wh = new Wallhaven();
        $wh->getWallpaperInformation(109965);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage HTTP Error 404
     */
    public function testNonExistentWallpaperInformation()
    {
        $wh = new Wallhaven();
        $wh->getWallpaperInformation(300000000);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage HTTP Error 403
     */
    public function testNsfwWallpaperInformationNotLoggedIn()
    {
        $wh = new Wallhaven();
        $wh->getWallpaperInformation(2480);
    }

    public function testNsfwWallpaperInformationLoggedIn()
    {
        $wh = new Wallhaven(getenv('WALLHAVENUSERNAME'), getenv('WALLHAVENPASSWORD'));
        $wh->getWallpaperInformation(2480);
    }
}
 