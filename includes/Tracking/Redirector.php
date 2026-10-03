<?php
/**
 * Click redirector: affiliate link → hub logs token → store product page.
 *
 * @package CoSellHive
 */

namespace CoSellHive\Tracking;

use CoSellHive\Core\Plugin;
use CoSellHive\Repository\ClickRepository;
use CoSellHive\Repository\ListingRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns the `/go/cs-hive/<ref>/` route. Tokens are minted at click time
 * (never at render time) so cached pages can't share attribution.
 */
class Redirector {

	const QUERY_VAR = 'cosell_go';

	/**
	 * Main plugin instance (hub client access).
	 *
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Main plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Register rewrite + handler.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'add_rewrite' ) );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_action( 'template_redirect', array( $this, 'handle' ) );
	}

	/**
	 * Add the go rewrite rule.
	 *
	 * @return void
	 */
	public function add_rewrite() {
		add_rewrite_rule( '^go/cs-hive/([^/]+)/?', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
	}

	/**
	 * Register the query var.
	 *
	 * @param array $vars Existing vars.
	 * @return array
	 */
	public function add_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;

		return $vars;
	}

	/**
	 * Build a shareable go URL for a listing ref + affiliate.
	 *
	 * @param string $ref          Listing ref (hub ID or `p-<product_id>`).
	 * @param int    $affiliate_id Affiliate user ID.
	 * @return string
	 */
	public static function build_url( $ref, $affiliate_id ) {
		/**
		 * Filter the go-link base. Defaults to the local redirector;
		 * swap to the hub `go.*` domain when it exists.
		 *
		 * @param string $base Base URL with trailing slash.
		 */
		$base = apply_filters( 'cosell_hive_go_base', home_url( '/go/cs-hive/' ) );

		return esc_url_raw( trailingslashit( $base ) . rawurlencode( $ref ) . '/?a=' . absint( $affiliate_id ) );
	}

	/**
	 * Handle go requests: resolve → mint token → redirect with `?cs_hive_token=`.
	 *
	 * @return void
	 */
	public function handle() {
		$ref = get_query_var( self::QUERY_VAR );

		if ( '' === $ref ) {
			return;
		}

		$ref          = sanitize_text_field( $ref );
		$affiliate_id = isset( $_GET['a'] ) ? absint( $_GET['a'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$product_id   = $this->resolve_product( $ref );

		if ( ! $product_id || ! function_exists( 'wc_get_product' ) ) {
			return;
		}

		$product = wc_get_product( $product_id );

		if ( ! $product || 'publish' !== $product->get_status() ) {
			return;
		}

		$response = $this->plugin->hub->track_click(
			array(
				'listing_id'   => $product_id,
				'affiliate_id' => $affiliate_id,
			)
		);

		$token = isset( $response['token'] ) ? sanitize_text_field( $response['token'] ) : '';

		if ( '' === $token ) {
			return;
		}

		$clicks = new ClickRepository();
		$clicks->record( $token, $product_id, $affiliate_id );

		$days = absint( cosell_hive_get_setting( 'attribution_days', 30 ) );

		setcookie(
			OrderReporter::COOKIE_NAME,
			$token,
			array(
				'expires'  => time() + $days * DAY_IN_SECONDS,
				'path'     => COOKIEPATH,
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);

		wp_safe_redirect( add_query_arg( 'cs_hive_token', rawurlencode( $token ), get_permalink( $product_id ) ), 302 );
		exit;
	}

	/**
	 * Resolve a ref to a live product ID.
	 *
	 * @param string $ref Listing ref.
	 * @return int
	 */
	private function resolve_product( $ref ) {
		if ( 0 === strpos( $ref, 'p-' ) ) {
			$product_id = absint( substr( $ref, 2 ) );
		} else {
			$repository = new ListingRepository();
			$row        = $repository->find_by_hub_id( $ref );
			$product_id = $row ? absint( $row->product_id ) : 0;
		}

		if ( ! $product_id ) {
			return 0;
		}

		$status = get_post_meta( $product_id, \CoSellHive\Modules\Store\ProductMeta::META_STATUS, true );

		return 'live' === $status ? $product_id : 0;
	}
}
