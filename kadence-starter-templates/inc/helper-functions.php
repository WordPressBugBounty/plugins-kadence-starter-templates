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

use function KadenceWP\KadenceStarterTemplates\StellarWP\Uplink\build_auth_url;
use function KadenceWP\KadenceStarterTemplates\StellarWP\Uplink\get_authorization_token;
use function KadenceWP\KadenceStarterTemplates\StellarWP\Uplink\get_disconnect_url;
use function KadenceWP\KadenceStarterTemplates\StellarWP\Uplink\get_license_domain;
use function KadenceWP\KadenceStarterTemplates\StellarWP\Uplink\get_license_key;
use function KadenceWP\KadenceStarterTemplates\StellarWP\Uplink\get_original_domain;
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
 * Returns the Uplink slug used for the legacy AI authorization flow on this site.
 *
 * Mirrors the slug-selection logic used when the auth flow originally
 * issued the token, so runtime checks (token retrieval, license validation,
 * disconnect URL) all align with what was registered server-side. The
 * `kadence-blocks-auth-slug` filter is honoured (Kadence Blocks Pro hooks
 * it to force `kadence-blocks-pro`).
 *
 * @since 2.3.2
 *
 * @return string
 */
function kadence_starter_templates_get_ai_auth_slug(): string {
	$slug = class_exists( '\KadenceWP\KadenceBlocks\App' ) ? 'kadence-blocks' : 'kadence-starter-templates';

	return (string) apply_filters( 'kadence-blocks-auth-slug', $slug );
}

/**
 * Returns the stored Uplink auth token for the AI flow.
 *
 * Routes through Kadence Blocks' Uplink container when KB is active so
 * that the slugs registered there (`kadence-blocks`, `kadence-blocks-pro`)
 * resolve to a Token_Manager. Otherwise uses Starter Templates' own
 * container. Returns an empty string when no token is stored.
 *
 * @since 2.3.2
 *
 * @return string
 */
function kadence_starter_templates_get_ai_auth_token(): string {
	$slug = kadence_starter_templates_get_ai_auth_slug();

	if ( class_exists( '\KadenceWP\KadenceBlocks\App' ) ) {
		$token = \KadenceWP\KadenceBlocks\StellarWP\Uplink\get_authorization_token( $slug );
	} else {
		$token = get_authorization_token( $slug );
	}

	return (string) ( $token ?? '' );
}

/**
 * Returns the stored Uplink license key associated with the AI auth slug.
 *
 * This is the slug-matched key that the server has on file for the token,
 * not the broader cascade from `kadence_starter_templates_get_license_data()`.
 *
 * @since 2.3.2
 *
 * @return string
 */
function kadence_starter_templates_get_ai_license_key(): string {
	$slug = kadence_starter_templates_get_ai_auth_slug();

	if ( class_exists( '\KadenceWP\KadenceBlocks\App' ) ) {
		return (string) \KadenceWP\KadenceBlocks\StellarWP\Uplink\get_license_key( $slug );
	}

	return (string) get_license_key( $slug );
}

/**
 * Builds the "Activate Kadence AI" URL.
 *
 * This is where the auth token is originally created — the user is sent to
 * the discovered origin, completes the round-trip, and the callback writes
 * the token + license back to wp_options. Uses the same slug + container
 * the runtime auth check uses so registration and validation stay aligned.
 *
 * @since 2.3.2
 *
 * @return string
 */
function kadence_starter_templates_get_ai_auth_url(): string {
	$slug = kadence_starter_templates_get_ai_auth_slug();

	if ( class_exists( '\KadenceWP\KadenceBlocks\App' ) ) {
		return \KadenceWP\KadenceBlocks\StellarWP\Uplink\build_auth_url( $slug, get_license_domain() );
	}

	return build_auth_url( $slug, get_license_domain() );
}

/**
 * Builds the URL used to disconnect (revoke) the stored AI auth token.
 *
 * @since 2.3.2
 *
 * @return string
 */
function kadence_starter_templates_get_ai_disconnect_url(): string {
	$slug = kadence_starter_templates_get_ai_auth_slug();

	if ( class_exists( '\KadenceWP\KadenceBlocks\App' ) ) {
		return \KadenceWP\KadenceBlocks\StellarWP\Uplink\get_disconnect_url( $slug );
	}

	return get_disconnect_url( $slug );
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
	$license_key = kadence_starter_templates_get_ai_license_key();

	if ( empty( $license_key ) ) {
		return false;
	}

	return is_authorized(
		$license_key,
		kadence_starter_templates_get_ai_auth_slug(),
		kadence_starter_templates_get_ai_auth_token(),
		get_license_domain()
	);
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
