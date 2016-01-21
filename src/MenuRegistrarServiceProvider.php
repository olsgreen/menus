<?php

namespace Pingpong\Menus;

use Illuminate\Support\ServiceProvider;
use Pingpong\Menus\Menu;

class MenuRegistrarServiceProvider extends ServiceProvider
{
    /**
     * List of menu providers to register.
     *
     * @var array
     */
    protected $menus = [];

    /**
     * Register the UI menus.
     *
     * @return void
     */
    public function boot(Menu $menu)
    {
        $this->registerMenus($this->menus);
    }

    /**
     * Registers each providers menus.
     *
     * @param  array $menus
     * @return void
     */
    public function registerMenus($menus)
    {
        $builder = $this->app['menus'];

        foreach ($menus as $class) {

            $provider = new $class;

            $menus = $provider->provides();

            foreach ($menus as $key => $menu) {
                $builder->create($key, function ($value) use ($provider, $menu) {
                    call_user_func_array([$provider, $menu], [$value]);
                });
            }

        }
    }

    public function register()
    {
        //
    }
}
