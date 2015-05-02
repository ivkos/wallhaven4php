<?php

namespace Wallhaven;

/**
 * User
 *
 * @package Wallhaven
 */
class User
{
    /**
     * @var string Username.
     */
    private $username;

    /**
     * @param string $username Username.
     */
    public function __construct($username)
    {
        $this->username = $username;
    }

    /**
     * @return string Username.
     */
    public function getUsername()
    {
        return $this->username;
    }

    // TODO Implement these.
    // public function getWallpapers() {}

    // public function getUsername () {}
    // public function getLastActiveTime () {}
    // public function getJoinedTime () {}
    // public function getUploads () {}
    // public function getFavorites () {}
    // public function getSubscribers () {}
    // public function getProfileViews () {}
    // public function getProfileComments () {}
    // public function getSiteComments () {}
    // public function getWallpapersTagged () {}
    // public function getWallpapersFlagged () {}

    /**
     * @return string Username.
     */
    public function __toString()
    {
        return $this->username;
    }
}
