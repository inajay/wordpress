<?php
/**
 * Plugin Name: Tutor Content Bilingual (Hindi + English)
 * Description: Adds a Hindi content editor to course posts and displays Hindi first with English below on the front-end.
 * Version: 1.0.1
 * Author: Themeum Customization
 * Text Domain: tutor-content-bilingual
 */

defined( 'ABSPATH' ) || exit;

class Tutor_Content_Bilingual {

	/** @var array supported post types to attach meta box to */
	private $post_types = array( 'course', 'courses', 'tutor_course', 'tutor_courses' );

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_hindi_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_hindi_content' ), 10, 2 );
		add_filter( 'the_content', array( $this, 'render_bilingual_content' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	public function add_hindi_meta_box() {
		$found = array();
		foreach ( $this->post_types as $pt ) {
			if ( post_type_exists( $pt ) ) {
				$found[] = $pt;
			}
		}

		// If none of the expected course post types exist, fall back to 'post' so you can test.
		if ( empty( $found ) ) {
			$found = array( 'post' );
			// Add a one-time admin notice explaining the fallback.
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-info"><p>';
				echo esc_html__( 'Tutor Content Bilingual: no Tutor course post-type found. Meta box is attached to Posts as a fallback. If you are using a different course post type, tell me its name and I will add it.', 'tutor-content-bilingual' );
				echo '</p></div>';
			} );
		}

		foreach ( $found as $pt ) {
			add_meta_box(
				'tutor_hindi_content_box',
				__( 'Hindi Course Content', 'tutor-content-bilingual' ),
				array( $this, 'render_meta_box' ),
				$pt,
				'normal',
				'high'
			);
		}
	}

	public function enqueue_admin_assets( $hook ) {
		// Only enqueue where post editor exists
		if ( in_array( $hook, array( 'post-new.php', 'post.php' ), true ) ) {
			wp_enqueue_style( 'tutor-bilingual-admin', plugin_dir_url( __FILE__ ) . 'assets/admin.css', array(), '1.0.0' );
		}
	}

	public function render_meta_box( $post ) {
		wp_nonce_field( 'tutor_bilingual_save', 'tutor_bilingual_nonce' );
		$hindi = get_post_meta( $post->ID, '_tutor_hindi_content', true );
		$settings = array(
			'textarea_name' => 'tutor_hindi_content',
			'textarea_rows' => 10,
			'teeny'         => false,
			'quicktags'     => true,
		);
		echo '<p style="margin:0 0 8px 0;">' . esc_html__( 'Enter the Hindi version of this course content. On the course page Hindi will display first followed by the original English content.', 'tutor-content-bilingual' ) . '</p>';
		wp_editor( wp_kses_post( $hindi ), 'tutor_hindi_content_editor_' . $post->ID, $settings );
	}

	public function save_hindi_content( $post_id, $post ) {
		// Verify nonce
		if ( ! isset( $_POST['tutor_bilingual_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tutor_bilingual_nonce'] ) ), 'tutor_bilingual_save' ) ) {
			return;
		}

		// Autosave or revisions should be ignored
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permission
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Only for supported post types (use fallback list if plugin attached to 'post')
		if ( ! in_array( $post->post_type, array_merge( $this->post_types, array( 'post' ) ), true ) ) {
			return;
		}

		$hindi = isset( $_POST['tutor_hindi_content'] ) ? wp_kses_post( wp_unslash( $_POST['tutor_hindi_content'] ) ) : '';
		if ( '' === $hindi ) {
			delete_post_meta( $post_id, '_tutor_hindi_content' );
		} else {
			update_post_meta( $post_id, '_tutor_hindi_content', $hindi );
		}
	}

	public function render_bilingual_content( $content ) {
		// Only run on singular course pages in the main query (avoid admin, feeds, etc.)
		if ( is_admin() || ! is_singular() ) {
			return $content;
		}

		global $post;
		if ( empty( $post ) ) {
			return $content;
		}

		// Only for supported post types (allow 'post' fallback)
		if ( ! in_array( $post->post_type, array_merge( $this->post_types, array( 'post' ) ), true ) ) {
			return $content;
		}

		$hindi = get_post_meta( $post->ID, '_tutor_hindi_content', true );
		$english = get_post_field( 'post_content', $post->ID );

		// If no Hindi content present, return original content
		if ( empty( trim( $hindi ) ) ) {
			return $content;
		}

		// Prepare outputs — do not call apply_filters('the_content') on english because that would re-run this filter recursively.
		$hindi_output = '<div class="tutor-bilingual tutor-bilingual-hindi" lang="hi" dir="ltr">' .
						'<h3 style="margin-top:0;">' . esc_html__( 'हिन्दी', 'tutor-content-bilingual' ) . '</h3>' .
						wpautop( do_shortcode( $hindi ) ) .
						'</div>';

		$english_output = '<div class="tutor-bilingual tutor-bilingual-english" lang="en" dir="ltr" style="margin-top:1.5rem;">' .
						  '<h3 style="margin-top:0;">' . esc_html__( 'English', 'tutor-content-bilingual' ) . '</h3>' .
						  wpautop( do_shortcode( $english ) ) .
						  '</div>';

		// Return Hindi first, then English
		$return = $hindi_output . $english_output;

		return $return;
	}
}

new Tutor_Content_Bilingual();