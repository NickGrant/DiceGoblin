<?php
declare(strict_types=1);

use PHPUnit\Framework\TestSuite;

final class BackendTestSuite
{
    public static function suite(): TestSuite
    {
        $suite = new TestSuite('backend');
        self::addTestsFromDirectory($suite, __DIR__);

        return $suite;
    }

    private static function addTestsFromDirectory(TestSuite $suite, string $directory): void
    {
        // PHPUnit's directory iterator omits entries on the Windows Docker bind mount.
        foreach (glob($directory . '/*') ?: [] as $path) {
            if (is_dir($path)) {
                self::addTestsFromDirectory($suite, $path);
            } elseif (str_ends_with($path, 'Test.php')) {
                $suite->addTestFile($path);
            }
        }
    }
}
