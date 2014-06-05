<?php

interface IFilter
{
    function init(array $config);
    function processLine($line);
    /**
     * @return array
     * array(
     *      '127.0.0.1' => array(
     *          'Connected via service XYZ...',
     *          [...]
     *      ),
     *      [...]
     * )
     */
    function getIpReport();
}
