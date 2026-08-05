<?php
/**
 * Participant Form Renderer.
 *
 * Renders frontend participant dropdown selector and dynamic attendee fields (Name, Gender, Email in same row).
 *
 * @package SlotNova\Extensions\GroupBooking\Frontend\Components
 */

namespace SlotNova\Extensions\GroupBooking\Frontend\Components;

use SlotNova\Extensions\GroupBooking\Helpers\GroupBookingHelper;
use SlotNova\Extensions\GroupBooking\Services\CapacityValidationService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ParticipantFormRenderer {

	private CapacityValidationService $capacityService;

	public function __construct( CapacityValidationService $capacityService ) {
		$this->capacityService = $capacityService;
	}

	/**
	 * Render participant quantity dropdown selector & dynamic attendee fields.
	 *
	 * @param mixed $productOrId Product ID or WC_Product object.
	 * @return void
	 */
	public function renderForm( $productOrId = 0 ): void {
		$productId = 0;
		if ( is_numeric( $productOrId ) ) {
			$productId = (int) $productOrId;
		} elseif ( is_object( $productOrId ) && method_exists( $productOrId, 'get_id' ) ) {
			$productId = (int) $productOrId->get_id();
		}

		if ( $productId <= 0 ) {
			global $product;
			if ( $product && is_object( $product ) && method_exists( $product, 'get_id' ) ) {
				$productId = (int) $product->get_id();
			}
		}

		if ( $productId <= 0 || ! GroupBookingHelper::isGroupBookingEnabled( $productId ) ) {
			return;
		}

		$maxCap = GroupBookingHelper::getMaxCapacity( $productId );
		?>
		<div class="slotnova-group-booking-container" style="display: none; margin: 16px 0;">

			<!-- Dropdown Selector: How many persons will book this? -->
			<div class="slotnova-qty-selector-wrapper" style="margin-bottom: 14px;">
				<label for="slotnova_group_quantity" style="display: block; font-weight: 700; font-size: 14px; color: #0f172a; margin-bottom: 6px;">
					<?php esc_html_e( 'How many persons will book this?', 'slotnova-booking' ); ?>
				</label>
				<select name="slotnova_group_quantity" id="slotnova_group_quantity" class="slotnova-group-qty-select" style="width: 100%; max-width: 240px; padding: 9px 12px; border-radius: 8px; border: 1px solid #cbd5e1; font-weight: 600; font-size: 14px; color: #0f172a; background: #fff;">
					<?php for ( $i = 1; $i <= $maxCap; $i++ ) : ?>
						<option value="<?php echo esc_attr( $i ); ?>"><?php printf( esc_html( _n( '%d Person', '%d Persons', $i, 'slotnova-booking' ) ), $i ); ?></option>
					<?php endfor; ?>
				</select>
			</div>

			<!-- Dynamic Participant Roster (Name, Gender, Email in same row) -->
			<div id="slotnova_participant_roster_container" class="slotnova-participant-roster">
				<!-- Dynamically generated via JavaScript -->
			</div>

		</div>
		<?php
	}
}
