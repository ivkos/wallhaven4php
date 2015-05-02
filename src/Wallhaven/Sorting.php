<?php

namespace Wallhaven;

abstract class Sorting
{
    /**
     * Sort by relevance the search query.
     */
    const RELEVANCE = "relevance";

    /**
     * Sort randomly.
     */
    const RANDOM = "random";

    /**
     * Sort by upload date.
     */
    const DATE_ADDED = "date_added";

    /**
     * Sort by number of views.
     */
    const VIEWS = "views";

    /**
     * Sort by number of favorites.
     */
    const FAVORITES = "favorites";
}
