<?php
/**
 * H3PHP — Params Test
 */

namespace H3Php\Tests\Generator;

use H3Php\Generator\Params;
use PHPUnit\Framework\TestCase;

class ParamsTest extends TestCase
{
    public function testDefaults(): void
    {
        $params = new Params();

        $this->assertEquals(864, $params->width);
        $this->assertEquals(480, $params->height);
        $this->assertEquals(56, $params->frames);
        $this->assertEquals(20, $params->steps);
        $this->assertEquals(50, $params->ditLayers);
        $this->assertEquals(1, $params->denoiseReuse);
        $this->assertEquals(1, $params->coreReuse);
        $this->assertEquals(42, $params->seed);
    }

    public function testValidParams(): void
    {
        $params = new Params();
        $params->width = 512;
        $params->height = 512;
        $params->frames = 39; // 5 + 17*2 = 39

        $result = $params->validate();
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testInvalidWidth(): void
    {
        $params = new Params();
        $params->width = 100; // Not multiple of 32

        $result = $params->validate();
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    public function testInvalidHeight(): void
    {
        $params = new Params();
        $params->height = 100; // Not multiple of 32

        $result = $params->validate();
        $this->assertFalse($result['valid']);
    }

    public function testCanvasTooLarge(): void
    {
        $params = new Params();
        $params->width = 1024;
        $params->height = 1024; // 1024*1024 > 768*1344

        $result = $params->validate();
        $this->assertFalse($result['valid']);
    }

    public function testInvalidFrames(): void
    {
        $params = new Params();
        $params->frames = 10; // Below minimum 22

        $result = $params->validate();
        $this->assertFalse($result['valid']);
    }

    public function testInvalidLayers(): void
    {
        $params = new Params();
        $params->ditLayers = 20; // Below minimum 35

        $result = $params->validate();
        $this->assertFalse($result['valid']);
    }

    public function testTooManyReferences(): void
    {
        $params = new Params();
        $params->refImages = array_fill(0, 10, 'img.jpg'); // Max 9

        $result = $params->validate();
        $this->assertFalse($result['valid']);
    }

    public function testFirstFrameWithReferences(): void
    {
        $params = new Params();
        $params->firstFrame = 'first.jpg';
        $params->refImages = ['ref.jpg'];

        $result = $params->validate();
        $this->assertFalse($result['valid']);
    }

    public function testSrRequiresBinAndModelDir(): void
    {
        $params = new Params();
        $params->sr = true;

        $result = $params->validate();
        $this->assertFalse($result['valid']);
    }

    public function testSrWithBinAndModelDir(): void
    {
        $params = new Params();
        $params->sr = true;
        $params->srBin = '/tmp/realesrgan';
        $params->srModelDir = '/tmp/realesrgan/models';

        $result = $params->validate();
        $this->assertTrue($result['valid']);
    }

    public function testAlignFrames(): void
    {
        $params = new Params();

        // Test alignment to 5 + 17*n
        $params->frames = 22;
        $this->assertEquals(22, $params->alignFrames());

        $params->frames = 39; // 5 + 17*2
        $this->assertEquals(39, $params->alignFrames());

        $params->frames = 40; // Should align to 39
        $this->assertEquals(39, $params->alignFrames());

        $params->frames = 10; // Below minimum, should return 22
        $this->assertEquals(22, $params->alignFrames());
    }
}
