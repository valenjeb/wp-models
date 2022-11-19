<?php

/**
 * Plugin Name:     WP Models
 * Plugin URI:      https://github.com/valenjeb/wp-models
 * Author:          Valentin Jebelev
 * Author URI:      https://github.com/valenjeb
 * Text Domain:     wp-models
 * Domain Path:     /languages
 * Version:         0.1.0
 */

declare(strict_types=1);

$autoload = dirname(__FILE__) . '/vendor/autoload.php';

if (! file_exists($autoload)) {
    wp_die();
}

require_once $autoload;
