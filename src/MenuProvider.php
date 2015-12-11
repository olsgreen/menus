<?php

namespace Pingpong\Menus;

abstract class MenuProvider
{
    abstract public function provides();

    public function addDivider(MenuBuilder $menu)
    {
        $menu->addDivider();
        
        return $this;
    }
}