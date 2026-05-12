<?php
/**
 * Kadence Blocks Helper Functions
 *
 * @since   1.8.0
 * @package Kadence Blocks
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use function KadenceWP\KadenceStarterTemplates\StellarWP\Uplink\get_license_key;
use function KadenceWP\KadenceStarterTemplates\StellarWP\Uplink\get_original_domain;
use function KadenceWP\KadenceStarterTemplates\StellarWP\Uplink\get_authorization_token;
use function KadenceWP\KadenceStarterTemplates\StellarWP\Uplink\get_license_domain;
use function KadenceWP\KadenceStarterTemplates\StellarWP\Uplink\is_authorized;

/**
 * Get the license data for the plugin.
 */
function kadence_starter_templates_get_license_data() {
	$data = [];
	if ( function_exists( 'kadence_blocks_get_current_license_data' ) ) {
		$data = kadence_blocks_get_current_license_data();
	}
	if ( empty( $data['key'] ) && function_exists( 'KadenceWP\KadencePro\StellarWP\Uplink\get_license_key' ) ) {
		$data = [
			'key'     => \KadenceWP\KadencePro\StellarWP\Uplink\get_license_key( 'kadence-theme-pro' ),
			'product' => 'kadence-theme-pro',
			'email'   => '',
		];
	}
	if ( empty( $data['key'] ) ) {
		$data = [
			'key'     => get_license_key( 'kadence-starter-templates' ),
			'product' => 'kadence-starter-templates',
			'email'   => '',
		];
	}
	$license_data = [
		'api_key'   => ( ! empty( $data['key'] ) ? $data['key'] : '' ),
		'api_email' => ( ! empty( $data['email'] ) ? $data['email'] : '' ), // Backwards compatibility with older licensing.
		'site_url'  => get_original_domain(),
		'product_slug' => ( ! empty( $data['product'] ) ? $data['product'] : 'kadence-starter-templates' ),
		'env'       => kadence_starter_templates_get_current_env(),
	];
	return $license_data;
}

/**
 * Check if a legacy (Uplink) Kadence Starter Templates license is authorized.
 *
 * AI features are not supported under Harbor licensing, so this function gates
 * AI-specific UI and functionality. Harbor-only customers and customers with no
 * license will return false.
 *
 * @since 2.3.0
 *
 * @return bool
 */
function kadence_starter_templates_is_legacy_license_authorized(): bool {
	$license_data = kadence_starter_templates_get_license_data();
	$license_key  = $license_data['api_key'];

	if ( ! empty( $license_key ) ) {
		$slug  = $license_data['product_slug'];
		$token = get_authorization_token( $slug );
		if ( is_authorized( $license_key, $slug, ( $token ?? '' ), get_license_domain() ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Returns true if AI features should be disabled.
 *
 * Checks in priority order:
 * 1. KADENCE_STARTER_TEMPLATES_AI_DISABLED constant (hard override).
 * 2. Legacy Uplink license — if authorized, AI is always available.
 * 3. Harbor/Liquid Web unified license (managed hosting without AI).
 * Falls back to false (AI enabled) when none of the above apply.
 *
 * @since 2.3.0
 *
 * @return bool
 */
function kadence_starter_templates_disable_ai(): bool {
	if ( defined( 'KADENCE_STARTER_TEMPLATES_AI_DISABLED' ) && KADENCE_STARTER_TEMPLATES_AI_DISABLED ) {
		return true;
	}

	return (bool) apply_filters( 'kadence_starter_templates_disable_ai', false );
}

/**
 * Get the current environment.
 */
function kadence_starter_templates_get_current_env() {
	if ( defined( 'STELLARWP_UPLINK_API_BASE_URL' ) ) {
		switch ( STELLARWP_UPLINK_API_BASE_URL ) {
			case 'https://licensing-dev.stellarwp.com':
				return 'dev';
			case 'https://licensing-staging.stellarwp.com':
				return 'staging';
		}
	}
	return '';
}
