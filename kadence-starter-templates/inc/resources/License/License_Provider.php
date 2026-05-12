<?php declare( strict_types=1 );

namespace KadenceWP\KadenceStarterTemplates\License;

use KadenceWP\KadenceStarterTemplates\StellarWP\ProphecyMonorepo\Container\Contracts\Provider;

/**
 * @since 2.3.0
 */
final class License_Provider extends Provider {

	/**
	 * @since 2.3.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'kadence_starter_templates_disable_ai', [ $this, 'is_ai_disabled' ] );
	}

	/**
	 * Disables Kadence AI if not authorized as a legacy license.
	 *
	 * @since 2.3.0
	 *
	 * @param bool $disabled Whether AI is already disabled.
	 *
	 * @return bool
	 */
	public function is_ai_disabled( bool $disabled ): bool {
		if ( $disabled ) {
			return true;
		}

		return ! kadence_starter_templates_is_legacy_license_authorized();
	}

}
