<?php
require_once dirname(__FILE__) . '/vendor/autoload.php';


function gingerLoadClass($className)
{
    $path =  dirname(__FILE__) .'/'. str_replace('\\', '/', $className . '.php');
    if (file_exists($path))
    {
        require_once $path;
    }
}

spl_autoload_register('gingerLoadClass');