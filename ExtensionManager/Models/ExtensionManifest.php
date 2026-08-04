<?php
/**
 * Value Object representing an extension.json manifest.
 *
 * @package SlotNova\Booking\ExtensionManager\Models
 */

namespace SlotNova\Booking\ExtensionManager\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ExtensionManifest {

	private string $id;
	private string $name;
	private string $version;
	private string $requiresCore;
	private string $requiresPhp;
	private string $autoload;
	private string $namespace;
	private string $description;
	private string $path;
	private string $type;
	private string $price;
	private string $purchaseUrl;
	private string $demoUrl;
	private string $settingsUrl;

	public function __construct( array $data, string $path ) {
		$this->id           = sanitize_key( $data['id'] ?? '' );
		$this->name         = sanitize_text_field( $data['name'] ?? '' );
		$this->version      = sanitize_text_field( $data['version'] ?? '1.0.0' );
		$this->requiresCore = sanitize_text_field( $data['requires_core'] ?? '1.0.0' );
		$this->requiresPhp  = sanitize_text_field( $data['requires_php'] ?? '7.4' );
		$this->autoload     = sanitize_text_field( $data['autoload'] ?? 'bootstrap.php' );
		$this->namespace    = sanitize_text_field( $data['namespace'] ?? '' );
		$this->description  = sanitize_text_field( $data['description'] ?? '' );
		$this->type         = sanitize_key( $data['type'] ?? 'free' );
		$this->price        = sanitize_text_field( $data['price'] ?? 'Free' );
		$this->purchaseUrl  = function_exists( 'esc_url_raw' ) ? esc_url_raw( $data['purchase_url'] ?? ( $data['buy_url'] ?? '' ) ) : sanitize_text_field( $data['purchase_url'] ?? ( $data['buy_url'] ?? '' ) );
		$this->demoUrl      = function_exists( 'esc_url_raw' ) ? esc_url_raw( $data['demo_url'] ?? '' ) : sanitize_text_field( $data['demo_url'] ?? '' );
		$this->settingsUrl  = function_exists( 'esc_url_raw' ) ? esc_url_raw( $data['settings_url'] ?? '' ) : sanitize_text_field( $data['settings_url'] ?? '' );
		$this->path         = rtrim( wp_normalize_path( $path ), '/' );
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

	public function getRequiresCore(): string {
		return $this->requiresCore;
	}

	public function getRequiresPhp(): string {
		return $this->requiresPhp;
	}

	public function getAutoload(): string {
		return $this->autoload;
	}

	public function getNamespace(): string {
		return $this->namespace;
	}

	public function getDescription(): string {
		return $this->description;
	}

	public function getType(): string {
		return $this->type;
	}

	public function getPrice(): string {
		return $this->price;
	}

	public function getPurchaseUrl(): string {
		return $this->purchaseUrl;
	}

	public function getDemoUrl(): string {
		return $this->demoUrl;
	}

	public function getSettingsUrl(): string {
		return $this->settingsUrl;
	}

	public function getPath(): string {
		return $this->path;
	}

	public function getBootstrapPath(): string {
		return $this->path . '/' . $this->autoload;
	}
}
