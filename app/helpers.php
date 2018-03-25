<?php

/**
 * Get the path to the base of the install.
 *
 * @param $path
 * @return string
 */
function install_path($path)
{
    if (!empty($pharPath = Phar::running(false))) {
        return dirname($pharPath) . '/' . $path;
    } else {
        return app()->basePath($path);
    }
}