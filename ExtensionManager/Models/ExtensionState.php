<?php
/**
 * Extension State model.
 *
 * @package SlotNova\Booking\ExtensionManager\Models
 */

namespace SlotNova\Booking\ExtensionManager\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ExtensionState {

	public const STATUS_ACTIVE   = 'active';
	public const STATUS_INACTIVE = 'inactive';
	public const STATUS_ERROR    = 'error';

	private string $id;
	private string $name;
	private string $version;
	private string $status;
	private string $licenseKey;
	private string $licenseStatus;
	private string $installedPath;
	private string $installedAt;
	private string $updatedAt;
	private array $settings;

	public function __construct( array $data ) {
		$this->id            = sanitize_key( $data['id'] ?? '' );
		$this->name          = sanitize_text_field( $data['name'] ?? '' );
		$this->version       = sanitize_text_field( $data['version'] ?? '1.0.0' );
		$this->status        = sanitize_key( $data['status'] ?? self::STATUS_INACTIVE );
		$this->licenseKey    = sanitize_text_field( $data['license_key'] ?? '' );
		$this->licenseStatus = sanitize_key( $data['license_status'] ?? 'unlicensed' );
		$this->installedPath = sanitize_text_field( $data['installed_path'] ?? '' );
		$this->installedAt   = sanitize_text_field( $data['installed_at'] ?? current_time( 'mysql' ) );
		$this->updatedAt     = sanitize_text_field( $data['updated_at'] ?? current_time( 'mysql' ) );
		$this->settings      = is_array( $data['settings'] ?? null ) ? $data['settings'] : ( json_decode( $data['settings'] ?? '[]', true ) ?: [] );
	}

	public function getId(): string {
		return $this->id;
	}

	public function getName(): string {
		return $this->name;
	}

	public function getVersion(): string {
		return $this->version;
	}

	public function getStatus(): string {
		return $this->status;
	}

	public function isActive(): bool {
		return self::STATUS_ACTIVE === $this->status;
	}

	public function getLicenseKey(): string {
		return $this->licenseKey;
	}

	public function getLicenseStatus(): string {
		return $this->licenseStatus;
	}

	public function getInstalledPath(): string {
		return $this->installedPath;
	}

	public function getInstalledAt(): string {
		return $this->installedAt;
	}

	public function getUpdatedAt(): string {
		return $this->updatedAt;
	}

	public function getSettings(): array {
		return $this->settings;
	}

	public function toArray(): array {
		return [
			'id'             => $this->id,
			'name'           => $this->name,
			'version'        => $this->version,
			'status'         => $this->status,
			'license_key'    => $this->licenseKey,
			'license_status' => $this->licenseStatus,
			'installed_path' => $this->installedPath,
			'installed_at'   => $this->installedAt,
			'updated_at'     => $this->updatedAt,
			'settings'       => json_encode( $this->settings ),
		];
	}
}
