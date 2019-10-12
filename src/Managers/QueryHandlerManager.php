<?php
namespace PoP\ComponentModel\Managers;

class QueryHandlerManager implements QueryHandlerManagerInterface
{
    protected $queryhandlers = [];

    public function add($name, $queryhandler)
    {
        $this->queryhandlers[$name] = $queryhandler;
    }

    public function get($name)
    {
        $queryhandler = $this->queryhandlers[$name];
        if (!$queryhandler) {
            throw new \Exception(sprintf('No QueryHandler with name \'%s\' (%s)', $name, fullUrl()));
        }
        return $queryhandler;
    }
}
