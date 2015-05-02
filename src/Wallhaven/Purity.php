<?php

namespace Wallhaven;

abstract class Purity
{
    /**
     * Safe for work.
     */
    const SFW = 4;

    /**
     * Sketchy. In between safe and not safe for work.
     */
    const SKETCHY = 2;

    /**
     * Not safe for work.
     */
    const NSFW = 1;

    /**
     * Wallpapers of all purities, i.e. safe + sketchy + NSFW.
     */
    const ALL = 7;
}
