<?php
/**
 * Class WPSEOAIWorkflowTest
 *
 * @package WPSEOAI
 */

class WPSEOAIWorkflowTest extends \PHPUnit\Framework\TestCase {

	public function setUp(): void {
		parent::setUp();

		update_option( 'wpseoai_subscription_id', $_ENV['WP_TESTS_WPSEOAI_SUBSCRIPTION_ID'] );
		update_option( 'wpseoai_secret', $_ENV['WP_TESTS_WPSEOAI_SUBSCRIPTION_SECRET'] );
		update_option( 'wpseoai_debug', $_ENV['WP_TESTS_WPSEOAI_DEBUG'] );

		update_option( 'siteurl', 'https://test.wpseo.ai/' );
		update_option( 'home', 'https://test.wpseo.ai/' );

		$this->post_id     = 0;
		$this->wp_error    = true;
		$this->post_data_1 = [
			'post_content' => '<h2 class="test">Lorem ipsum dolor sit amet.</h2>
<p>foo</p>
<p>bar</p>
<p>baz</p>
<p>qux</p>',
			'post_title'   => '',
			'post_excerpt' => '',
			'post_status'  => 'publish',
			'post_type'    => 'post',
			'post_name'    => '',
		];
	}

	public function test_workflow_submission_and_retrieval() {
		$pattern_html_comment = WPSEOAIValidationTest::PATTERN_TEST_COMMENT;
		$pattern_signature_id = WPSEOAI::PATTERN_SIGNATURE_ID;

		$this->post_id = wp_insert_post( $this->post_data_1, $this->wp_error );
		$this->assertGreaterThan( 0, $this->post_id );

		$result_submit = WPSEOAI::submit_post( $this->post_id );
//		var_dump($result_submit);

		$this->assertIsArray( $result_submit );

		$this->assertArrayHasKey( 'code', $result_submit );
		$this->assertEquals( 200, $result_submit['code'] );

		$this->assertArrayHasKey( 'response', $result_submit );

		$this->assertArrayHasKey( 'message', $result_submit['response'] );
		$this->assertEquals( 'Data stored successfully', $result_submit['response']['message'] );

		$this->assertArrayHasKey( 'signature', $result_submit['response'] );
		$this->assertMatchesRegularExpression( $pattern_signature_id, $result_submit['response']['signature'] );


//		$this->expectException(Exception::class);
		$result_lookup = WPSEOAI::retrieve( $result_submit['response']['signature'] );
//		var_dump($result_lookup);
		$this->assertIsArray( $result_lookup );

		$this->assertArrayHasKey( 'code', $result_lookup );
		$this->assertEquals( 204, $result_lookup['code'] );

		// TBC
		$this->assertArrayHasKey( 'response', $result_lookup );
		$this->assertNull( $result_lookup['response'] );

		// Allow backend time to handle submission, triggering AI processing, and generate response for retrieval
		sleep( 5 );

		// Expect real test submission response
		$result_lookup = WPSEOAI::retrieve( $result_submit['response']['signature'] );
//		var_dump($result_lookup);
		$this->assertIsArray( $result_lookup );

		$this->assertArrayHasKey( 'code', $result_lookup );
		$this->assertEquals( 200, $result_lookup['code'] );

		$this->assertArrayHasKey( 'response', $result_lookup );

		$this->assertArrayHasKey( 'message', $result_lookup['response'] );
		$this->assertEquals( 'Success', $result_lookup['response']['message'] );

		$this->assertArrayHasKey( 'signature', $result_submit['response'] );
		$this->assertMatchesRegularExpression( $pattern_signature_id, $result_submit['response']['signature'] );

		$this->assertArrayHasKey( 'payload', $result_lookup['response'] );
		$payload = json_decode( $result_lookup['response']['payload'], true );

		$payload_data = base64_decode( $payload['data'] );
		$this->assertJson( $payload_data );

		$data = json_decode( $payload_data, true );
		$this->assertArrayHasKey( 'post', $data );

		// Test for submission processing summary
		$this->assertArrayHasKey( 'summary', $data );
		$this->assertMatchesRegularExpression( $pattern_html_comment, $data['summary'] );

		$this->assertArrayHasKey( 'creditRemaining', $data );
		$this->assertGreaterThan(0, $data['creditRemaining'] );

		$this->assertArrayHasKey( 'creditUsed', $data );
		$this->assertEquals(1000, $data['creditUsed'] );

		// Test for post structure
		$post_data = $data['post'];
//		var_dump($post_data, "POST_DATA");
		$this->assertArrayHasKey( 'ID', $post_data );
		$this->assertIsNumeric( $post_data['ID'] );
		$this->assertGreaterThan( 0, $post_data['ID'] );
		$this->assertArrayHasKey( 'post_type', $post_data );
		$this->assertArrayHasKey( 'post_date', $post_data );
		$this->assertArrayHasKey( 'post_url', $post_data );
		$this->assertArrayHasKey( 'post_content', $post_data );
		$this->assertArrayHasKey( 'post_name', $post_data );

		// Test for test comments on response
		$this->assertMatchesRegularExpression( $pattern_html_comment, $post_data['post_content'] );
		$this->assertMatchesRegularExpression( $pattern_html_comment, $post_data['post_name'] );

		// TODO: Test audit trail record

		$this->assertArrayHasKey( 'post_id', $result_lookup );
		$this->assertArrayHasKey( 'audit_post_id', $result_lookup );
		$this->assertIsNumeric( $result_lookup['post_id'] );
		$this->assertGreaterThan( 0, $result_lookup['post_id'] );
		$this->assertEquals( $post_data['ID'], $result_lookup['post_id'] );

//		$result_lookup = WPSEOAI::retrieve($result_submit['response']['signature']);

		// TODO: test all post record existence is exact
	}

//	public function test_retrieve_and_revise_post() {
//		//
//	}
}
