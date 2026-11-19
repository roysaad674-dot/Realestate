<?php
/**
 * EstateOS bootstrap: autoloading, configuration, session.
 * Required by public/index.php and by every CLI script in database/.
 */

declare(strict_types=1);

if (!defined('ESTATEOS_ROOT')) {
    define('ESTATEOS_ROOT', __DIR__);
}

// PSR-4 style autoloader for App\ -> app/
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = ESTATEOS_ROOT . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

\App\Core\App::boot(ESTATEOS_ROOT);

require_once ESTATEOS_ROOT . '/app/Support/helpers.php';
