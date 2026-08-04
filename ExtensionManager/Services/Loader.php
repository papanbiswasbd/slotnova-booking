<?php
/**
 * Extension Loader Service.
 * Registers dynamic PSR-4 autoloaders and boots extensions safely in isolated scope.
 *
 * @package SlotNova\Booking\ExtensionManager\Services
 */

namespace SlotNova\Booking\ExtensionManager\Services;

use SlotNova\Booking\ExtensionManager\Models\ExtensionManifest;
use SlotNova\Booking\ExtensionManager\Contracts\ExtensionInterface;
use SlotNova\Booking\ExtensionManager\Contracts\ExtensionException;
use SlotNova\Booking\ExtensionManager\API\SlotNovaApi;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loader {

	private SlotNovaApi $api;

	public function __construct( SlotNovaApi $api ) {
		$this->api = $api;
	}

	/**
	 * Load and boot an extension.
	 *
	 * @param ExtensionManifest $manifest Extension manifest.
	 * @return ExtensionInterface Loaded extension instance.
	 * @throws \Exception
	 */
	public function load( ExtensionManifest $manifest ): ExtensionInterface {
		$this->registerAutoloader( $manifest );

		$bootstrapPath = $manifest->getBootstrapPath();
		if ( ! file_exists( $bootstrapPath ) ) {
			throw new \Exception( esc_html( sprintf( 'Bootstrap file not found for extension %s at: %s', $manifest->getId(), $bootstrapPath ) ) );
		}

		$instance = $this->isolatedRequire( $bootstrapPath );

		if ( ! $instance instanceof ExtensionInterface ) {
			throw new \Exception( esc_html( sprintf( 'Bootstrap file for extension %s must return an instance of ExtensionInterface.', $manifest->getId() ) ) );
		}

		// Boot extension passing public API facade
		$instance->boot( $this->api );

		return $instance;
	}

	/**
	 * Register dynamic PSR-4 autoloader for an extension.
	 *
	 * @param ExtensionManifest $manifest
	 * @return void
	 */
	private function registerAutoloader( ExtensionManifest $manifest ): void {
		$namespace = $manifest->getNamespace();
		if ( empty( $namespace ) ) {
			return;
		}

		$srcDir = rtrim( $manifest->getPath(), '/' ) . '/src/';
		if ( ! is_dir( $srcDir ) ) {
			return;
		}

		$prefix = ltrim( $namespace, '\\' );

		spl_autoload_register(
			function ( string $class ) use ( $prefix, $srcDir ) {
				$class = ltrim( $class, '\\' );
				if ( 0 !== strpos( $class, $prefix ) ) {
					return;
				}

				$relativeClass = substr( $class, strlen( $prefix ) );
				$file          = $srcDir . str_replace( '\\', '/', $relativeClass ) . '.php';

				if ( file_exists( $file ) ) {
					require_once $file;
				}
			}
		);
	}

	/**
	 * Require bootstrap.php inside isolated function scope.
	 *
	 * @param string $bootstrapPath Absolute path to bootstrap file.
	 * @return mixed
	 */
	private function isolatedRequire( string $bootstrapPath ) {
		return require $bootstrapPath;
	}
}
