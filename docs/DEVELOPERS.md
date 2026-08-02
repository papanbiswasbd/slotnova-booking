# SlotNova Booking - Developer & Add-on API Guide

Welcome to the developer documentation for **SlotNova Booking for WooCommerce**.
SlotNova Booking is engineered with WordPress best practices, modular OOP architecture, and a rich hook system (Actions & Filters) to make developing add-on plugins and custom extensions effortless.

---

## Global Accessor Function

Access the main plugin instance from anywhere in your custom plugin or theme:

```php
// Get main plugin instance
$slotnova = slotnova_booking();

// Access components
$taxonomies = slotnova_booking()->taxonomies;
$frontend   = slotnova_booking()->frontend;
$cart       = slotnova_booking()->cart;
$admin      = slotnova_booking()->admin;
```

---

## Action Hooks Reference

### Lifecycle Hooks
| Action Hook | Parameters | Description |
| :--- | :--- | :--- |
| `slotnova_booking_loaded` | `(Plugin $instance)` | Fires when the main plugin is loaded. |
| `slotnova_booking_init` | `(Plugin $instance)` | Fires after all core components are initialized. |

### Frontend Booking Form Hooks
| Action Hook | Parameters | Description |
| :--- | :--- | :--- |
| `slotnova_before_booking_form` | `(WC_Product $product, array $services, array $employees)` | Fires before opening `<form class="slotnova-form">`. |
| `slotnova_before_service_select` | `(WC_Product $product, array $services)` | Fires before rendering the Service select wrapper. |
| `slotnova_after_service_select` | `(WC_Product $product, array $services)` | Fires after rendering the Service select wrapper. |
| `slotnova_before_employee_select` | `(WC_Product $product, array $employees)` | Fires before rendering Employee select wrapper. |
| `slotnova_after_employee_select` | `(WC_Product $product, array $employees)` | Fires after rendering Employee select wrapper. |
| `slotnova_before_date_select` | `(WC_Product $product)` | Fires before Date picker input. |
| `slotnova_after_date_select` | `(WC_Product $product)` | Fires after Date picker input. |
| `slotnova_before_time_slots` | `(WC_Product $product)` | Fires before time slot pills grid. |
| `slotnova_after_time_slots` | `(WC_Product $product)` | Fires after time slot pills grid. |
| `slotnova_before_booking_summary` | `(WC_Product $product)` | Fires before booking summary card. |
| `slotnova_after_booking_summary` | `(WC_Product $product)` | Fires after booking summary card. |
| `slotnova_after_booking_form` | `(WC_Product $product)` | Fires before closing `</form>`. |

### WooCommerce Cart & Order Hooks
| Action Hook | Parameters | Description |
| :--- | :--- | :--- |
| `slotnova_before_add_to_cart` | `(int $product_id, array $booking_data)` | Fires right before item is added to cart with booking data. |
| `slotnova_save_booking_data_to_order` | `(WC_Order_Item_Product $item, string $key, array $values, WC_Order $order)` | Fires when booking metadata is saved to an order line item. |

### Admin & Manual Booking Hooks
| Action Hook | Parameters | Description |
| :--- | :--- | :--- |
| `slotnova_before_manual_booking_created` | `(array $post_data)` | Fires before creating a manual booking order via AJAX. |
| `slotnova_after_manual_booking_created` | `(int $order_id, WC_Order $order, array $post_data)` | Fires after a manual booking order is created & saved. |
| `slotnova_product_data_tab_content` | `(WP_Post $post)` | Fires inside the WooCommerce Product Data tab for custom fields. |
| `slotnova_save_product_tab_data` | `(int $post_id, array $post_data)` | Fires when saving SlotNova product tab settings. |
| `slotnova_taxonomy_add_form_fields` | `(string $taxonomy)` | Fires on taxonomy term add form. |
| `slotnova_taxonomy_edit_form_fields` | `(WP_Term $term)` | Fires on taxonomy term edit form. |
| `slotnova_save_taxonomy_meta` | `(int $term_id, array $post_data)` | Fires when taxonomy term meta is saved. |

---

## Filter Hooks Reference

| Filter Hook | Default Value | Description |
| :--- | :--- | :--- |
| `slotnova_get_booked_slots` | `array $booked_slots` | Filter booked time slots array for AJAX availability checks. |
| `slotnova_frontend_localized_params` | `array $params` | Filter localized JS parameters (`slotnova_params`). |
| `slotnova_admin_localized_data` | `array $localized_data` | Filter localized JS parameters (`slotnova_admin_data`). |
| `slotnova_register_service_taxonomy_args` | `array $args` | Filter `register_taxonomy` args for `slotnova_service`. |
| `slotnova_register_employee_taxonomy_args` | `array $args` | Filter `register_taxonomy` args for `slotnova_employee`. |
| `slotnova_validate_booking_data` | `bool $passed` | Filter add-to-cart validation status. |
| `slotnova_cart_item_booking_data` | `array $booking_data` | Filter booking metadata saved to cart item. |
| `slotnova_cart_item_price` | `float $price` | Filter line item price set in cart. |
| `slotnova_display_item_data_in_cart` | `array $item_data` | Filter booking meta displayed in cart / checkout table. |
| `slotnova_manual_booking_order_status` | `'pending'` | Filter initial status of manual bookings created by admin. |
| `slotnova_dashboard_metrics` | `array $metrics` | Filter calculated dashboard metrics and chart values. |
| `slotnova_calendar_events` | `array $events` | Filter calendar and booking list events data. |

---

## Add-on Plugin Code Example

Create an add-on plugin by hooking into SlotNova:

```php
<?php
/**
 * Plugin Name: SlotNova Custom Addon Example
 * Description: Custom Addon for SlotNova Booking.
 */

// Listen to order creation after manual booking
add_action( 'slotnova_after_manual_booking_created', function( $order_id, $order, $posted_data ) {
    // Send custom SMS notification or trigger third-party API webhook
    error_log( 'New manual booking created for Order #' . $order_id );
}, 10, 3 );

// Modify manual booking status to processing
add_filter( 'slotnova_manual_booking_order_status', function( $status, $posted_data ) {
    return 'processing';
}, 10, 2 );

// Add custom fields to booking form
add_action( 'slotnova_after_service_select', function( $product, $services ) {
    echo '<div class="slotnova-form-group"><label>Custom Notes</label><input type="text" name="custom_note"></div>';
}, 10, 2 );
```
