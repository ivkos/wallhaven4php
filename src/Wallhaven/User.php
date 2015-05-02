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

    /**
     * @return string Username.
     */
    public function __toString()
    {
        return $this->username;
    }
}
