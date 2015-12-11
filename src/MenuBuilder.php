<?php

namespace Pingpong\Menus;

use Countable;
use Illuminate\Config\Repository;
use Illuminate\View\Factory as ViewFactory;

class MenuBuilder implements Countable
{
    /**
     * Menu name.
     *
     * @var string
     */
    protected $menu;

    /**
     * Array menu items.
     *
     * @var array
     */
    protected $items = array();

    /**
     * Default presenter class.
     *
     * @var string
     */
    protected $presenter = 'Pingpong\Menus\Presenters\Bootstrap\NavbarPresenter';

    /**
     * Style name for each presenter.
     *
     * @var array
     */
    protected $styles = array();

    /**
     * Prefix URL.
     *
     * @var string|null
     */
    protected $prefixUrl = null;

    /**
     * The name of view presenter.
     *
     * @var null
     */
    protected $view = null;

    /**
     * The laravel view factory instance.
     *
     * @var \Illumiate\View\Factory
     */
    protected $views;

    /**
     * Determine whether the ordering feature is enabled or not.
     *
     * @var boolean
     */
    protected $ordering = false;

    /**
     * Resolved item binding map.
     *
     * @var array
     */
    protected $bindings = array();

    /**
     * Constructor.
     *
     * @param string $menu
     */
    public function __construct($menu, Repository $config)
    {
        $this->menu = $menu;
        $this->config = $config;
    }

    /**
     * Get menu name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->menu;
    }

    /**
     * Find menu item by given its title.
     *
     * @param  string        $title
     * @param  callable|null $callback
     * @return mixed
     */
    public function whereTitle($title, callable $callback = null)
    {
        $item = $this->findBy('title', $title);

        if (is_callable($callback)) {
            return call_user_func($callback, $item);
        }

        return $item;
    }

    /**
     * Find menu item by given key and value.
     *
     * @param  string $key
     * @param  string $value
     * @return \Pingpong\Menus\MenuItem
     */
    public function findBy($key, $value)
    {
        return collect($this->items)->filter(function ($item) use ($key, $value) {
            return $item->{$key} == $value;
        })->first();
    }

    /**
     * Set view factory instance.
     *
     * @param ViewFactory $views
     *
     * @return $this
     */
    public function setViewFactory(ViewFactory $views)
    {
        $this->views = $views;

        return $this;
    }

    /**
     * Set view.
     *
     * @param string $view
     *
     * @return $this
     */
    public function setView($view)
    {
        $this->view = $view;

        return $this;
    }

    /**
     * Set Prefix URL.
     *
     * @param string $prefixUrl
     *
     * @return $this
     */
    public function setPrefixUrl($prefixUrl)
    {
        $this->prefixUrl = $prefixUrl;

        return $this;
    }

    /**
     * Set styles.
     *
     * @param array $styles
     */
    public function setStyles(array $styles)
    {
        $this->styles = $styles;
    }

    /**
     * Set new presenter class.
     *
     * @param string $presenter
     */
    public function setPresenter($presenter)
    {
        $this->presenter = $presenter;
    }

    /**
     * Get presenter instance.
     *
     * @return \Pingpong\Menus\Presenters\PresenterInterface
     */
    public function getPresenter()
    {
        return new $this->presenter();
    }

    /**
     * Set new presenter class by given style name.
     *
     * @param string $name
     *
     * @return self
     */
    public function style($name)
    {
        if ($this->hasStyle($name)) {
            $this->setPresenter($this->getStyle($name));
        }

        return $this;
    }

    /**
     * Determine if the given name in the presenter style.
     *
     * @param $name
     *
     * @return bool
     */
    public function hasStyle($name)
    {
        return array_key_exists($name, $this->getStyles());
    }

    /**
     * Get style aliases.
     *
     * @return mixed
     */
    public function getStyles()
    {
        return $this->styles ?: $this->config->get('menus.styles');
    }

    /**
     * Get the presenter class name by given alias name.
     *
     * @param $name
     *
     * @return mixed
     */
    public function getStyle($name)
    {
        $style = $this->getStyles();

        return $style[$name];
    }

    /**
     * Set new presenter class from given alias name.
     *
     * @param $name
     */
    public function setPresenterFromStyle($name)
    {
        $this->setPresenter($this->getStyle($name));
    }

    /**
     * Set the resolved item bindings
     *
     * @param array $arr
     */
    public function setBindings(array $bindings)
    {
        $this->bindings = $bindings;
        return $this;
    }

    /**
     * Resolves a key from the bindings array.
     *
     * @param  string|array $key
     * @return mixed
     */
    public function resolve($key)
    {
        if (is_array($key)) {

            foreach ($key as $k => $v) {
                $key[$k] = $this->resolve($v);
            }

        } elseif (is_string($key)) {

            $matches = array();
            preg_match_all('/{[\s]*?([^\s]+)[\s]*?}/i', $key, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {

                $parts = explode('.', $match[1]);

                // Deal with keys that match the binding exactly
                if (array_key_exists($match[1], $this->bindings)) {
                    $key = preg_replace('/' . $match[0] . '/', $this->bindings[$match[1]], $key, 1);
                } 

                // Then with keys that reference objects via the dot annotation
                elseif (count($parts) > 1 && array_key_exists($parts[0], $this->bindings)) {
                    $key = $this->bindings[$parts[0]];
                    for ($i = 1; $i < count($parts); $i++) {
                        $key = $key->{ $parts[$i] };
                    }
                }

            }

        }

        return $key;
    }

    /**
     * Resolves an array of menu items properties.
     *
     * @param  array  &$items
     * @return void
     */
    protected function resolveItems(array $items)
    {
        $resolver = function ($property) {
            return $this->resolve($property) ?: $property;
        };

        for ($i = 0; $i < count($items); $i++) {
            $items[$i]->fill(array_map($resolver, $items[$i]->getProperties()));
        }

        return $items;
    }


    /**
     * Gets the current item array filtered by 'visible' parameter value.
     * 
     * @param  array  $items 
     * @return array
     */
    protected function getVisibleItems(array $items)
    {
        foreach ($items as $key => $item) {

            if (is_callable($item->visible)) {

                $context = new \stdclass;
                $context->auth = \Auth::user();
                $context->item = $item;

                foreach ($this->bindings as $k => $binding) {
                    $context->$k = $binding;
                }

                $item->visible = $item->visible->bindTo($context, $context);
            }

            if (!$item->visible || is_callable($item->visible) 
                && !call_user_func_array($item->visible, [$item, $this->bindings])) {

                unset($items[$key]);

            }

        }

        return $items;
    }

    /**
     * Add new child menu.
     *
     * @param array $attributes
     *
     * @return \Pingpong\Menus\MenuItem
     */
    public function add(array $attributes = array())
    {
        $item = MenuItem::make($attributes);

        $this->items[] = $item;

        return $item;
    }

    /**
     * Create new menu with dropdown.
     *
     * @param $title
     * @param callable $callback
     * @param array    $attributes
     *
     * @return $this
     */
    public function dropdown($title, \Closure $callback, $order = 0, array $attributes = array(), $visible = true)
    {
        $item = MenuItem::make(compact('title', 'order', 'visible') + $attributes);

        call_user_func($callback, $item);

        $this->items[] = $item;

        return $this;
    }

    /**
     * Register new menu item using registered route.
     *
     * @param $route
     * @param $title
     * @param array $parameters
     * @param array $attributes
     *
     * @return static
     */
    public function route($route, $title, $parameters = array(), $order = null, $attributes = array(), $visible = true)
    {
        $route = array($route, $parameters);

        $item = MenuItem::make(
            compact('route', 'title', 'parameters', 'attributes', 'order', 'visible')
        );

        $this->items[] = $item;

        return $item;
    }

    /**
     * Format URL.
     *
     * @param string $url
     *
     * @return string
     */
    protected function formatUrl($url)
    {
        $uri = !is_null($this->prefixUrl) ? $this->prefixUrl.$url : $url;

        return $uri == '/' ? '/' : ltrim(rtrim($uri, '/'), '/');
    }

    /**
     * Register new menu item using url.
     *
     * @param $url
     * @param $title
     * @param array $attributes
     *
     * @return static
     */
    public function url($url, $title, $order = 0, $attributes = array(), $visible = true)
    {
        $url = $this->formatUrl($url);

        return $this->rawUrl($url, $title, $order, $attributes, $visible);
    }

    public function rawUrl($url, $title, $order = 0, $attributes = array(), $visible = true)
    {
        $item = MenuItem::make(compact('url', 'title', 'order', 'attributes', 'visible'));

        $this->items[] = $item;

        return $item;
    }

    /**
     * Add new divider item.
     *
     * @param int $order
     * @return \Pingpong\Menus\MenuItem
     */
    public function addDivider($order = null, $visible = true)
    {
        $this->items[] = new MenuItem(array('name' => 'divider', 'order' => $order, 'visible' => $visible));

        return $this;
    }

    /**
     * Add new header item.
     *
     * @return \Pingpong\Menus\MenuItem
     */
    public function addHeader($title, $order = null, $visible = true)
    {
        $this->items[] = new MenuItem(array(
            'name' => 'header',
            'title' => $title,
            'order' => $order,
            'visible' => $visible,
        ));

        return $this;
    }

    /**
     * Alias for "addHeader" method.
     *
     * @param string $title
     *
     * @return $this
     */
    public function header($title, $visible = true)
    {
        return $this->addHeader($title, $visible);
    }

    /**
     * Alias for "addDivider" method.
     *
     * @return $this
     */
    public function divider($visible = true)
    {
        return $this->addDivider(null, $visible);
    }

    /**
     * Get items count.
     *
     * @return int
     */
    public function count()
    {
        return count($this->items);
    }

    /**
     * Empty the current menu items.
     */
    public function destroy()
    {
        $this->items = array();

        return $this;
    }

    /**
     * Render the menu to HTML tag.
     *
     * @param string $presenter
     *
     * @return string
     */
    public function render($presenter = null)
    {
        if (!is_null($this->view)) {
            return $this->renderView($presenter);
        }

        if ($this->hasStyle($presenter)) {
            $this->setPresenterFromStyle($presenter);
        }

        if (!is_null($presenter) && !$this->hasStyle($presenter)) {
            $this->setPresenter($presenter);
        }

        return $this->renderMenu();
    }

    /**
     * Render menu via view presenter.
     *
     * @return \Illuminate\View\View
     */
    public function renderView($presenter = null)
    {
        return $this->views->make($presenter ?: $this->view, [
            'items' => $this->getFilteredItems(),
        ]);
    }

    /**
     * Get original items.
     *
     * @return array
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * Gets the current item list with filters applied.
     * 
     * @return array
     */
    public function getFilteredItems()
    {
        $items = $this->items;

        foreach (['getVisibleItems', 'resolveItems', 'getOrderedItems'] as $filter) {
            $items = $this->$filter(array_values($items));
        }

        return $items;
    }

    /**
     * Get menu items as laravel collection instance.
     *
     * @return \Illuminate\Support\Collection
     */
    public function toCollection()
    {
        return collect($this->items);
    }

    /**
     * Get menu items as array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->toCollection()->toArray();
    }

    /**
     * Enable menu ordering.
     *
     * @return self
     */
    public function enableOrdering()
    {
        $this->ordering = true;

        return $this;
    }

    /**
     * Disable menu ordering.
     *
     * @return self
     */
    public function disableOrdering()
    {
        $this->ordering = true;

        return $this;
    }

    /**
     * Get menu items and order it by 'order' key.
     *
     * @return array
     */
    public function getOrderedItems(array $items)
    {
        if (config('menus.ordering') || $this->ordering) {
            return collect($items)>sortBy(function ($item) {
                return $item->order;
            })->all();
        }

        return $items;
    }

    /**
     * Render the menu.
     *
     * @return string
     */
    protected function renderMenu()
    {
        $presenter = $this->getPresenter();
        $menu = $presenter->getOpenTagWrapper();

        foreach ($this->getFilteredItems() as $item) {
            if ($item->hasSubMenu()) {
                $menu .= $presenter->getMenuWithDropDownWrapper($item);
            } elseif ($item->isHeader()) {
                $menu .= $presenter->getHeaderWrapper($item);
            } elseif ($item->isDivider()) {
                $menu .= $presenter->getDividerWrapper();
            } else {
                $menu .= $presenter->getMenuWithoutDropdownWrapper($item);
            }
        }

        $menu .= $presenter->getCloseTagWrapper();

        return $menu;
    }
}
