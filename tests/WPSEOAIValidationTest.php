<?php

/**
 * Class WPSEOAIValidationTest
 *
 * @package WPSEOAI
 */
class WPSEOAIValidationTest extends \PHPUnit\Framework\TestCase {

	public const PATTERN_TEST_COMMENT = '/^<!--wpseoai-[a-z]+-[0-9.]{10,}+-->/i';

	public function test_patterns() {
		$valid_secret = 'a0b1c2d3e4f5g6h7i8j9k0l1m2n3o4p5';
		$this->assertMatchesRegularExpression(WPSEOAI::PATTERN_SECRET, $valid_secret);

		$valid_subscription_id = 'A0B1C2D3E4F5G6H7I8J9';
		$this->assertMatchesRegularExpression(WPSEOAI::PATTERN_SUBSCRIPTION_ID, $valid_subscription_id);

	}
}
