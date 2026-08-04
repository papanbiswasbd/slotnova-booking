<?php
/**
 * Dependency Injection Service Container implementation.
 *
 * @package SlotNova\Booking\ExtensionManager\Container
 */

namespace SlotNova\Booking\ExtensionManager\Container;

use SlotNova\Booking\ExtensionManager\Contracts\ContainerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Container implements ContainerInterface {

	/**
	 * Container singleton instance.
	 *
	 * @var Container|null
	 */
	private static ?Container $instance = null;

	/**
	 * Registered bindings.
	 *
	 * @var array
	 */
	private array $bindings = [];

	/**
	 * Resolved singleton instances.
	 *
	 * @var array
	 */
	private array $instances = [];

	/**
	 * Get global container instance.
	 *
	 * @return Container
	 */
	public static function getInstance(): Container {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Bind an abstract type to concrete implementation.
	 *
	 * @param string $abstract  Abstract key/interface.
	 * @param mixed  $concrete  Concrete class name, instance, or Closure.
	 * @param bool   $singleton Whether to retain instance.
	 * @return void
	 */
	public function bind( string $abstract, $concrete = null, bool $singleton = false ): void {
		if ( null === $concrete ) {
			$concrete = $abstract;
		}
		$this->bindings[ $abstract ] = [
			'concrete'  => $concrete,
			'singleton' => $singleton,
		];
	}

	/**
	 * Bind a singleton type.
	 *
	 * @param string $abstract Abstract key/interface.
	 * @param mixed  $concrete Concrete class name, instance, or Closure.
	 * @return void
	 */
	public function singleton( string $abstract, $concrete = null ): void {
		$this->bind( $abstract, $concrete, true );
	}

	/**
	 * Resolve instance from container.
	 *
	 * @param string $abstract Abstract key/interface.
	 * @return mixed
	 * @throws \Exception If abstract cannot be resolved.
	 */
	public function make( string $abstract ) {
		if ( isset( $this->instances[ $abstract ] ) ) {
			return $this->instances[ $abstract ];
		}

		if ( ! isset( $this->bindings[ $abstract ] ) ) {
			if ( class_exists( $abstract ) ) {
				$object = $this->buildClass( $abstract );
				return $object;
			}
			throw new \Exception( "No binding found in SlotNova Container for: {$abstract}" );
		}

		$binding  = $this->bindings[ $abstract ];
		$concrete = $binding['concrete'];

		if ( $concrete instanceof \Closure ) {
			$object = $concrete( $this );
		} elseif ( is_string( $concrete ) && class_exists( $concrete ) ) {
			$object = $this->buildClass( $concrete );
		} elseif ( is_object( $concrete ) ) {
			$object = $concrete;
		} else {
			throw new \Exception( "Invalid binding concrete for: {$abstract}" );
		}

		if ( $binding['singleton'] ) {
			$this->instances[ $abstract ] = $object;
		}

		return $object;
	}

	/**
	 * Instantiate a class using Reflection to auto-wire constructor dependencies.
	 *
	 * @param string $className
	 * @return object
	 * @throws \Exception
	 */
	private function buildClass( string $className ) {
		$reflector = new \ReflectionClass( $className );
		if ( ! $reflector->isInstantiable() ) {
			throw new \Exception( "Class {$className} is not instantiable." );
		}

		$constructor = $reflector->getConstructor();
		if ( null === $constructor ) {
			return new $className();
		}

		$parameters   = $constructor->getParameters();
		$dependencies = [];

		foreach ( $parameters as $parameter ) {
			$type = $parameter->getType();
			if ( $type && ! $type->isBuiltin() ) {
				$typeName       = $type->getName();
				$dependencies[] = $this->make( $typeName );
			} elseif ( $parameter->isDefaultValueAvailable() ) {
				$dependencies[] = $parameter->getDefaultValue();
			} else {
				throw new \Exception( "Cannot resolve parameter '{$parameter->getName()}' in {$className}" );
			}
		}

		return $reflector->newInstanceArgs( $dependencies );
	}

	/**
	 * Check if binding exists.
	 *
	 * @param string $abstract Abstract key/interface.
	 * @return bool
	 */
	public function has( string $abstract ): bool {
		return isset( $this->bindings[ $abstract ] ) || isset( $this->instances[ $abstract ] );
	}
}
