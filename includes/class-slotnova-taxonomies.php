<?php
/**
 * SlotNova Taxonomies Class
 *
 * @package SlotNova\Booking
 * @version 1.0.0
 */

namespace SlotNova\Booking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Taxonomies
 *
 * Handles registration and custom fields for SlotNova taxonomies.
 */
class Taxonomies {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_taxonomies' ) );

		// Add Image fields for Services
		add_action( 'slotnova_service_add_form_fields', array( $this, 'add_image_field' ) );
		add_action( 'slotnova_service_edit_form_fields', array( $this, 'edit_image_field' ) );
		add_action( 'created_slotnova_service', array( $this, 'save_image_field' ) );
		add_action( 'edited_slotnova_service', array( $this, 'save_image_field' ) );

		// Add Avatar fields for Employees
		add_action( 'slotnova_employee_add_form_fields', array( $this, 'add_image_field' ) );
		add_action( 'slotnova_employee_edit_form_fields', array( $this, 'edit_image_field' ) );
		add_action( 'created_slotnova_employee', array( $this, 'save_image_field' ) );
		add_action( 'edited_slotnova_employee', array( $this, 'save_image_field' ) );

		// Enqueue media script on taxonomy pages
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_media_scripts' ) );

		// Remove Services and Employees columns from WooCommerce Products list table
		add_filter( 'manage_edit-product_columns', array( $this, 'remove_product_columns' ), 999 );
	}

	/**
	 * Remove Services and Employees columns from Products table.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function remove_product_columns( $columns ) {
		unset( $columns['taxonomy-slotnova_service'] );
		unset( $columns['taxonomy-slotnova_employee'] );
		return $columns;
	}

	/**
	 * Register custom taxonomies for Services and Employees.
	 *
	 * @return void
	 */
	public function register_taxonomies() {
		// Services Taxonomy
		$labels_service = array(
			'name'              => _x( 'Services', 'taxonomy general name', 'slotnova-booking' ),
			'singular_name'     => _x( 'Service', 'taxonomy singular name', 'slotnova-booking' ),
			'search_items'      => __( 'Search Services', 'slotnova-booking' ),
			'all_items'         => __( 'All Services', 'slotnova-booking' ),
			'parent_item'       => __( 'Parent Service', 'slotnova-booking' ),
			'parent_item_colon' => __( 'Parent Service:', 'slotnova-booking' ),
			'edit_item'         => __( 'Edit Service', 'slotnova-booking' ),
			'update_item'       => __( 'Update Service', 'slotnova-booking' ),
			'add_new_item'      => __( 'Add New Service', 'slotnova-booking' ),
			'new_item_name'     => __( 'New Service Name', 'slotnova-booking' ),
			'menu_name'         => __( 'Services', 'slotnova-booking' ),
		);

		$args_service = apply_filters( 'slotnova_register_service_taxonomy_args', array(
			'hierarchical'      => true,
			'labels'            => $labels_service,
			'show_ui'           => true,
			'show_admin_column' => false,
			'query_var'         => true,
			'meta_box_cb'       => false,
			'rewrite'           => array( 'slug' => 'slotnova-service' ),
		) );

		register_taxonomy( 'slotnova_service', array( 'product' ), $args_service );

		// Employees Taxonomy
		$labels_employee = array(
			'name'              => _x( 'Employees', 'taxonomy general name', 'slotnova-booking' ),
			'singular_name'     => _x( 'Employee', 'taxonomy singular name', 'slotnova-booking' ),
			'search_items'      => __( 'Search Employees', 'slotnova-booking' ),
			'all_items'         => __( 'All Employees', 'slotnova-booking' ),
			'parent_item'       => __( 'Parent Employee', 'slotnova-booking' ),
			'parent_item_colon' => __( 'Parent Employee:', 'slotnova-booking' ),
			'edit_item'         => __( 'Edit Employee', 'slotnova-booking' ),
			'update_item'       => __( 'Update Employee', 'slotnova-booking' ),
			'add_new_item'      => __( 'Add New Employee', 'slotnova-booking' ),
			'new_item_name'     => __( 'New Employee Name', 'slotnova-booking' ),
			'menu_name'         => __( 'Employees', 'slotnova-booking' ),
		);

		$args_employee = apply_filters( 'slotnova_register_employee_taxonomy_args', array(
			'hierarchical'      => false,
			'labels'            => $labels_employee,
			'show_ui'           => true,
			'show_admin_column' => false,
			'query_var'         => true,
			'meta_box_cb'       => false,
			'rewrite'           => array( 'slug' => 'slotnova-employee' ),
		) );

		register_taxonomy( 'slotnova_employee', array( 'product' ), $args_employee );
	}

	/**
	 * Enqueue media scripts for taxonomy pages.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_media_scripts( $hook ) {
		if ( in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) ) {
			wp_enqueue_media();
			wp_enqueue_style( 'slotnova-admin-css', SLOTNOVA_BOOKING_URL . 'assets/css/slotnova-admin.css', array(), SLOTNOVA_BOOKING_VERSION );
			wp_enqueue_script( 'slotnova-admin-js', SLOTNOVA_BOOKING_URL . 'assets/js/slotnova-admin.js', array( 'jquery' ), SLOTNOVA_BOOKING_VERSION, true );
			wp_localize_script( 'slotnova-admin-js', 'slotnova_admin_data', array(
				'i18n' => array(
					'choose_image' => __( 'Choose Image', 'slotnova-booking' ),
					'use_image'    => __( 'Use this image', 'slotnova-booking' ),
				),
			) );
		}
	}

	/**
	 * Add custom image field to taxonomy add form.
	 *
	 * @param string $taxonomy The taxonomy slug.
	 * @return void
	 */
	public function add_image_field( $taxonomy ) {
		wp_nonce_field( 'slotnova_taxonomy_save', 'slotnova_tax_nonce' );
		?>
		<div class="form-field term-group">
			<label for="slotnova-image-id"><?php esc_html_e( 'Image / Avatar', 'slotnova-booking' ); ?></label>
			<input type="hidden" id="slotnova-image-id" name="slotnova_image_id" class="slotnova-image-id" value="">
			<img class="slotnova-image-preview slotnova-term-preview-img slotnova-is-hidden" src="" alt="" />
			<button type="button" class="button slotnova-upload-image-button"><?php esc_html_e( 'Upload/Add Image', 'slotnova-booking' ); ?></button>
			<button type="button" class="button slotnova-remove-image-button slotnova-is-hidden"><?php esc_html_e( 'Remove Image', 'slotnova-booking' ); ?></button>
		</div>
		<?php
		if ( 'slotnova_service' === $taxonomy ) {
			?>
			<div class="form-field term-group">
				<label for="slotnova-service-price"><?php esc_html_e( 'Service Price', 'slotnova-booking' ); ?></label>
				<input type="number" id="slotnova-service-price" name="slotnova_service_price" value="0" min="0" step="0.01">
				<p class="description"><?php esc_html_e( 'Enter the price for this service. Default is 0.', 'slotnova-booking' ); ?></p>
			</div>
			<?php
		}
		do_action( 'slotnova_taxonomy_add_form_fields', $taxonomy );
	}

	/**
	 * Edit custom image field on taxonomy edit form.
	 *
	 * @param \WP_Term $term The current term object.
	 * @return void
	 */
	public function edit_image_field( $term ) {
		wp_nonce_field( 'slotnova_taxonomy_save', 'slotnova_tax_nonce' );
		$image_id  = get_term_meta( $term->term_id, 'slotnova_image_id', true );
		$image_url = '';
		if ( $image_id ) {
			$image_url = wp_get_attachment_image_url( $image_id, 'thumbnail' );
		}
		?>
		<tr class="form-field term-group-wrap">
			<th scope="row"><label for="slotnova-image-id"><?php esc_html_e( 'Image / Avatar', 'slotnova-booking' ); ?></label></th>
			<td>
				<input type="hidden" id="slotnova-image-id" name="slotnova_image_id" class="slotnova-image-id" value="<?php echo esc_attr( $image_id ); ?>">
				<img class="slotnova-image-preview slotnova-term-preview-img <?php echo $image_url ? '' : 'slotnova-is-hidden'; ?>" src="<?php echo esc_url( $image_url ); ?>" alt="" />
				<button type="button" class="button slotnova-upload-image-button"><?php esc_html_e( 'Upload/Add Image', 'slotnova-booking' ); ?></button>
				<button type="button" class="button slotnova-remove-image-button <?php echo $image_url ? '' : 'slotnova-is-hidden'; ?>"><?php esc_html_e( 'Remove Image', 'slotnova-booking' ); ?></button>
			</td>
		</tr>
		<?php
		if ( 'slotnova_service' === $term->taxonomy ) {
			$price = get_term_meta( $term->term_id, 'slotnova_service_price', true );
			if ( '' === $price || false === $price ) {
				$price = '0';
			}
			?>
			<tr class="form-field term-group-wrap">
				<th scope="row"><label for="slotnova-service-price"><?php esc_html_e( 'Service Price', 'slotnova-booking' ); ?></label></th>
				<td>
					<input type="number" id="slotnova-service-price" name="slotnova_service_price" value="<?php echo esc_attr( $price ); ?>" min="0" step="0.01">
					<p class="description"><?php esc_html_e( 'Enter the price for this service. Default is 0.', 'slotnova-booking' ); ?></p>
				</td>
			</tr>
			<?php
		}
		do_action( 'slotnova_taxonomy_edit_form_fields', $term );
	}

	/**
	 * Save custom fields for taxonomies.
	 *
	 * @param int $term_id The ID of the term being saved.
	 * @return void
	 */
	public function save_image_field( $term_id ) {
		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		if ( ! isset( $_POST['slotnova_tax_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['slotnova_tax_nonce'] ) ), 'slotnova_taxonomy_save' ) ) {
			return;
		}

		if ( isset( $_POST['slotnova_image_id'] ) ) {
			update_term_meta( $term_id, 'slotnova_image_id', sanitize_text_field( wp_unslash( $_POST['slotnova_image_id'] ) ) );
		}

		if ( isset( $_POST['taxonomy'] ) && 'slotnova_service' === $_POST['taxonomy'] ) {
			if ( isset( $_POST['slotnova_service_price'] ) ) {
				$price = floatval( $_POST['slotnova_service_price'] );
				update_term_meta( $term_id, 'slotnova_service_price', $price );
			} else {
				update_term_meta( $term_id, 'slotnova_service_price', 0 );
			}
		}

		do_action( 'slotnova_save_taxonomy_meta', $term_id, $_POST );
	}
}
