<?php

namespace Wallhaven;

/**
 * Fluent Interface for searching
 *
 * @package Wallhaven
 */
class Filter
{
    private $wallhaven;

    private $keywords    = "";
    private $categories  = Category::ALL;
    private $purity      = Purity::SFW;
    private $sorting     = Sorting::RELEVANCE;
    private $order       = Order::DESC;
    private $resolutions = [];
    private $ratios      = [];
    private $pages       = 1;

    public function __construct(Wallhaven $wallhaven)
    {
        $this->wallhaven = $wallhaven;
    }

    /**
     * @param string $keywords What to search for. Can be keywords or #tagnames, e.g. #cars
     *
     * @return self
     */
    public function keywords($keywords)
    {
        $this->keywords = $keywords;

        return $this;
    }

    /**
     * @param int $categories Categories to include. This is a bit field, for example:
     *                        <samp>Category::GENERAL | Category::PEOPLE</samp>
     *
     * @return self
     */
    public function categories($categories)
    {
        $this->categories = $categories;

        return $this;
    }

    /**
     * @param int $purity Purity of wallpapers. This is a bit field, for example:
     *                    <samp>Purity::SFW | Purity::NSFW</samp>
     *
     * @return self
     */
    public function purity($purity)
    {
        $this->purity = $purity;

        return $this;
    }

    /**
     * @param string $sorting Sorting, e.g. <samp>Sorting::RELEVANCE</samp>
     *
     * @return self
     */
    public function sorting($sorting)
    {
        $this->sorting = $sorting;

        return $this;
    }

    /**
     * @param string $order Order of results. Can be <samp>Order::ASC</samp> or <samp>Order::DESC</samp>
     *
     * @return self
     */
    public function order($order)
    {
        $this->order = $order;

        return $this;
    }

    /**
     * @param string[] $resolutions Array of resolutions in the format of WxH, for example:
     *                              <samp>['1920x1080', '1280x720']</samp>
     *
     * @return self
     */
    public function resolutions(array $resolutions)
    {
        $this->resolutions = $resolutions;

        return $this;
    }

    /**
     * @param string[] $ratios Array of ratios in the format of WxH, for example:
     *                         <samp>['16x9', '4x3']</samp>
     *
     * @return self
     */
    public function ratios(array $ratios)
    {
        $this->ratios = $ratios;

        return $this;
    }

    /**
     * Set number of pages of wallpapers to fetch. A single page typically consists of 24, 32 or 64 wallpapers.
     *
     * @param int $pages Number of pages.
     *
     * @throws \InvalidArgumentException Thrown if the number of pages is negative or zero.
     * @return self
     */
    public function pages($pages)
    {
        if ($pages <= 0) {
            throw new \InvalidArgumentException("Number of pages must be positive.");
        }

        $this->pages = $pages;

        return $this;
    }

    /**
     * Execute the search with the specified filters.
     *
     * @return WallpaperList Wallpapers matching the specified filters.
     */
    public function getWallpapers()
    {
        $wallpapers = new WallpaperList();

        for ($i = 1; $i <= $this->pages; ++$i) {
            $wallpapers->addAll($this->wallhaven->search(
                $this->keywords,
                $this->categories,
                $this->purity,
                $this->sorting,
                $this->order,
                $this->resolutions,
                $this->ratios,
                $i
            ));
        }

        return $wallpapers;
    }
}
