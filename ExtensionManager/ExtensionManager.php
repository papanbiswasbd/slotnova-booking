<?php
/**
 * Main Extension Manager Engine.
 *
 * Scans, validates, registers, loads, updates, and manages extensions.
 *
 * @package SlotNova\Booking\ExtensionManager
 */

namespace SlotNova\Booking\ExtensionManager;

use SlotNova\Booking\ExtensionManager\Contracts\ExtensionManagerInterface;
use SlotNova\Booking\ExtensionManager\Contracts\ExtensionInterface;
use SlotNova\Booking\ExtensionManager\Container\Container;
use SlotNova\Booking\ExtensionManager\Repositories\ExtensionRepository;
use SlotNova\Booking\ExtensionManager\Services\Scanner;
use SlotNova\Booking\ExtensionManager\Services\Loader;
use SlotNova\Booking\ExtensionManager\Services\LifecycleManager;
use SlotNova\Booking\ExtensionManager\Services\Installer;
use SlotNova\Booking\ExtensionManager\Services\Updater;
use SlotNova\Booking\ExtensionManager\Services\ManifestValidator;
use SlotNova\Booking\ExtensionManager\Services\ChecksumVerifier;
use SlotNova\Booking\ExtensionManager\API\SlotNovaApi;
use SlotNova\Booking\ExtensionManager\API\Services\BookingService;
use SlotNova\Booking\ExtensionManager\API\Services\StaffService;
use SlotNova\Booking\ExtensionManager\API\Services\ServiceService;
use SlotNova\Booking\ExtensionManager\API\Services\CalendarService;
use SlotNova\Booking\ExtensionManager\API\Services\EventBus;
use SlotNova\Booking\ExtensionManager\API\Services\ExtensionRegistry;
use SlotNova\Booking\ExtensionManager\Contracts\BookingServiceInterface;
use SlotNova\Booking\ExtensionManager\Contracts\StaffServiceInterface;
use SlotNova\Booking\ExtensionManager\Contracts\ServiceServiceInterface;
use SlotNova\Booking\ExtensionManager\Contracts\CalendarServiceInterface;
use SlotNova\Booking\ExtensionManager\Contracts\EventBusInterface;
use SlotNova\Booking\ExtensionManager\Contracts\ExtensionRegistryInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ExtensionManager implements ExtensionManagerInterface {

	private Container $container;
	private ExtensionRepository $repository;
	private Scanner $scanner;
	private Loader $loader;
	private LifecycleManager $lifecycle;
	private Installer $installer;
	private Updater $updater;

	/**
	 * Map of loaded Extension instances.
	 *
	 * @var array<string, ExtensionInterface>
	 */
	private array $loadedExtensions = [];

	public function __construct() {
		$this->container  = Container::getInstance();
		$this->repository = new ExtensionRepository();

		// Register DI Bindings
		$this->registerBindings();

		$validator              = new ManifestValidator();
		$checksumVerifier       = new ChecksumVerifier();
		$this->scanner          = new Scanner( $validator );
		$this->installer        = new Installer( $this->repository, $checksumVerifier, $validator );
		$this->loader           = $this->container->make( Loader::class );
		$this->lifecycle        = new LifecycleManager( $this->repository );
		$this->updater          = new Updater( $this->repository, $this->installer, $this->loader );
	}

	/**
	 * Register DI bindings for core container services.
	 *
	 * @return void
	 */
	private function registerBindings(): void {
		$this->container->singleton( ExtensionRepository::class, $this->repository );
		$this->container->singleton( BookingServiceInterface::class, BookingService::class );
		$this->container->singleton( StaffServiceInterface::class, StaffService::class );
		$this->container->singleton( ServiceServiceInterface::class, ServiceService::class );
		$this->container->singleton( CalendarServiceInterface::class, CalendarService::class );
		$this->container->singleton( EventBusInterface::class, EventBus::class );
		$this->container->singleton(
			ExtensionRegistryInterface::class,
			function ( Container $c ) {
				return new ExtensionRegistry( $c->make( ExtensionRepository::class ) );
			}
		);

		$this->container->singleton(
			SlotNovaApi::class,
			function ( Container $c ) {
				return new SlotNovaApi(
					$c->make( BookingServiceInterface::class ),
					$c->make( StaffServiceInterface::class ),
					$c->make( ServiceServiceInterface::class ),
					$c->make( CalendarServiceInterface::class ),
					$c->make( EventBusInterface::class ),
					$c->make( ExtensionRegistryInterface::class )
				);
			}
		);

		$this->container->singleton(
			Loader::class,
			function ( Container $c ) {
				return new Loader( $c->make( SlotNovaApi::class ) );
			}
		);
	}

	/**
	 * Boot the Extension Manager and load all active extensions.
	 *
	 * @return void
	 */
	public function boot(): void {
		$manifests    = $this->scan();
		$activeStates = $this->repository->getActive();
		$bootedIds    = [];

		foreach ( $activeStates as $id => $state ) {
			$targetId = $id;
			if ( ! isset( $manifests[ $targetId ] ) ) {
				$altId = ( 0 === strpos( $id, 'slotnova-' ) ) ? substr( $id, 9 ) : 'slotnova-' . $id;
				if ( isset( $manifests[ $altId ] ) ) {
					$targetId = $altId;
				}
			}

			$cleanId = ( 0 === strpos( $targetId, 'slotnova-' ) ) ? substr( $targetId, 9 ) : $targetId;
			if ( isset( $bootedIds[ $cleanId ] ) ) {
				continue;
			}

			if ( isset( $manifests[ $targetId ] ) ) {
				try {
					$instance                            = $this->loader->load( $manifests[ $targetId ] );
					$this->loadedExtensions[ $targetId ] = $instance;
					$this->loadedExtensions[ $id ]       = $instance;
					$this->loadedExtensions[ $cleanId ]  = $instance;
					$bootedIds[ $cleanId ]               = true;
				} catch ( \Throwable $e ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( "[SlotNova ExtensionManager] Failed to load active extension '{$id}': " . $e->getMessage() );
					$this->repository->updateStatus( $id, 'error' );
				}
			}
		}

		do_action( 'slotnova_extensions_loaded', $this->loadedExtensions );
	}

	/**
	 * Scan configured directories for extensions.
	 *
	 * @return array Array of ExtensionManifest objects.
	 */
	public function scan(): array {
		$dirs = [
			SLOTNOVA_BOOKING_PATH . 'extensions',
			Installer::getExtensionsStorageDir(),
		];
		return $this->scanner->scanDirectories( $dirs );
	}

	public function install( string $extensionId, string $licenseKey = '' ): bool {
		return $this->installer->installFromRemote( $extensionId, $licenseKey );
	}

	public function activate( string $extensionId ): bool {
		$cleanId = ( 0 === strpos( $extensionId, 'slotnova-' ) ) ? substr( $extensionId, 9 ) : $extensionId;
		if ( isset( $this->loadedExtensions[ $cleanId ] ) ) {
			return true;
		}

		$success = $this->lifecycle->activate( $extensionId );
		if ( $success ) {
			$manifests = $this->scan();
			$targetId  = $extensionId;

			if ( ! isset( $manifests[ $targetId ] ) ) {
				$altId = ( 0 === strpos( $extensionId, 'slotnova-' ) ) ? substr( $extensionId, 9 ) : 'slotnova-' . $extensionId;
				if ( isset( $manifests[ $altId ] ) ) {
					$targetId = $altId;
				}
			}

			if ( isset( $manifests[ $targetId ] ) ) {
				try {
					$instance                               = $this->loader->load( $manifests[ $targetId ] );
					$instance->activate();
					$this->loadedExtensions[ $targetId ]   = $instance;
					$this->loadedExtensions[ $extensionId ] = $instance;
					$this->loadedExtensions[ $cleanId ]    = $instance;
				} catch ( \Throwable $e ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( "[SlotNova ExtensionManager] Failed to activate extension '{$extensionId}': " . $e->getMessage() );
				}
			}
		}
		return $success;
	}

	public function deactivate( string $extensionId ): bool {
		$altId = ( 0 === strpos( $extensionId, 'slotnova-' ) ) ? substr( $extensionId, 9 ) : 'slotnova-' . $extensionId;
		if ( isset( $this->loadedExtensions[ $extensionId ] ) ) {
			$this->loadedExtensions[ $extensionId ]->deactivate();
			unset( $this->loadedExtensions[ $extensionId ] );
		}
		if ( isset( $this->loadedExtensions[ $altId ] ) ) {
			$this->loadedExtensions[ $altId ]->deactivate();
			unset( $this->loadedExtensions[ $altId ] );
		}
		return $this->lifecycle->deactivate( $extensionId );
	}

	public function uninstall( string $extensionId ): bool {
		$cleanId = ( 0 === strpos( $extensionId, 'slotnova-' ) ) ? substr( $extensionId, 9 ) : $extensionId;
		$altId   = ( 0 === strpos( $extensionId, 'slotnova-' ) ) ? $extensionId : 'slotnova-' . $extensionId;

		if ( isset( $this->loadedExtensions[ $cleanId ] ) ) {
			try {
				$this->loadedExtensions[ $cleanId ]->deactivate();
				$this->loadedExtensions[ $cleanId ]->uninstall();
			} catch ( \Throwable $e ) {}
			unset( $this->loadedExtensions[ $cleanId ] );
		}
		if ( isset( $this->loadedExtensions[ $altId ] ) ) {
			try {
				$this->loadedExtensions[ $altId ]->deactivate();
				$this->loadedExtensions[ $altId ]->uninstall();
			} catch ( \Throwable $e ) {}
			unset( $this->loadedExtensions[ $altId ] );
		}

		$this->repository->delete( $cleanId );
		$this->repository->delete( $altId );

		$uploadPath = Installer::getExtensionsStorageDir() . '/' . $cleanId;
		if ( is_dir( $uploadPath ) ) {
			$this->recursiveRmdir( $uploadPath );
		}

		$bundledPath = SLOTNOVA_BOOKING_PATH . 'extensions/' . $cleanId;
		if ( is_dir( $bundledPath ) ) {
			$this->recursiveRmdir( $bundledPath );
		}

		return $this->lifecycle->uninstall( $extensionId );
	}

	private function recursiveRmdir( string $dir ): bool {
		if ( ! is_dir( $dir ) ) {
			return false;
		}
		$files = array_diff( scandir( $dir ), [ '.', '..' ] );
		foreach ( $files as $file ) {
			$path = $dir . '/' . $file;
			if ( is_dir( $path ) ) {
				$this->recursiveRmdir( $path );
			} else {
				if ( function_exists( 'wp_delete_file' ) ) {
					wp_delete_file( $path );
				} else {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
					@unlink( $path );
				}
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		return @rmdir( $dir );
	}

	public function update( string $extensionId ): bool {
		return $this->updater->update( $extensionId );
	}

	/**
	 * Check if an extension is currently loaded.
	 *
	 * @param string $extensionId Extension ID.
	 * @return bool
	 */
	public function isLoaded( string $extensionId ): bool {
		$altId = ( 0 === strpos( $extensionId, 'slotnova-' ) ) ? substr( $extensionId, 9 ) : 'slotnova-' . $extensionId;
		return isset( $this->loadedExtensions[ $extensionId ] ) || isset( $this->loadedExtensions[ $altId ] );
	}

	/**
	 * Get loaded extension instances.
	 *
	 * @return array<string, ExtensionInterface>
	 */
	public function getLoadedExtensions(): array {
		return $this->loadedExtensions;
	}
}
