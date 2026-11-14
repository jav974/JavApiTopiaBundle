<?php

use Symfony\Component\Filesystem\Filesystem;

require dirname(__DIR__) . '/vendor/autoload.php';

// Always start from a fresh test kernel cache, otherwise the container is not rebuilt between runs
(new Filesystem())->remove(sys_get_temp_dir() . '/apitopia-tests');
