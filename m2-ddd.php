<?php
/**
 * Plugin Name: M2 Dump Debug Data
 * Description: Dump M2 data for debugging purposes.
 * Author:      Philipp Stracker
 * Version:     1.0.0
 *
 * Dumps some data for debugging purposes
 * ----------------------------------------------------------------------------
 */

class M2_DDD {
	const SLUG = 'm2_ddd';

	static public function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
	}

	static protected function prepare_dump() {
		self::$dump = '';

		foreach ( self::$post_types as $post_type ) {

		}
	}

	static public function admin_menu() {
		add_utility_page(
			'M2 Dump Debug Data',
			'M2 Dump',
			'manage_options',
			self::SLUG,
			array( __CLASS__, 'menu_page' ),
			'dashicons-archive'
		);
	}

	static public function menu_page() {
		?>
		<div class="wrap">
		<h2>M2 - Dump Debug Data</h2>

		<br>
		<?php
		if ( isset( $_POST['include'] ) && is_array( $_POST['include'] ) ) {
			self::display_dump();
		} else {
			self::display_form();
		}
		?>
		</div>
		<?php
	}

	static protected function display_form() {
		?>
		<form method="post">
			<p>
				<label for="include-1">
					<input type="checkbox" name="include[]" id="include-1" value="by_id" />
					Specific Post-IDs (comma separated):
				</label>
				<input type="text" name="by_id_value" />
			</p>
			<p>
				<label for="include-2">
					<input type="checkbox" name="include[]" id="include-2" value="setting" />
					M2 Settings
				</label>
			</p>
			<p>
				<label for="include-3">
					<input type="checkbox" name="include[]" id="include-3" value="membership" />
					All Memberships
				</label>
			</p>
			<p>
				<label for="include-4">
					<input type="checkbox" name="include[]" id="include-4" value="subscription" />
					All Subscriptions
				</label>
			</p>
			<p>
				<label for="include-5">
					<input type="checkbox" name="include[]" id="include-5" value="communication" />
					All Automated Email Response Templates
				</label>
			</p>
			<p>
				<label for="include-6">
					<input type="checkbox" name="include[]" id="include-6" value="invoice" />
					All Invoices
				</label>
			</p>
			<p>
				<button class="button-primary">Dump &raquo;</button>
			</p>
		</form>
		<?php
	}

	static protected function display_dump() {
		$data = new stdClass();
		$count = 0;

		foreach ( $_POST['include'] as $part ) {
			$items = self::dump_items( $part );
			$count += count( $items );
			$data->$part = $items;
		}

		$dump = var_export( $data, true );
		$bytes = strlen( $dump );
		$download_url = self::compress_data( $dump );

		printf(
			'<p>Total Size: <b>%s</b> | Items included: <b>%s</b> %s</p>',
			intval( $bytes / 10.12 ) / 100 . ' kB',
			$count,
			( $download_url ? '| <a href="' . $download_url . '">Download</a>' : '' )
		);
		echo '<textarea style="width: 100%;min-height: 320px;font: 12px monospace" readonly="readonly">';
		echo esc_textarea( $dump );
		echo '</textarea>';
	}

	static protected function dump_items( $type ) {
		global $wpdb;
		$items = array();

		if ( 'by_id' == $type ) {
			if ( isset( $_POST['by_id_value'] ) ) {
				$ids = explode( ',', $_POST['by_id_value'] );
				foreach ( $ids as $id ) {
					$id = intval( trim( $id ) );
					if ( $id < 1 ) { continue; }

					$post = get_post( $id );
					$meta = get_post_meta( $id );
					$items[] = array( 'post' => $post, 'meta' => $meta );
				}
			}
		} elseif ( 'setting' == $type ) {
			$option_names = $wpdb->get_col(
				"SELECT option_name
				FROM $wpdb->options
				WHERE option_name LIKE 'ms_%';"
			);
			foreach ( $option_names as $option ) {
				$items[$option] = get_option( $option );
			}
		} else {
			$post_type = '';
			switch ( $type ) {
				case 'membership': $post_type = 'ms_membership'; break;
				case 'subscription': $post_type = 'ms_relationship'; break;
				case 'communication': $post_type = 'ms_communication'; break;
				case 'invoice': $post_type = 'ms_invoice'; break;
			}

			$posts = get_posts(
				array(
					'post_type' => $post_type,
					'posts_per_page' => -1,
					'post_status' => 'any',
					'orderby' => 'ID',
					'order' => 'ASC',
				)
			);

			foreach ( $posts as $post ) {
				$meta = get_post_meta( $post->ID );
				$items[] = array( 'post' => $post, 'meta' => $meta );
			}
		}

		return $items;
	}

	static protected function compress_data( $data ) {
		$url = false;

		if ( class_exists( 'ZipArchive' ) ) {
			$destination = WP_CONTENT_DIR . '/m2_dump.zip';

			if ( file_exists( $destination ) ) {
				unlink( $destination );
			}

			$zip = new ZipArchive();
			if ( true !== $zip->open( $destination, ZIPARCHIVE::CREATE ) ) {
				return false;
			}

			$zip->addFromString( 'dump.txt', $data );

			$zip->close();

			if ( file_exists( $destination ) ) {
				$url = content_url( '/m2_dump.zip' );
			}
		}

		return $url;
	}
}

M2_DDD::init();
