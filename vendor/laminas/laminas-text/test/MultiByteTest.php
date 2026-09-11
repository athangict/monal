<?php

declare(strict_types=1);

namespace LaminasTest\Text;

use Exception;
use Laminas\Text;
use PHPUnit\Framework\TestCase;

use function restore_error_handler;
use function set_error_handler;

use const E_USER_DEPRECATED;

/**
 * @group      Laminas_Text
 */
class MultiByteTest extends TestCase
{
    protected function setUp(): void
    {
        set_error_handler(static function (int $errno, string $errstr): never {
            throw new Exception($errstr, $errno);
        }, E_USER_DEPRECATED);
    }

    protected function tearDown(): void
    {
        restore_error_handler();
    }

    public function testWordWrapTriggersDeprecatedError()
    {
        $this->expectExceptionMessage(
            "This method is deprecated, please use 'Laminas\Stdlib\StringUtils::getWrapper(<charset>)->wordWrap"
        );
        $line = Text\MultiByte::wordWrap('äbüöcß', 2, ' ', true);
    }

    public function testStrPadTriggersDeprecatedError()
    {
        $this->expectExceptionMessage(
            "This method is deprecated, please use 'Laminas\Stdlib\StringUtils::getWrapper(<charset>)->strPad"
        );
        $text = Text\MultiByte::strPad('äääöö', 2, 'ö');
    }
}
