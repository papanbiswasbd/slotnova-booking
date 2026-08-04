<?php
/**
 * Repository for storing and retrieving Extension state from Database.
 *
 * @package SlotNova\Booking\ExtensionManager\Repositories
 */

namespace SlotNova\Booking\ExtensionManager\Repositories;

use SlotNova\Booking\ExtensionManager\Models\ExtensionState;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ExtensionRepository {

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'slotnova_extensions';
		$this->ensureTableExists();
	}

	/**
	 * Ensure the database table exists.
	 *
	 * @return void
	 */
	public function ensureTableExists(): void {
		global $wpdb;

		// Quick check if table exists
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $this->table ) ) === $this->table ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$this->table} (
			id varchar(64) NOT NULL,
			name varchar(128) NOT NULL,
			version varchar(32) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'inactive',
			license_key varchar(255) DEFAULT NULL,
			license_status varchar(32) DEFAULT 'unlicensed',
			installed_path varchar(512) NOT NULL,
			installed_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			settings longtext DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY idx_status (status)
		) {$charset_collate};";

		if ( file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			if ( function_exists( 'dbDelta' ) ) {
				dbDelta( $sql );
			}
		}

		// Direct fallback if dbDelta did not create the table
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $this->table ) ) !== $this->table ) {
			$wpdb->query( $sql );
		}
	}

	/**
	 * Get extension state by ID.
	 *
	 * @param string $id Extension ID.
	 * @return ExtensionState|null
	 */
	public function get( string $id ): ?ExtensionState {
		global $wpdb;
		$outputType = defined( 'ARRAY_A' ) ? ARRAY_A : 'ARRAY_A';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %s", $id ), $outputType );

		if ( ! $row ) {
			$altId = ( 0 === strpos( $id, 'slotnova-' ) ) ? substr( $id, 9 ) : 'slotnova-' . $id;
			$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %s", $altId ), $outputType );
		}

		if ( $row ) {
			return new ExtensionState( $row );
		}

		// Fallback to WordPress option check
		$optStatus = get_option( 'slotnova_ext_status_' . $id );
		if ( 'active' === $optStatus || 'inactive' === $optStatus ) {
			$cleanId       = ( 0 === strpos( $id, 'slotnova-' ) ) ? substr( $id, 9 ) : $id;
			$installedPath = SLOTNOVA_BOOKING_PATH . 'extensions/' . $cleanId;
			return new ExtensionState(
				[
					'id'             => $id,
					'name'           => ucwords( str_replace( [ '-', '_' ], ' ', $id ) ),
					'version'        => '1.0.0',
					'status'         => $optStatus,
					'installed_path' => $installedPath,
				]
			);
		}

		return null;
	}

	/**
	 * Get all installed extension states.
	 *
	 * @return array Array of ExtensionState objects.
	 */
	public function getAllInstalled(): array {
		global $wpdb;
		$outputType = defined( 'ARRAY_A' ) ? ARRAY_A : 'ARRAY_A';
		$rows = $wpdb->get_results( "SELECT * FROM {$this->table}", $outputType );
		$list = [];
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$state = new ExtensionState( $row );
				$path  = $state->getInstalledPath();
				if ( ! empty( $path ) && ( file_exists( $path . '/extension.json' ) || file_exists( $path . '/bootstrap.php' ) || is_dir( $path ) ) ) {
					$list[ $row['id'] ] = $state;
				} else {
					$wpdb->delete( $this->table, [ 'id' => $row['id'] ] );
				}
			}
		}

		// Merge WordPress Options dual persistence layer (only if directory exists on disk)
		$activeList = get_option( 'slotnova_active_extensions_list', array() );
		if ( is_array( $activeList ) ) {
			foreach ( $activeList as $id => $st ) {
				if ( ! isset( $list[ $id ] ) ) {
					$cleanId       = ( 0 === strpos( $id, 'slotnova-' ) ) ? substr( $id, 9 ) : $id;
					$bundledPath   = SLOTNOVA_BOOKING_PATH . 'extensions/' . $cleanId;
					$uploadPath    = Installer::getExtensionsStorageDir() . '/' . $cleanId;
					$validPath     = '';

					if ( is_dir( $bundledPath ) && file_exists( $bundledPath . '/extension.json' ) ) {
						$validPath = $bundledPath;
					} elseif ( is_dir( $uploadPath ) && file_exists( $uploadPath . '/extension.json' ) ) {
						$validPath = $uploadPath;
					}

					if ( ! empty( $validPath ) ) {
						$list[ $id ] = new ExtensionState(
							[
								'id'             => $id,
								'name'           => ucwords( str_replace( [ '-', '_' ], ' ', $id ) ),
								'version'        => '1.0.0',
								'status'         => $st,
								'installed_path' => $validPath,
							]
						);
					} else {
						unset( $activeList[ $id ] );
						delete_option( 'slotnova_ext_status_' . $id );
					}
				}
			}
			update_option( 'slotnova_active_extensions_list', $activeList );
		}

		// Merge local bundled extensions in extensions/ directory
		$bundledDir = SLOTNOVA_BOOKING_PATH . 'extensions';
		if ( is_dir( $bundledDir ) ) {
			$items = scandir( $bundledDir );
			if ( is_array( $items ) ) {
				foreach ( $items as $item ) {
					if ( '.' === $item || '..' === $item ) {
						continue;
					}
					$path = $bundledDir . '/' . $item;
					if ( is_dir( $path ) && file_exists( $path . '/extension.json' ) && ! isset( $list[ $item ] ) ) {
						$optStatus = get_option( 'slotnova_ext_status_' . $item, 'active' );
						$list[ $item ] = new ExtensionState(
							[
								'id'             => $item,
								'name'           => ucwords( str_replace( [ '-', '_' ], ' ', $item ) ),
								'version'        => '1.0.0',
								'status'         => $optStatus,
								'installed_path' => $path,
							]
						);
					}
				}
			}
		}

		return $list;
	}

	/**
	 * Get all active extension states.
	 *
	 * @return array Array of ExtensionState objects.
	 */
	public function getActive(): array {
		global $wpdb;
		$outputType = defined( 'ARRAY_A' ) ? ARRAY_A : 'ARRAY_A';
		$list       = [];

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE status = %s", ExtensionState::STATUS_ACTIVE ), $outputType );
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$state = new ExtensionState( $row );
				$path  = $state->getInstalledPath();
				if ( ! empty( $path ) && ( file_exists( $path . '/extension.json' ) || file_exists( $path . '/bootstrap.php' ) || is_dir( $path ) ) ) {
					$list[ $row['id'] ] = $state;
				} else {
					$wpdb->delete( $this->table, [ 'id' => $row['id'] ] );
				}
			}
		}

		// Also check WordPress Options dual persistence layer
		$activeList = get_option( 'slotnova_active_extensions_list', array() );
		if ( is_array( $activeList ) ) {
			foreach ( $activeList as $id => $st ) {
				if ( 'active' === $st && ! isset( $list[ $id ] ) ) {
					$cleanId     = ( 0 === strpos( $id, 'slotnova-' ) ) ? substr( $id, 9 ) : $id;
					$bundledPath = SLOTNOVA_BOOKING_PATH . 'extensions/' . $cleanId;
					$uploadPath  = Installer::getExtensionsStorageDir() . '/' . $cleanId;
					$validPath   = '';

					if ( is_dir( $bundledPath ) && file_exists( $bundledPath . '/extension.json' ) ) {
						$validPath = $bundledPath;
					} elseif ( is_dir( $uploadPath ) && file_exists( $uploadPath . '/extension.json' ) ) {
						$validPath = $uploadPath;
					}

					if ( ! empty( $validPath ) ) {
						$list[ $id ] = new ExtensionState(
							[
								'id'             => $id,
								'name'           => ucwords( str_replace( [ '-', '_' ], ' ', $id ) ),
								'version'        => '1.0.0',
								'status'         => 'active',
								'installed_path' => $validPath,
							]
						);
					} else {
						unset( $activeList[ $id ] );
						delete_option( 'slotnova_ext_status_' . $id );
					}
				}
			}
			update_option( 'slotnova_active_extensions_list', $activeList );
		}

		// Bundled extensions in extensions/ directory default to ACTIVE unless explicitly disabled
		$bundledDir = SLOTNOVA_BOOKING_PATH . 'extensions';
		if ( is_dir( $bundledDir ) ) {
			$items = scandir( $bundledDir );
			if ( is_array( $items ) ) {
				foreach ( $items as $item ) {
					if ( '.' === $item || '..' === $item ) {
						continue;
					}
					$path = $bundledDir . '/' . $item;
					if ( is_dir( $path ) && file_exists( $path . '/extension.json' ) ) {
						$optStatus = get_option( 'slotnova_ext_status_' . $item );
						if ( 'inactive' !== $optStatus && ! isset( $list[ $item ] ) ) {
							$list[ $item ] = new ExtensionState(
								[
									'id'             => $item,
									'name'           => ucwords( str_replace( [ '-', '_' ], ' ', $item ) ),
									'version'        => '1.0.0',
									'status'         => 'active',
									'installed_path' => $path,
								]
							);
							update_option( 'slotnova_ext_status_' . $item, 'active' );
						}
					}
				}
			}
		}

		return $list;
	}

	/**
	 * Check if extension is installed.
	 *
	 * @param string $id Extension ID.
	 * @return bool
	 */
	public function exists( string $id ): bool {
		return null !== $this->get( $id );
	}

	/**
	 * Check if extension is active.
	 *
	 * @param string $id Extension ID.
	 * @return bool
	 */
	public function isActive( string $id ): bool {
		$state = $this->get( $id );
		return $state ? $state->isActive() : false;
	}

	/**
	 * Save or update extension state.
	 *
	 * @param ExtensionState $state Extension state object.
	 * @return bool
	 */
	public function save( ExtensionState $state ): bool {
		global $wpdb;
		$this->ensureTableExists();

		$data = $state->toArray();

		if ( empty( $data['installed_at'] ) ) {
			$data['installed_at'] = current_time( 'mysql' );
		}
		$data['updated_at'] = current_time( 'mysql' );

		// Sync status with WordPress Options dual persistence layer
		$cleanId = ( 0 === strpos( $state->getId(), 'slotnova-' ) ) ? substr( $state->getId(), 9 ) : $state->getId();
		$altId   = ( 0 === strpos( $state->getId(), 'slotnova-' ) ) ? $state->getId() : 'slotnova-' . $state->getId();

		update_option( 'slotnova_ext_status_' . $cleanId, $state->getStatus() );
		update_option( 'slotnova_ext_status_' . $altId, $state->getStatus() );

		$activeList = get_option( 'slotnova_active_extensions_list', array() );
		if ( ! is_array( $activeList ) ) {
			$activeList = array();
		}
		if ( ExtensionState::STATUS_ACTIVE === $state->getStatus() ) {
			$activeList[ $cleanId ] = 'active';
			$activeList[ $altId ]   = 'active';
		} else {
			unset( $activeList[ $cleanId ], $activeList[ $altId ] );
		}
		update_option( 'slotnova_active_extensions_list', $activeList );

		$result = $wpdb->replace( $this->table, $data );

		if ( false === $result ) {
			error_log( "[SlotNova ExtensionRepository] DB Replace failed for '{$state->getId()}': " . $wpdb->last_error );
			$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->table} WHERE id = %s", $state->getId() ) );
			if ( $existing ) {
				$wpdb->update( $this->table, $data, [ 'id' => $state->getId() ] );
			} else {
				$wpdb->insert( $this->table, $data );
			}
		}

		return true;
	}

	/**
	 * Update status of an extension.
	 *
	 * @param string $id            Extension ID.
	 * @param string $status        New status string.
	 * @param string $installedPath Optional path if creating entry.
	 * @return bool
	 */
	public function updateStatus( string $id, string $status, string $installedPath = '' ): bool {
		$cleanId = ( 0 === strpos( $id, 'slotnova-' ) ) ? substr( $id, 9 ) : $id;
		$altId   = ( 0 === strpos( $id, 'slotnova-' ) ) ? $id : 'slotnova-' . $id;

		// Dual persistence: WordPress Option API
		$activeList = get_option( 'slotnova_active_extensions_list', array() );
		if ( ! is_array( $activeList ) ) {
			$activeList = array();
		}
		if ( 'active' === $status ) {
			$activeList[ $cleanId ] = 'active';
			$activeList[ $altId ]   = 'active';
			update_option( 'slotnova_ext_status_' . $cleanId, 'active' );
			update_option( 'slotnova_ext_status_' . $altId, 'active' );
		} else {
			unset( $activeList[ $cleanId ], $activeList[ $altId ] );
			update_option( 'slotnova_ext_status_' . $cleanId, 'inactive' );
			update_option( 'slotnova_ext_status_' . $altId, 'inactive' );
		}
		update_option( 'slotnova_active_extensions_list', $activeList );

		// Database Table Persistence
		$state = $this->get( $cleanId );
		if ( ! $state ) {
			$state = $this->get( $id );
		}

		if ( ! $state ) {
			if ( empty( $installedPath ) ) {
				$installedPath = SLOTNOVA_BOOKING_PATH . 'extensions/' . $cleanId;
			}
			$state = new ExtensionState(
				[
					'id'             => $cleanId,
					'name'           => ucwords( str_replace( [ '-', '_' ], ' ', $cleanId ) ),
					'version'        => '1.0.0',
					'status'         => $status,
					'installed_path' => $installedPath,
				]
			);
		} else {
			$data           = $state->toArray();
			$data['status'] = $status;
			$state          = new ExtensionState( $data );
		}

		$this->save( $state );

		$altData       = $state->toArray();
		$altData['id'] = $altId;
		$altState      = new ExtensionState( $altData );
		$this->save( $altState );

		return true;
	}

	/**
	 * Delete extension state from database and options.
	 *
	 * @param string $id Extension ID.
	 * @return bool
	 */
	public function delete( string $id ): bool {
		global $wpdb;
		$cleanId = ( 0 === strpos( $id, 'slotnova-' ) ) ? substr( $id, 9 ) : $id;
		$altId   = ( 0 === strpos( $id, 'slotnova-' ) ) ? $id : 'slotnova-' . $id;

		$wpdb->delete( $this->table, [ 'id' => $cleanId ] );
		$wpdb->delete( $this->table, [ 'id' => $altId ] );
		$wpdb->delete( $this->table, [ 'id' => $id ] );

		$activeList = get_option( 'slotnova_active_extensions_list', array() );
		if ( is_array( $activeList ) ) {
			unset( $activeList[ $cleanId ], $activeList[ $altId ], $activeList[ $id ] );
			update_option( 'slotnova_active_extensions_list', $activeList );
		}

		delete_option( 'slotnova_ext_status_' . $cleanId );
		delete_option( 'slotnova_ext_status_' . $altId );
		delete_option( 'slotnova_ext_status_' . $id );

		return true;
	}
}
