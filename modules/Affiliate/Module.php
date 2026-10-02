<?php
/**
 * Affiliate module — discovery feed, tracked links, embeds.
 *
 * @package CoSellHive
 */

namespace CoSellHive\Modules\Affiliate;

use CoSellHive\Core\Plugin;
use CoSellHive\Shortcodes\ProductCard;
use CoSellHive\Tracking\Redirector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires the affiliate-side feature set.
 */
class Module {

	/**
	 * Main plugin instance.
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

		$feed = new FeedController();
		$feed->register();

		$links = new LinksController( $plugin );
		$links->register();

		$card = new ProductCard();
		$card->register();

		$redirector = new Redirector( $plugin );
		$redirector->register();
	}
}
