<?php
/**
 * SlotNova Main Public API Facade.
 *
 * All extensions MUST communicate with SlotNova core strictly using this facade.
 *
 * @package SlotNova\Booking\ExtensionManager\API
 */

namespace SlotNova\Booking\ExtensionManager\API;

use SlotNova\Booking\ExtensionManager\Contracts\BookingServiceInterface;
use SlotNova\Booking\ExtensionManager\Contracts\StaffServiceInterface;
use SlotNova\Booking\ExtensionManager\Contracts\ServiceServiceInterface;
use SlotNova\Booking\ExtensionManager\Contracts\CalendarServiceInterface;
use SlotNova\Booking\ExtensionManager\Contracts\EventBusInterface;
use SlotNova\Booking\ExtensionManager\Contracts\ExtensionRegistryInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SlotNovaApi {

	private BookingServiceInterface $bookings;
	private StaffServiceInterface $staff;
	private ServiceServiceInterface $services;
	private CalendarServiceInterface $calendar;
	private EventBusInterface $events;
	private ExtensionRegistryInterface $extensions;

	public function __construct(
		BookingServiceInterface $bookings,
		StaffServiceInterface $staff,
		ServiceServiceInterface $services,
		CalendarServiceInterface $calendar,
		EventBusInterface $events,
		ExtensionRegistryInterface $extensions
	) {
		$this->bookings   = $bookings;
		$this->staff      = $staff;
		$this->services   = $services;
		$this->calendar   = $calendar;
		$this->events     = $events;
		$this->extensions = $extensions;
	}

	public function bookings(): BookingServiceInterface {
		return $this->bookings;
	}

	public function staff(): StaffServiceInterface {
		return $this->staff;
	}

	public function services(): ServiceServiceInterface {
		return $this->services;
	}

	public function calendar(): CalendarServiceInterface {
		return $this->calendar;
	}

	public function events(): EventBusInterface {
		return $this->events;
	}

	public function hooks(): EventBusInterface {
		return $this->events;
	}

	public function extensions(): ExtensionRegistryInterface {
		return $this->extensions;
	}
}
