<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class FunctionsTest extends TestCase
{
    public function testHashPassword()
    {
        $hash = hashPassword('test123');
        $this->assertStringStartsWith('$2y$', $hash);
        $this->assertTrue(password_verify('test123', $hash));
    }

    public function testHEscapesHtml()
    {
        $this->assertEquals('&lt;script&gt;', h('<script>'));
        $this->assertEquals('&amp;', h('&'));
        $this->assertEquals('', h(null));
    }

    public function testCalculateLFTHolidays()
    {
        $this->assertEquals(0, calculateLFTHolidays(0));
        $this->assertEquals(12, calculateLFTHolidays(1));
        $this->assertEquals(14, calculateLFTHolidays(2));
        $this->assertEquals(16, calculateLFTHolidays(3));
        $this->assertEquals(18, calculateLFTHolidays(4));
        $this->assertEquals(20, calculateLFTHolidays(5));
        $this->assertEquals(20, calculateLFTHolidays(10));
        $this->assertEquals(22, calculateLFTHolidays(11));
        $this->assertEquals(22, calculateLFTHolidays(15));
        $this->assertEquals(24, calculateLFTHolidays(16));
        $this->assertEquals(24, calculateLFTHolidays(20));
        $this->assertEquals(26, calculateLFTHolidays(21));
        $this->assertEquals(26, calculateLFTHolidays(30));
    }

    public function testValidateCURP()
    {
        $this->assertTrue(validateCURP('JUAP800101HDFRRN01'));
        $this->assertFalse(validateCURP(''));
        $this->assertFalse(validateCURP('corta'));
    }

    public function testValidateRFC()
    {
        $this->assertTrue(validateRFC('PELJ800101XXX'));
        $this->assertFalse(validateRFC(''));
        $this->assertFalse(validateRFC('XAMG123'));
    }
}
