<?php

namespace Tests\Unit;

use App\Support\CoordinateNormalizer;
use PHPUnit\Framework\TestCase;

class CoordinateNormalizerTest extends TestCase
{
    public function test_grouped_spreadsheet_coordinates_are_normalized_without_broad_fallback(): void
    {
        $this->assertSame(-6.812391, CoordinateNormalizer::parse('-6.812.391', -90, 90));
        $this->assertSame(107.194458, CoordinateNormalizer::parse('107.194.458', -180, 180));
        $this->assertSame(-6.812391, CoordinateNormalizer::parse('-6,812391', -90, 90));
    }

    public function test_empty_out_of_range_and_ambiguous_coordinates_are_rejected(): void
    {
        $this->assertNull(CoordinateNormalizer::parse('', -90, 90));
        $this->assertNull(CoordinateNormalizer::parse('91.000000', -90, 90));
        $this->assertNull(CoordinateNormalizer::parse('-6812391', -90, 90));
        $this->assertNull(CoordinateNormalizer::parse('107194458', -180, 180));
        $this->assertNull(CoordinateNormalizer::parse('-6.81.23', -90, 90));
        $this->assertNull(CoordinateNormalizer::parse('not-a-coordinate', -180, 180));
    }

    public function test_runtime_can_decode_the_exact_fixed_scale_mysql_import_shape(): void
    {
        $this->assertSame(-6.812391, CoordinateNormalizer::parse('-6812391', -90, 90, true));
        $this->assertSame(107.194458, CoordinateNormalizer::parse('107194458', -180, 180, true));

        // Short arbitrary integers and values still outside the coordinate
        // range are not repaired speculatively.
        $this->assertNull(CoordinateNormalizer::parse('120', -90, 90, true));
        $this->assertNull(CoordinateNormalizer::parse('91000000', -90, 90, true));
    }
}
