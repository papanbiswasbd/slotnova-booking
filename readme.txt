=== SlotNova Booking for WooCommerce ===
Contributors: papanbiswasbd
Tags: booking, spa, appointments, reservations, salon
Requires at least: 5.3
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

SlotNova Booking for WooCommerce transforms your WooCommerce store into a powerful booking system for SPA centers, salons, and service businesses.

== Description ==

**SlotNova Booking for WooCommerce** is an all-in-one booking and appointment management solution built natively for WooCommerce. Whether you run a SPA center, hair salon, wellness clinic, or appointment-based service business, SlotNova allows your customers to select services, pick staff members, choose dates on an inline calendar, and select real-time available time slots.

= Live Demo =
Explore the live booking demonstration: [Massage Packages Demo](https://livelang.pro/slotnova/product/massage-packages/)

### Key Features
* **Custom Product Type**: Seamlessly creates a new 'SlotNova Booking' WooCommerce product type.
* **Service & Staff Assignment**: Assign specific services and staff members (employees) per product with custom pricing and images.
* **Inline Calendar & Real-Time Availability**: Customer selects date and available time slots update dynamically via AJAX.
* **Instant Double-Booking Prevention**: Strict server-side and client-side validation prevents double-booking the same staff member or service at the same time.
* **Flexible Operating Hours & Off-Days**: Set global or product-level business opening/closing hours, slot durations, and weekly/specific off-days.
* **Timezone Precision**: Built-in UTC timestamp calculation eliminates timezone offset shifts across international users.
* **Developer-Friendly API**: Exposes global accessor `slotnova_booking()` and extensive action/filter hooks for third-party add-on development.
* **Admin Manual Booking**: Create manual bookings directly from the WordPress Admin with an interactive modal dialog.
* **Dashboard Analytics**: Track total bookings, revenue, upcoming appointments, and staff performance from the admin panel.

== Installation ==

1. Upload the `slotnova-booking` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **SlotNova Booking > Settings** to configure your business opening/closing hours and off-days.
4. Create a new WooCommerce product and set its Product Type to **SlotNova Booking**.
5. Configure services, staff members, and slot durations on the product edit screen.

== Frequently Asked Questions ==

= Does SlotNova require WooCommerce? =
Yes, SlotNova Booking is built natively on top of WooCommerce and requires WooCommerce to be activated.

= How does real-time slot validation work? =
When a customer picks a date or changes their selected service/employee, an AJAX query checks active orders and cart sessions. If a staff member or service slot is already booked for that specific date and time, the slot button is immediately disabled on the UI.

= Can developers extend SlotNova with custom hooks? =
Yes! SlotNova is built developer-friendly. Access the core plugin instance via `slotnova_booking()` and filter or hook into lifecycle points including cart validation, price calculations, and admin modal actions.

= Where can I try a live demonstration? =
You can explore the live booking experience here: [Massage Packages Demo](https://livelang.pro/slotnova/product/massage-packages/).

== Developer Hooks ==

SlotNova Booking exposes standard WordPress action and filter hooks:

* `slotnova_booking()` – Global accessor function returning `\SlotNova\Booking\Plugin`.
* `slotnova_get_booked_slots` – Filter array of booked slots for a given product and date.
* `slotnova_validate_booking_data` – Filter validation status before adding a booking to the cart.
* `slotnova_save_booking_data_to_order` – Action triggered when saving booking metadata to an order item.
* `slotnova_before_booking_form` / `slotnova_after_booking_form` – Actions for inserting custom form content.

== Changelog ==

= 1.1.0 =
* Added instant AJAX time slot loading and real-time double-booking prevention.
* Added UTC date normalization (`slotnova_parse_date()`) to prevent timezone offset shifts.
* Added custom search select dropdowns with price display for services and employees.
* Added developer hook API (`slotnova_booking()`) and filter suite across cart and order lifecycles.
* Enhanced UI/UX with smooth reveal animations, pulse loading states, and clean receipt summary cards.
* Hidden internal metadata (`_slotnova_service_id`, `_slotnova_employee_id`) from WooCommerce order item displays.

= 1.0.0 =
* Initial Release of SlotNova Booking for WooCommerce.

== Upgrade Notice ==

= 1.1.0 =
Upgrade to 1.1.0 for instant time slot availability validation, developer hooks, and enhanced UI/UX.
