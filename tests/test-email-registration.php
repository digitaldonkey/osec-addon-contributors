<?php
/**
 * Tests for the email label on wp-login.php and the registration without username.
 *
 * @package Osec\Addon\Contributors
 */

use Osec\Addon\Contributors\Admin\EmailLogin;
use Osec\Addon\Contributors\Admin\EmailRegistration;

require_once __DIR__ . '/class-testcase.php';

/**
 * EmailLogin and EmailRegistration.
 */
class Test_Email_Registration extends Osec_Addon_Contributors_TestCase {

	/**
	 * Resets the request.
	 */
	public function tear_down() {
		unset( $_SERVER['REQUEST_METHOD'], $_POST['user_login'] );
		remove_filter( 'gettext', [ EmailLogin::class, 'email_label' ], 20 );
		parent::tear_down();
	}

	/**
	 * The login label filter is added on wp-login.php only.
	 */
	public function test_label_filter_only_on_login_page() {
		$this->assertFalse( has_filter( 'gettext', [ EmailLogin::class, 'email_label' ] ) );
		$this->assertSame( 10, has_action( 'login_init', [ EmailLogin::class, 'on_login_page' ] ) );
		EmailLogin::on_login_page();
		$this->assertSame( 20, has_filter( 'gettext', [ EmailLogin::class, 'email_label' ] ) );
	}

	/**
	 * Core's label becomes "Email", other texts and domains stay.
	 */
	public function test_label() {
		$this->assertSame( 'Email', EmailLogin::email_label( 'x', EmailLogin::LABEL, 'default' ) );
		$this->assertSame( 'x', EmailLogin::email_label( 'x', EmailLogin::LABEL, 'other' ) );
		$this->assertSame( 'x', EmailLogin::email_label( 'x', 'Password', 'default' ) );
	}

	/**
	 * A registration gets a random login name, whatever was sent as username.
	 */
	public function test_registration_ignores_sent_username() {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['user_login']       = 'chosen-name';

		do_action( 'login_form_register' );
		// phpcs:ignore WordPress.Security -- the test reads the value core would read.
		$user_id = register_new_user( wp_unslash( $_POST['user_login'] ), 'new@example.com' );

		$this->assertIsInt( $user_id );
		$this->assertMatchesRegularExpression( '/^[a-z0-9]{8}$/', get_userdata( $user_id )->user_login );
	}

	/**
	 * A GET request (the form being shown) leaves the request alone.
	 */
	public function test_get_request_unchanged() {
		$_SERVER['REQUEST_METHOD'] = 'GET';
		EmailRegistration::set_login_name();
		$this->assertArrayNotHasKey( 'user_login', $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	/**
	 * A random name that is taken gets a number.
	 */
	public function test_taken_name_gets_number() {
		$fixed = static fn() => 'AbCdEfGh';
		add_filter( 'random_password', $fixed );
		self::factory()->user->create( [ 'user_login' => 'abcdefgh' ] );
		self::factory()->user->create( [ 'user_login' => 'abcdefgh-2' ] );

		$this->assertSame( 'abcdefgh-3', EmailRegistration::login_name() );
		remove_filter( 'random_password', $fixed );
	}

	/**
	 * The CSS hides the username row of the registration form.
	 */
	public function test_css() {
		$this->assertStringContainsString( '#registerform p:has(> #user_login)', EmailRegistration::css() );
	}
}
