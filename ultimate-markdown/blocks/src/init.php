<?php
/**
 * This file contains the function that will enqueue the Gutenberg block assets for the backend.
 *
 * @package ultimate-markdown
 */

// Prevent direct access to this file.
if ( ! defined( 'WPINC' ) ) {
	die();
}

/**
 * Enqueue the Gutenberg block assets for the backend.
 *
 * 'wp-blocks': includes block type registration and related functions.
 * 'wp-element': includes the WordPress Element abstraction for describing the structure of your blocks.
 */
function daextulma_editor_assets() {

	// Check capability before loading block editor assets.
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	/**
	 * Load the assets only in the post editor (post.php and post-new.php).
	 *
	 * The "enqueue_block_editor_assets" hook also fires in the Site Editor, in the block-based Widgets screen, and in
	 * the Customizer. The editor panels of the plugin work with posts, and they can't work in these contexts. For
	 * example, templates don't have the post meta used by the panels, and the Marked and DOMPurify libraries are
	 * loaded only in the post editor.
	 */
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( null === $screen || 'post' !== $screen->base ) {
		return;
	}

	// assign an instance of Daextulma_Shared.
	$shared = Daextulma_Shared::get_instance();

	// Styles ---------------------------------------------------------------------------------------------------------.

	wp_enqueue_style(
		'daextulma-editor-css',
		plugins_url( 'css/editor.css', __DIR__ ),
		array( 'wp-edit-blocks' ), // Dependency to include the CSS after it.
		$shared->get( 'ver' )
	);

	// Scripts --------------------------------------------------------------------------------------------------------.

	// Block.
	wp_enqueue_script(
		'daextulma-editor-js', // Handle.
		plugins_url( '/build/index.js', __DIR__ ), // We register the block here.
		array( 'wp-blocks', 'wp-element' ), // Dependencies.
		$shared->get( 'ver' ),
		true // Enqueue the script in the footer.
	);

	// Get the documents used to populate the selector in the "Load Document" block editor sidebar section.
	$document_a_alt = $shared->get_documents_for_selector();

	// Store the JavaScript parameters in the window.DAEXTULMA_PARAMETERS object.
	$initialization_script  = 'window.DAEXTULMA_PARAMETERS = {';
	$initialization_script .= 'documents: ' . wp_json_encode( $document_a_alt ) . ',';
	$initialization_script .= 'ajaxUrl: "' . admin_url( 'admin-ajax.php' ) . '",';
	$initialization_script .= 'pluginDirectoryUrl: "' . $shared->get( 'url' ) . '",';
	$initialization_script .= 'editorMarkdownParser: "' . get_option('daextulma_editor_markdown_parser') . '",';
	$initialization_script .= 'nonce: "' . wp_create_nonce( 'daextulma' ) . '",';
	$initialization_script .= '};';
	wp_add_inline_script( $shared->get( 'slug' ) . '-editor-js', $initialization_script, 'before' );

}

// Enqueue the editor assets. Note that daextulma_editor_assets() loads them only in the post editor.
add_action( 'enqueue_block_editor_assets', 'daextulma_editor_assets' );

/**
 * Register the meta fields used in the components of the post sidebar.
 *
 * See: https://developer.wordpress.org/reference/functions/register_post_meta/
 */
function ultimate_markdown_register_post_meta() {

	/**
	 * Register the meta used to save the value of the selector available in the "Load Document" section of the post
	 * sidebar included in the post editor.
	 */
	register_post_meta(
		'', // Registered in all post types.
		'_import_markdown_pro_load_document_selector',
		array(
			'auth_callback' => '__return_true',
			'default'       => 0,
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => 'integer',
		)
	);

	/**
	 * Register the meta used to save the value of the textarea available in the "Submit Text" section of the post
	 *  sidebar included in the post editor.
	 */
	register_post_meta(
		'', // Registered in all post types.
		'_import_markdown_pro_submit_text_textarea',
		array(
			'auth_callback' => '__return_true',
			'default'       => '',
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => 'string',
		)
	);
}

add_action( 'init', 'ultimate_markdown_register_post_meta' );
