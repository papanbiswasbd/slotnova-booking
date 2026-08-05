=== SlotNova Booking for WooCommerce ===
Contributors: papanbiswasbd
Tags: woocommerce booking, appointment booking, time slot booking, service booking, booking calendar
Requires at least: 5.3
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

SlotNova Booking for WooCommerce transforms your WooCommerce store into a powerful booking system for SPA centers, salons, photographers, yacht rentals, and service businesses.

== Description ==

**SlotNova Booking for WooCommerce** is an all-in-one booking and appointment management solution built natively for WooCommerce. Whether you run a SPA center, hair salon, photography business, yacht rental service, wellness clinic, or appointment-based service business, SlotNova allows your customers to select services, pick staff members, choose dates on an inline calendar, and select real-time available time slots.

= Live Demos =
Explore our live booking demonstrations:
* [Photographer Booking Demo](https://livelang.pro/slotnova/product/photographer-booking/)
* [Yacht Booking Demo](https://livelang.pro/slotnova/product/yacht-booking/)
* [Wellness Therapy Demo (Free)](https://livelang.pro/slotnova/product/wellness-therapy/)
* [Massage Packages Demo (Free)](https://livelang.pro/slotnova/product/massage-packages/)
* [Partial Deposit Demo (Pro Addon)](https://livelang.pro/slotnova/product/massage-therapy-with-partial-deposit/)

### Key Features
* **Custom Product Type**: Seamlessly creates a new 'SlotNova Booking' WooCommerce product type.
* **Whole Day & Time-Slot Bookings**: Support both time-slot based bookings (e.g., 2-hour slots) and full-day bookings when time slots are disabled on the product level.
* **Fully Booked Dates & "Already Booked" Tooltips**: Automatically disables booked calendar dates with strikethrough styling and a sleek hover tooltip ("Already Booked").
* **Dynamic Time Slot Generator**: Computes precise time slot start times dynamically between custom Opening Time and Closing Time based on Slot Duration (e.g., 10:00 AM to 04:00 PM with 120-minute slots).
* **Interactive Quick Status Change**: Change booking order status directly from the All Bookings Management table via AJAX dropdown badges.
* **Product Title Column**: Displays product titles across both the All Bookings Management table and WooCommerce Admin Orders table.
* **Staff-Based Time Slot Availability**: Each booking is assigned to a specific staff member. If a slot is booked for that staff member, it automatically becomes visually disabled for that staff member while remaining available for others.
* **Custom Staff & Service Labels**: Support frontend custom labels (e.g., "Photographer", "Doctor") while keeping core taxonomy terms intact in administrative areas.
* **Inline Calendar & Real-Time Availability**: Customers select dates and available time slots update dynamically via AJAX.
* **Instant Double-Booking Prevention**: Strict server-side and client-side validation prevents double-booking the same staff member or service at the same time.
* **Automatic Slot Release**: Trashing, cancelling, refunding, or failing an order automatically re-enables its booked slot instantly.
* **Partial Deposits & Flexible Payments (Pro Addon)**: Offer deposit options (percentage or fixed amount) with a seamless Payment Option switcher on single product pages, cart totals, and checkout order tables.
* **Dynamic Cart & Checkout Totals**: Clean "Due at Appointment" balance breakdown for deposit orders without confusing discount/negative fee lines.
* **Modular Extension Manager**: Built-in extension architecture supporting pro add-ons, license key management, and automated update delivery.
* **Flexible Operating Hours & Off-Days**: Set global or product-level business opening/closing hours, slot durations, and weekly/specific off-days.
* **Timezone Precision**: Built-in UTC timestamp calculation eliminates timezone offset shifts across international users.
* **Developer-Friendly API**: Exposes global accessor `slotnova_booking()` and extensive action/filter hooks for third-party add-on development.

== Installation ==

1. Upload the `slotnova-booking` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **SlotNova Booking > Settings** to configure your business opening/closing hours and off-days.
4. Create a new WooCommerce product and set its Product Type to **SlotNova Booking**.
5. Configure services, staff members, opening/closing hours, and slot durations on the product edit screen.

== Frequently Asked Questions ==

= Does SlotNova require WooCommerce? =
Yes, SlotNova Booking is built natively on top of WooCommerce and requires WooCommerce to be activated.

= How does Whole Day booking work? =
When "Enable Time Slots" is set to "No (Disable)" on the product edit screen, time slots are hidden on the frontend. Customers select a date, and the order is automatically booked for the whole day.

= What happens when a date is fully booked? =
Fully booked dates automatically become disabled in the inline and popup calendar with a strikethrough appearance and a hover tooltip saying "Already Booked".

= Can I change order statuses directly from the All Bookings management table? =
Yes! The All Bookings Management table features interactive status dropdown badges that update order status instantly via AJAX.

= How does the Partial Deposit Pro Addon work? =
The Partial Deposit addon allows store owners to collect an upfront deposit (e.g. 20% or $50) at checkout while deferring the remaining balance to be paid at the appointment. It adds an interactive "Full Payment vs Pay Deposit" switcher to product pages, cart totals, and checkout pages.

= Where can I try a live demonstration? =
You can explore our live booking experiences here:
* [Photographer Booking Demo](https://livelang.pro/slotnova/product/photographer-booking/)
* [Yacht Booking Demo](https://livelang.pro/slotnova/product/yacht-booking/)
* [Wellness Therapy Demo (Free)](https://livelang.pro/slotnova/product/wellness-therapy/)
* [Massage Packages Demo (Free)](https://livelang.pro/slotnova/product/massage-packages/)
* [Partial Deposit Demo (Pro Addon)](https://livelang.pro/slotnova/product/massage-therapy-with-partial-deposit/)

== Developer Hooks ==

SlotNova Booking exposes standard WordPress action and filter hooks:

* `slotnova_booking()` – Global accessor function returning `\SlotNova\Booking\Plugin`.
* `slotnova_get_booked_slots` – Filter array of booked slots for a given product and date.
* `slotnova_validate_booking_data` – Filter validation status before adding a booking to the cart.
* `slotnova_save_booking_data_to_order` – Action triggered when saving booking metadata to an order item.
* `slotnova_before_booking_form` / `slotnova_after_booking_form` – Actions for inserting custom form content.

== Changelog ==

= 1.2.0 =
* Added Whole Day Booking mode when time slots are disabled on product level (automatically assigns Time: All Day).
* Added Fully Booked Date disabling on Flatpickr calendar with strikethrough styling and "Already Booked" hover tooltip.
* Added dynamic product time slot generation between Opening Time and Closing Time based on custom Slot Duration (e.g. 10:00 AM to 04:00 PM with 120-min slots).
* Added interactive quick status change dropdown select badges in the All Bookings Management table with real-time AJAX updates.
* Added Product Title ("Title") column across both All Bookings Management table and WooCommerce Admin Orders list table.
* Added custom staff & service label resolution (e.g. Photographer, Doctor) across admin booking tables and emails.
* Added Pro Addon support for Partial Deposits & Flexible Payment Plans (fixed amount or percentage deposit with remaining balance due at appointment).
* Added dynamic Payment Plan selector on single product pages, WooCommerce cart totals table, and checkout review table.
* Added clean "Due at Appointment" breakdown in cart & checkout, replacing negative fee lines for a polished user experience.
* Added staff-specific time slot availability validation, ensuring booked slots auto-disable for the selected staff member while remaining available for others.
* Added automatic time slot re-enabling when orders are moved to `trash`, `cancelled`, `refunded`, `failed`, or `draft` status.
* Added modular Extension Manager architecture supporting pro add-on installation, activation status persistence, and license verification.
* Updated live demo showcase links including Photographer Booking and Yacht Booking demos.

= 1.1.1 =
* Added streamlined SlotNova Overview Dashboard Analytics Widget with dynamic Chart.js timeline and metrics summary.
* Added Classic & Clean WP Admin Filter Toolbar with quick 1-click date range presets (Today, Last 7 Days, Last 30 Days, This Month, This Year).
* Added Booking Management List View (showing last 20 bookings) & interactive FullCalendar Schedule tab switcher with View Details modal popup.
* Added prominent "View All Bookings" action button linking directly to full plugin booking management page.
* Fixed currency HTML entity decoding (`$2,515.00`) and upgraded metric card visual icons and header status badges.

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

= 1.2.0 =
Upgrade to 1.2.0 for Whole Day bookings, "Already Booked" hover tooltips, dynamic time slot calculations, quick status change dropdowns, and Pro Addon Partial Deposit support.

= 1.1.1 =
Upgrade to 1.1.1 for streamlined dashboard overview analytics, quick date range filter presets, and interactive booking schedule calendar.

= 1.1.0 =
Upgrade to 1.1.0 for instant time slot availability validation, developer hooks, and enhanced UI/UX.
