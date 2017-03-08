<?php
/**
 * Created by PhpStorm.
 * User: Antoine
 * Date: 06/03/2017
 * Time: 21:53
 */

namespace Stoakes\RoutingConverterBundle\Command;


use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Routing\RouteCollection;

class ConvertRouteLoader extends YamlFileLoader
{
    public function load($resource, $type = null)
    {
        $collection = new RouteCollection();

       // $resource = '@AppBundle/Resources/config/import_routing.yml';
        $type = 'yaml';

        return parent::load($resource, $type);
    }

}
