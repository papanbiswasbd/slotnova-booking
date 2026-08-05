<?php
/**
 * Capacity Model.
 *
 * Value object representing slot capacity state.
 *
 * @package SlotNova\Extensions\GroupBooking\Models
 */

namespace SlotNova\Extensions\GroupBooking\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Capacity {
	public int $maxCapacity = 20;
	public int $minCapacity = 1;
	public int $bookedSeats = 0;
	public int $remainingSeats = 20;
	public bool $isFull = false;

	public function __construct( int $max = 20, int $min = 1, int $booked = 0 ) {
		$this->maxCapacity    = max( 1, $max );
		$this->minCapacity    = max( 1, $min );
		$this->bookedSeats    = max( 0, $booked );
		$this->remainingSeats = max( 0, $this->maxCapacity - $this->bookedSeats );
		$this->isFull         = $this->remainingSeats <= 0;
	}

	public function toArray(): array {
		return array(
			'max_capacity'     => $this->maxCapacity,
			'min_capacity'     => $this->minCapacity,
			'booked_seats'     => $this->bookedSeats,
			'remaining_seats'  => $this->remainingSeats,
			'is_full'          => $this->isFull,
		);
	}
}
