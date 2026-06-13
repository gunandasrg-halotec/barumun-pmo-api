<?php
namespace App\Core;
use Illuminate\Http\Resources\Json\ResourceCollection as BaseResourceCollecion;
/**
 * Purpose to override ResourceColletion paginationInformation method
 * return value;
 */
class ResourceCollection extends BaseResourceCollecion
{
    public function __construct($resource, $collects)
    {
        $this->collects = $collects;
        parent::__construct($resource);
    }
    /**
     * Overides method paginationInformation on base class
     * -remove key 'links'
     * -rename key 'meta' to  'pagination'
     * @param mixed $request
     * @param mixed $paginated
     * @param mixed $default
     */
    public function paginationInformation($request, $paginated, $default)
    {
        unset($default['links']);
        unset($default['meta']["links"]);
        $default["meta"]["limit"] = $default["meta"]["per_page"];
        $default["meta"]["page"] = $default["meta"]["current_page"];
        unset($default["meta"]["per_page"]);
        unset($default["meta"]["current_page"]);
        return $default;
    }
}