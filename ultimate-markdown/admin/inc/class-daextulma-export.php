<?php
/**
 * This class should be used to handle the export of the documents stored in the plugin.
 *
 * @package ultimate-markdown
 */

/**
 * This class should be used to handle the export of the documents stored in the plugin.
 */
class Daextulma_Export {

	/**
	 * The instance of the class.
	 *
	 * @var null
	 */
	protected static $instance = null;

	/**
	 * An instance of the shared class.
	 *
	 * @var Daextulma_Shared
	 */
	private $shared = null;

	/**
	 * The constructor.
	 */
	private function __construct() {

		// Assign an instance of the plugin info.
		$this->shared = Daextulma_Shared::get_instance();

		// Export controller.
		add_action( 'init', array( $this, 'export_controller' ) );
	}

	/**
	 * Get the singleton instance of the class.
	 *
	 * @return self|null
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * The click on the "Export" button available in the "Export" menu is intercepted and the
	 *  method that generates the downloadable ZIP file that includes all the markdown files generated from the
	 *  documents stored in the plugin.
	 *
	 * @return void
	 */
	public function export_controller() {

		/**
		 * Intercept requests that come from the "Export" button of the
		 * "Ultimate Markdown -> Export" menu and generate the downloadable ZIP file.
		 */
		if ( isset( $_POST['daextulma_export'] ) ) {

			// Nonce verification.
			check_admin_referer( 'daextulma_tools_export', 'daextulma_tools_export_nonce' );

			// Verify capability.
			if ( ! current_user_can( get_option( $this->shared->get( 'slug' ) . '_tools_menu_required_capability' ) ) ) {
				wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ultimate-markdown' ) );
			}

			// Generates a ZIP file that includes all the documents stored in the plugin.
			$this->export_documents();

		}
	}

	/**
	 * Generates a ZIP file that includes all the documents stored in the plugin.
	 *
	 * @return false|void
	 */
	private function export_documents() {

		// Get the data from the 'connect' db.
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$document_a = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}daextulma_document ORDER BY document_id ASC", ARRAY_A );

		// Stripslash title and content in the document array.
		foreach ( $document_a as $key => $document ) {
			$document_a[ $key ]['title']   = stripslashes( $document['title'] );
			$document_a[ $key ]['content'] = stripslashes( $document['content'] );
		}

		// Create the zip archive.
		$this->generate_zip_with_markdown_files( $document_a );
	}

	/**
	 * Generates a ZIP file with all the documents.
	 *
	 * @param array $document_a The array of documents.
	 */
	private function generate_zip_with_markdown_files( $document_a ) {

		// If there are no documents there is nothing to export.
		if ( count( $document_a ) === 0 ) {
			return false;
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'The export requires the PHP Zip extension, which is not available on this server.', 'ultimate-markdown' ) );
		}

		// Renames the items with a duplicate name.
		$document_a = $this->shared->rename_duplicate_names_in_document( $document_a );

		/**
		 * Build the archive in the server temporary directory, using a file with a random name. The archive is never
		 * stored in a publicly accessible directory (like the current working directory, which is usually wp-admin/),
		 * and it's deleted at the end of the request, including when the request ends early because of an error or
		 * because the download is interrupted.
		 */
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$archive_path = wp_tempnam( 'daextulma-export' );
		register_shutdown_function( 'wp_delete_file', $archive_path );

		// Create and open a new zip archive. OVERWRITE is required because wp_tempnam() creates an empty file.
		$zip = new ZipArchive();
		if ( true !== $zip->open( $archive_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			wp_die( esc_html__( 'The ZIP archive could not be created.', 'ultimate-markdown' ) );
		}

		/**
		 * Add one Markdown file for each document. The content is added directly from memory, so no intermediate
		 * files are written to disk.
		 */
		$used_file_names = array();
		foreach ( $document_a as $document ) {

			$file_name = sanitize_file_name( $document['title'] . '.md' );

			// If a file with the same name is already in the archive, add a suffix "[counter]" to the file name.
			$counter = 0;
			while ( isset( $used_file_names[ $file_name ] ) ) {
				++$counter;
				$file_name = sanitize_file_name( $document['title'] ) . $counter . '.md';
			}
			$used_file_names[ $file_name ] = true;

			$zip->addFromString( $file_name, $document['content'] );

		}

		if ( ! $zip->close() ) {
			wp_die( esc_html__( 'The ZIP archive could not be created.', 'ultimate-markdown' ) );
		}

		// Generate the header of a zip file.
		header( 'Content-type: application/zip' );
		header( 'Content-Disposition: attachment; filename="data-' . time() . '.zip"' );
		header( 'Content-Length: ' . filesize( $archive_path ) );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Send the archive to the browser. The archive is then deleted by the shutdown function registered above.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streams a local temporary file created by ZipArchive.
		readfile( $archive_path );

		die();
	}
}
