<?php // phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols

/**
 * PHPUnit bootstrap file.
 *
 * phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
 * phpcs:disable Squiz.NamingConventions.ValidVariableName.NotCamelCaps
 * phpcs:disable Squiz.Functions.GlobalFunction.Found
 */

declare(strict_types=1);

$_tests_dir = getenv('WP_TESTS_DIR');

if (! $_tests_dir) {
    $_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$_phpunit_polyfills_path = getenv('WP_TESTS_PHPUNIT_POLYFILLS_PATH');
if ($_phpunit_polyfills_path !== false) {
    define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path);
}

if (! file_exists(sprintf('%s/includes/functions.php', $_tests_dir))) {
    printf(
        'Could not find %s/includes/functions.php, have you run bin/install-wp-tests.sh ?%s',
        $_tests_dir, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        PHP_EOL
    );
    exit(1);
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin(): void
{
    require dirname(__FILE__, 2) . '/wp-models.php';
}

tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
