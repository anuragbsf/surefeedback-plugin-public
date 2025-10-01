<?php
/**
 * Plugin Name: SureFeedback Client Site
 * Plugin URI: http://surefeedback.com
 * Description: Collect note-style feedback from your client's websites and sync them with your SureFeedback parent project.
 * Author: Brainstorm Force
 * Author URI: https://www.brainstormforce.com
 * Version: 1.2.10
 *
 * Requires at least: 4.7
 * Tested up to: 6.8
 *
 * Text Domain: surefeedback
 * Domain Path: languages
 *
 * @package SureFeedback
 * @author Brainstorm Force, Andre Gagnon
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Setup Constants before init
 *
 * @since 1.0.0
 */

// Plugin Folder Path.
if ( ! defined( 'SUREFEEDBACK_PLUGIN_DIR' ) ) {
	define( 'SUREFEEDBACK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

// Plugin Folder URL.
if ( ! defined( 'SUREFEEDBACK_PLUGIN_URL' ) ) {
	define( 'SUREFEEDBACK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// Plugin Root File.
if ( ! defined( 'SUREFEEDBACK_PLUGIN_FILE' ) ) {
	define( 'SUREFEEDBACK_PLUGIN_FILE', __FILE__ );
}

// Plugin Version.
if ( ! defined( 'SUREFEEDBACK_VERSION' ) ) {
	define( 'SUREFEEDBACK_VERSION', '1.2.10' );
}

// Plugin Basename.
if ( ! defined( 'SUREFEEDBACK_PLUGIN_BASENAME' ) ) {
	define( 'SUREFEEDBACK_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

// Include autoloader.
require_once SUREFEEDBACK_PLUGIN_DIR . 'includes/class-autoloader.php';


/**
 * Main plugin class
 */
final class SureFeedback {

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	const VERSION = '1.2.10';

	/**
	 * Plugin singleton instance
	 *
	 * @var SureFeedback
	 */
	private static $instance = null;

	/**
	 * Plugin directory path
	 *
	 * @var string
	 */
	private $plugin_path;

	/**
	 * Plugin directory URL
	 *
	 * @var string
	 */
	private $plugin_url;

	/**
	 * Admin menu manager
	 *
	 * @var \SureFeedback\Admin\Admin_Menu
	 */
	private $admin_menu;

	/**
	 * REST controller
	 *
	 * @var \SureFeedback\API\Rest_Controller
	 */
	private $rest_controller;

	/**
	 * SaaS client
	 *
	 * @var \SureFeedback\SaaS\SaaS_Client
	 */
	private $saas_client;

	/**
	 * Environment modes
	 */
	const MODE_DEVELOPMENT = 'development';
	const MODE_PRODUCTION = 'production';

	/**
	 * API URLs
	 */
	const API_URL_LOCAL = 'http://localhost:8000';
	const API_URL_PRODUCTION = 'https://api.surefeedback.com';

	/**
	 * App URLs  
	 */
	const APP_URL_LOCAL = 'http://localhost:3000';
	const APP_URL_PRODUCTION = 'https://app.surefeedback.com';

	/**
	 * Default mode
	 */
	const DEFAULT_MODE = self::MODE_DEVELOPMENT;

	/**
	 * Make sure to whitelist our option names
	 *
	 * @var array
	 */
	protected $whitelist_option_names = array();

	/**
	 * Get instance of this class
	 *
	 * @return SureFeedback
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the plugin
	 */
	public function __construct() {
		$this->setup_properties();
		$this->define_constants();
		$this->init_hooks();
		
		// Initialize components after WordPress is loaded
		add_action('init', array($this, 'includes'), 0);
	}

	/**
	 * Setup plugin properties
	 */
	private function setup_properties() {
		$this->plugin_path = SUREFEEDBACK_PLUGIN_DIR;
		$this->plugin_url  = SUREFEEDBACK_PLUGIN_URL;
	}

	/**
	 * Define plugin constants
	 */
	private function define_constants() {
		// Environment already defined in global constants
	}

	/**
	 * Include required files and initialize components
	 */
	public function includes() {
		// Initialize admin menu
		if ( is_admin() ) {
			$this->admin_menu = new \SureFeedback\Admin\Admin_Menu();
		}

		// Initialize REST controller
		$this->rest_controller = new \SureFeedback\API\Rest_Controller();

		// Initialize SaaS client
		$this->saas_client = new \SureFeedback\SaaS\SaaS_Client();

		// Initialize frontend manager
		if ( ! is_admin() ) {
			$this->frontend_manager = new \SureFeedback\Frontend\Frontend_Manager();
		}
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Load plugin text domain and setup options after WordPress is ready
		add_action('init', array($this, 'load_textdomain'));
		add_action('init', array($this, 'setup_option_whitelist'));

		// Legacy enqueue hooks (for backward compatibility)
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_menu_styles' ) );

		// Whitelist our blog options
		add_filter( 'xmlrpc_blog_options', array( $this, 'whitelist_option' ) );

		// Handle webhook from SureFeedback backend
		add_action( 'rest_api_init', array( $this, 'register_webhook_endpoint' ) );

		// Inject widget script on frontend for connected sites
		add_action( 'wp_footer', array( $this, 'inject_widget_script' ) );
		
		// Handle automatic verification
		add_action( 'surefeedback_auto_verify', array( $this, 'perform_auto_verification' ) );
		add_action( 'surefeedback_hourly_verify', array( $this, 'perform_hourly_verification_update' ) );

		// Update registration option in database for parent site reference
		register_activation_hook( SUREFEEDBACK_PLUGIN_FILE, array( $this, 'register_installation' ) );
		register_deactivation_hook( SUREFEEDBACK_PLUGIN_FILE, array( $this, 'deregister_installation' ) );

		// Redirect to the options page after activating
		add_action( 'activated_plugin', array( $this, 'redirect_options_page' ) );

		// Add settings link to plugins page
		add_filter( 'plugin_action_links_' . plugin_basename( SUREFEEDBACK_PLUGIN_FILE ), array( $this, 'add_settings_link' ) );

		// White label text only on plugins page
		global $pagenow;
		if ( is_admin() && 'plugins.php' === $pagenow ) {
			add_filter( 'gettext', array( $this, 'white_label' ), 20, 3 );
		}
	}

	/**
	 * Load plugin text domain
	 */
	public function load_textdomain() {
		load_plugin_textdomain('surefeedback', false, dirname(plugin_basename(__FILE__)) . '/languages');
	}

	/**
	 * Setup option whitelist
	 */
	public function setup_option_whitelist() {
		$this->whitelist_option_names = array(
			'surefeedback_id'           => array(
				'description'       => __( 'Website project ID.', 'surefeedback' ),
				'sanitize_callback' => 'intval',
			),
			'surefeedback_api_key'      => array(
				'description'       => __( 'Public API key for the script loader.', 'surefeedback' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'surefeedback_access_token' => array(
				'description'       => __( 'Access token to verify access to be able to register and leave comments.', 'surefeedback' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'surefeedback_parent_url'   => array(
				'description'       => __( 'Parent Site URL.', 'surefeedback' ),
				'sanitize_callback' => 'esc_url',
			),
			'surefeedback_signature'    => array(
				'description'       => __( 'Secret signature to verify identity.', 'surefeedback' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'surefeedback_installed'    => array(
				'description'       => __( 'Is the plugin installed?', 'surefeedback' ),
				'sanitize_callback' => 'boolval',
			),
		);
	}

		/**
		 * Get current environment mode
		 *
		 * @return string
		 */
		public function get_environment_mode() {
			return defined( 'SUREFEEDBACK_MODE' ) ? SUREFEEDBACK_MODE : self::DEFAULT_MODE;
		}

		/**
		 * Get API URL based on environment
		 *
		 * @return string
		 */
		public function get_api_url() {
			return $this->get_environment_mode() === self::MODE_PRODUCTION 
				? self::API_URL_PRODUCTION 
				: self::API_URL_LOCAL;
		}

		/**
		 * Get App URL based on environment
		 *
		 * @return string
		 */
		public function get_app_url() {
			return $this->get_environment_mode() === self::MODE_PRODUCTION 
				? self::APP_URL_PRODUCTION 
				: self::APP_URL_LOCAL;
		}

		/**
		 * Apply white label.
		 *
		 * @param string $translated_text White label translated text.
		 * @param string $untranslated_text White label untranslated text.
		 * @param string $domain Plugin domain name.
		 *
		 * @return mixed
		 */
		public function white_label( $translated_text, $untranslated_text, $domain ) {
			global $pagenow;
			if ( ! is_admin() || 'plugins.php' !== $pagenow ) {
				return $translated_text;
			}
			// make the changes to the text.
			if ( 'surefeedback' === $domain ) { // added this check to avoid conflicting other plugins.
				switch ( $untranslated_text ) {
					case 'SureFeedback Client Site':
						$name = get_option( 'surefeedback_plugin_name', false );
						if ( $name ) {
							$translated_text = $name;
						}
						break;
					case 'Collect note-style feedback from your client’s websites and sync them with your SureFeedback parent project.':
						$description = get_option( 'surefeedback_plugin_description', false );
						if ( $description ) {
							$translated_text = $description;
						}
						break;
					case 'Brainstorm Force':
						$author = get_option( 'surefeedback_plugin_author', false );
						if ( $author ) {
							$translated_text = $author;
						}
						break;
					// add more items.
				}
			}

			return $translated_text;
		}

		/**
		 * Add settings link to plugin list table
		 *
		 * @param  array $links Existing links.
		 *
		 * @since 1.0.0
		 * @return array        Modified links
		 */
		public function add_settings_link( $links ) {
			$dashboard_link = '<a href="' . admin_url( 'admin.php?page=surefeedback#dashboard' ) . '">' . __( 'Dashboard', 'surefeedback' ) . '</a>';
			$settings_link = '<a href="' . admin_url( 'admin.php?page=surefeedback#settings' ) . '">' . __( 'Settings', 'surefeedback' ) . '</a>';
			array_push( $links, $dashboard_link, $settings_link );
			return $links;
		}

	

		/**
		 * Redirect to options page.
		 *
		 * @param string $plugin Plugin name.
		 *
		 * @return void
		 */
		public function redirect_options_page( $plugin ) {
			if ( plugin_basename( __FILE__ ) == $plugin ) {
				exit( wp_redirect( admin_url( 'admin.php?page=surefeedback#dashboard' ) ) );
			}
		}

		/**
		 * Add option for when plugin is active
		 *
		 * @return void
		 */
		public function register_installation() {
			update_option( 'surefeedback_installed', true );
		}

		/**
		 * Delete option when deactivated
		 *
		 * @return void
		 */
		public function deregister_installation() {
			delete_option( 'surefeedback_installed' );
		}

		/**
		 * Whitelist our option in xmlrpc
		 *
		 * @param array $options whitelabel options.
		 *
		 * @return array
		 */
		public function whitelist_option( $options ) {
			foreach ( $this->whitelist_option_names as $name => $item ) {
				$options[ $name ] = array(
					'desc'     => esc_html( $item['description'] ),
					'readonly' => false,
					'option'   => $name,
				);
			}

			return $options;
		}


		/**
		 * Get menu icon for SureFeedback
		 *
		 * @return string
		 */
		private function get_menu_icon() {
			// Use the project huddle icon
			$icon_file = SUREFEEDBACK_PLUGIN_DIR . '';
			
			if ( file_exists( $icon_file ) ) {
				return SUREFEEDBACK_PLUGIN_URL . '';
			}
			
			// Fallback to dashicons if icon file doesn't exist
			return 'dashicons-format-chat';
		}

		/**
		 * Main page content (handles both dashboard and settings)
		 *
		 * @return void
		 */
		public function main_page() {
			// Enqueue admin scripts and styles
			$this->enqueue_admin_scripts_dashboard();
			?>
			<div class="wrap">
				<div id="surefeedback-dashboard-app"></div>
			</div>
			<?php
		}

		/**
		 * Dashboard page content (deprecated - kept for backward compatibility)
		 *
		 * @return void
		 */
		public function dashboard_page() {
			$this->main_page();
		}


		/**
		 * Enqueue admin scripts and styles for dashboard
		 *
		 * @return void
		 */
		public function enqueue_admin_scripts_dashboard() {
			$screen = get_current_screen();
			
			// Only load on our dashboard page
			if ( $screen->id !== 'toplevel_page_surefeedback' ) {
				return;
			}
			
			// Check if built React dashboard assets exist
			$js_file = SUREFEEDBACK_PLUGIN_DIR . 'assets/dist/admin.js';
			$css_file = SUREFEEDBACK_PLUGIN_DIR . 'assets/dist/admin.css';

			if ( file_exists( $js_file ) ) {
				wp_enqueue_script(
					'surefeedback-dashboard',
					SUREFEEDBACK_PLUGIN_URL . 'assets/dist/admin.js',
					array(),
					filemtime( $js_file ),
					true
				);
				
				// Add module type for ES6 imports
				add_filter( 'script_loader_tag', array( $this, 'add_module_type_to_dashboard_script' ), 10, 3 );

				// Localize script with WordPress data
				wp_localize_script(
					'surefeedback-dashboard',
					'sureFeedbackAdmin',
					array(
						'rest_url'         => rest_url(),
						'rest_nonce'       => wp_create_nonce( 'wp_rest' ),
						'admin_url'        => admin_url(),
						'ajax_url'         => admin_url( 'admin-ajax.php' ),
						'plugin_url'       => SUREFEEDBACK_PLUGIN_URL,
						'nonce'            => wp_create_nonce( 'surefeedback_admin_nonce' ),
						'installer_nonce'  => wp_create_nonce( 'surefeedback_installer_nonce' ),
						'disconnect_nonce' => wp_create_nonce( 'surefeedback-site-disconnect-nonce' ),
						'showWhiteLabel'   => ! defined( 'PH_HIDE_WHITE_LABEL' ) || true !== PH_HIDE_WHITE_LABEL,
						'verification_status' => get_option( 'surefeedback_verification_status', 'unverified' ),
						'connection_status' => get_option( 'surefeedback_connection_status', 'not_connected' ),
						// Site token data for disconnect functionality
						'site_token'       => get_option( 'surefeedback_site_token', '' ) ?: get_option( 'surefeedback_api_key', '' ),
						'api_token'        => get_option( 'surefeedback_api_key', '' ),
						'site_domain'      => parse_url( home_url(), PHP_URL_HOST ),
						'api_url'          => $this->get_api_url() . '/api/v1',
						'user_token'       => get_option( 'surefeedback_user_token', '' ),
						// Connection data for auth.js
						'connection' => array(
							'app_url'          => $this->get_app_url(),
							'callback_url'     => admin_url('admin.php?page=surefeedback&action=callback'),
							'site_data' => array(
								'domain'           => parse_url(home_url(), PHP_URL_HOST),
								'site_name'        => get_bloginfo('name'),
								'site_url'         => home_url(),
								'admin_email'      => get_option('admin_email'),
								'wp_version'       => get_bloginfo('version'),
								'plugin_version'   => defined('SUREFEEDBACK_VERSION') ? SUREFEEDBACK_VERSION : '1.0.0',
								'language'         => get_locale(),
								'timezone'         => get_option('timezone_string') ?: 'UTC',
								'theme'            => get_template(),
								'active_plugins'   => count(get_option('active_plugins', [])),
							)
						),
						'icon_url'             => SUREFEEDBACK_PLUGIN_URL . 'assets/project-huddle-icon.png',
						'welcome_url'             => SUREFEEDBACK_PLUGIN_URL . 'assets/Video player.png',
						'settings_url'             => SUREFEEDBACK_PLUGIN_URL . 'assets/settings_unselected.svg',
						'settings_selected_url'             => SUREFEEDBACK_PLUGIN_URL . 'assets/settings.svg',
						'label_url'             => SUREFEEDBACK_PLUGIN_URL . 'assets/label.svg',
						'label_selected_url'             => SUREFEEDBACK_PLUGIN_URL . 'assets/label_selected.svg',
						'connection_url'             => SUREFEEDBACK_PLUGIN_URL . 'assets/connection.svg',
						'surerank_icon'    => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/surerank.svg',
						'surecart_icon'    => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/surecart.svg',
						'sureforms_icon'   => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/sureforms.svg',
						'presto_player_icon' => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/pplayer.svg',
						'surefeedback_icon' => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/surefeedback.svg',
						'thumbs' => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/thumbs.svg',
						'rocket' => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/rocket.svg',
						'admin' => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/admin.svg',
						'docs' => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/docs.svg',
						'welcome' => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/welcome.png',
						'configure' => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/configure_banner.png',
						'footer' => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/footer.png',
						'welcome_background' => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/welcome_background.png',
						'suretriggers_icon' => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/OttoKit-Symbol-Primary.svg',
					)
				);
			}

			if ( file_exists( $css_file ) ) {
				wp_enqueue_style(
					'surefeedback-dashboard',
					SUREFEEDBACK_PLUGIN_URL . 'assets/dist/admin.css',
					array(),
					filemtime( $css_file )
				);
			}
			
			// Enqueue admin dashboard custom styles
			$dashboard_css = SUREFEEDBACK_PLUGIN_DIR . 'assets/admin-dashboard.css';
			if ( file_exists( $dashboard_css ) ) {
				wp_enqueue_style(
					'surefeedback-dashboard-custom',
					SUREFEEDBACK_PLUGIN_URL . 'assets/admin-dashboard.css',
					array(),
					filemtime( $dashboard_css )
				);
			}
			
			// Enqueue WordPress admin styles
			wp_enqueue_style( 'common' );
			wp_enqueue_style( 'forms' );
			wp_enqueue_style( 'dashicons' );
		}

		/**
		 * Enqueue admin menu styles globally
		 *
		 * @return void
		 */
		public function enqueue_admin_menu_styles() {
			// Enqueue menu icon styles on all admin pages
			$menu_css = SUREFEEDBACK_PLUGIN_DIR . 'assets/admin-menu.css';
			if ( file_exists( $menu_css ) ) {
				wp_enqueue_style(
					'surefeedback-menu-styles',
					SUREFEEDBACK_PLUGIN_URL . 'assets/admin-menu.css',
					array(),
					filemtime( $menu_css )
				);
			}
			
			// Enqueue admin dashboard styles on all admin pages
			$dashboard_css = SUREFEEDBACK_PLUGIN_DIR . 'assets/admin-dashboard.css';
			if ( file_exists( $dashboard_css ) ) {
				wp_enqueue_style(
					'surefeedback-dashboard-styles',
					SUREFEEDBACK_PLUGIN_URL . 'assets/admin-dashboard.css',
					array(),
					filemtime( $dashboard_css )
				);
			}
		}

		/**
		 * Add module type to dashboard script tag
		 *
		 * @param string $tag    The script tag.
		 * @param string $handle The script handle.
		 * @param string $src    The script source.
		 * @return string
		 */
		public function add_module_type_to_dashboard_script( $tag, $handle, $src ) {
			if ( 'surefeedback-dashboard' === $handle ) {
				$tag = str_replace( '<script ', '<script type="module" ', $tag );
			}
			return $tag;
		}

		/**
         * Hide admin notices on the custom settings page.
         *
         * @since 2.2.1
         * @return void
         */
        public static function hide_admin_notices() {
            $screen                = get_current_screen();
            $pages_to_hide_notices = [
                'edit-elementor-hf',     // Edit screen for elementor-hf post type.
                'elementor-hf',          // New post screen for elementor-hf post type.
            ];
            if ( in_array( $screen->id, $pages_to_hide_notices ) || 'toplevel_page_surefeedback' === $screen->id ) {
                remove_all_actions( 'admin_notices' );
                remove_all_actions( 'all_admin_notices' );
            }
        }

		/**
		 * Add module type to admin script tag
		 *
		 * @param string $tag    The script tag.
		 * @param string $handle The script handle.
		 * @param string $src    The script source.
		 * @return string
		 */
		public function add_module_type_to_admin_script( $tag, $handle, $src ) {
			if ( 'surefeedback-admin' === $handle ) {
				$tag = str_replace( '<script ', '<script type="module" ', $tag );
			}
			return $tag;
		}

		/**
		 * Inject SureFeedback widget script on frontend for connected sites
		 *
		 * @return void
		 */
		public function inject_widget_script() {
			// Only inject on frontend, not in admin
			if ( is_admin() ) {
				return;
			}

			// Check if site is connected and has proper tokens
			$site_id = get_option( 'surefeedback_id' );
			$script_token = get_option( 'surefeedback_script_token' );
			$access_token = get_option( 'surefeedback_access_token' );
			$parent_url = get_option( 'surefeedback_parent_url' );
			$integration_script = get_option( 'surefeedback_integration_script' );

			// Debug: Add console logging to help troubleshoot
			?>
			<script>
			console.log('SureFeedback Debug: Connection check', {
				site_id: '<?php echo esc_js( $site_id ); ?>',
				script_token: '<?php echo esc_js( $script_token ? 'present' : 'missing' ); ?>',
				access_token: '<?php echo esc_js( $access_token ? 'present' : 'missing' ); ?>',
				parent_url: '<?php echo esc_js( $parent_url ); ?>',
				integration_script: '<?php echo esc_js( $integration_script ? 'present' : 'missing' ); ?>'
			});
			</script>
			<?php

			if ( ! $site_id || ! $parent_url ) {
				?>
				<script>
				console.warn('SureFeedback: Widget not loaded - missing connection data');
				</script>
				<?php
				return;
			}

			// Prefer integration script from SaaS platform if available
			// if ( ! empty( $integration_script ) ) {
			// 	// Decode the script properly and output as raw HTML (trusted source - our own SaaS platform)
			// 	$decoded_script = urldecode( $integration_script );
				
			// 	// Debug logging to check what we're outputting
			 	?>
			
				<?php
				
			// 	// Since this is trusted content from our own SaaS platform, output it directly
			// 	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			// 	echo $decoded_script;
			// 	return;
			// }

			// Fallback to manual script injection if no integration script
			$token = $script_token ?: $access_token;
			if ( ! $token ) {
				?>
				<script>
				console.warn('SureFeedback: No valid tokens available');
				</script>
				<?php
				return;
			}

			// Check blacklist (don't show on page builders, etc.)
			// if ( $this->compatiblity_blacklist() ) {
			// 	return;
			// }

			// Inject the SureFeedback widget script - corrected path and configuration
			?>
			<!-- SureFeedback Widget -->
			<script>
			// SureFeedback WordPress Integration Script (Fallback Mode)
			(function (d, t, g, defaultToken, baseUrl, debug, restrictedUrl, requiredToken) {
			'use strict';
			
			// Set token in localStorage before widget-loader.js runs
			// This allows widget-loader.js to find the token since it checks localStorage
			// try {
			// 	localStorage.setItem('surefeedback_api_token', defaultToken);
			// 	localStorage.setItem('surefeedback_token_timestamp', Date.now().toString());
			// } catch (e) {
			// 	console.warn('SureFeedback: Could not set localStorage token:', e);
			// }
			
			var sf = d.createElement(t),
				s = d.getElementsByTagName(t)[0];
			
			sf.type = 'text/javascript';
			sf.async = true;
			sf.defer = true;
			sf.charset = 'UTF-8';
			sf.src = g + '?v=' + (new Date()).getTime();
			sf.setAttribute('data-default-token', defaultToken);
			sf.setAttribute('data-base-url', baseUrl);
			sf.setAttribute('data-debug', debug || 'false');
			sf.setAttribute('data-mode', 'iframe');
			sf.setAttribute('data-platform', 'wordpress');
			sf.setAttribute('data-site-url', window.location.href);
			sf.setAttribute('data-site-origin', window.location.origin);
			sf.setAttribute('data-site-domain', window.location.hostname);
			
			// Optional: Add restricted URL and required token if provided
			if (restrictedUrl) {
				sf.setAttribute('data-restricted-url', restrictedUrl);
			}
			if (requiredToken) {
				sf.setAttribute('data-required-token', requiredToken);
			}
			
			s.parentNode.insertBefore(sf, s);
			console.log('SureFeedback: Token set in localStorage and script injected');
			console.log('SureFeedback: Token:', defaultToken);
			console.log('SureFeedback: Base URL:', baseUrl);
			console.log('SureFeedback: Script src:', sf.src);
			console.log('SureFeedback: LocalStorage token:', localStorage.getItem('surefeedback_api_token'));
			
			// Debug: Check URL parameters that widget-loader.js will look for
			const urlParams = new URLSearchParams(window.location.search);
			console.log('SureFeedback: URL Parameters Check:', {
				api_token: urlParams.get('api_token'),
				magic_token: urlParams.get('magic_token'), 
				surefeedback_token: urlParams.get('surefeedback_token'),
				current_url: window.location.href
			});
			
			// Debug: Set up listener for widget ready event
			window.addEventListener('surefeedback:ready', function() {
				console.log('SureFeedback: Widget ready event fired - widget is fully loaded');
			});
			
			// Debug: Check if widget loads after 3 seconds
			setTimeout(function() {
				console.log('SureFeedback: Status check after 3s:', {
					iframe_ready: window.SureFeedbackIframeReady,
					widget_loaded: window.SureFeedbackWidget ? 'yes' : 'no',
					verification_data: window.SureFeedbackVerification || 'none'
				});
			}, 3000);
			})(document, 'script', '<?php echo esc_js( $parent_url ); ?>/js/widget-loader.js', '<?php echo esc_js( $token ); ?>', '<?php echo esc_js( $parent_url ); ?>', 'true', null, null);
			</script>
			<!-- End SureFeedback Widget -->
			<?php
		}

		/**
		 * Trigger script injection immediately by making internal HTTP request
		 * This forces wp_footer to run and inject the script right after webhook completes
		 * 
		 * @return void
		 */
		public function trigger_script_injection_immediately() {
			// Get the site URL to make internal request
			$site_url = home_url();
			
			// Log the attempt
			error_log( 'SureFeedback: Triggering immediate script injection by requesting: ' . $site_url );
			
			// Make internal HTTP request to trigger wp_footer
			$response = wp_remote_get( $site_url, array(
				'timeout' => 30,
				'blocking' => false, // Don't wait for response, just trigger the request
				'headers' => array(
					'User-Agent' => 'SureFeedback-Auto-Injection/1.0'
				)
			) );
			
			if ( is_wp_error( $response ) ) {
				error_log( 'SureFeedback: Failed to trigger immediate script injection: ' . $response->get_error_message() );
			} else {
				error_log( 'SureFeedback: Successfully triggered immediate script injection request' );
			}
		}

		/**
		 * Auto-verify script with smart scheduling and retry limits
		 * - Schedules verification every 1 minute
		 * - Limits to 10 attempts maximum
		 * - Switches to hourly updates after verification
		 *
		 * @return void
		 */
		public function auto_verify_script() {
			// Reset attempt counter when starting new verification cycle
			update_option( 'surefeedback_verification_attempts', 0 );
			
			// Schedule initial verification after a short delay
			wp_schedule_single_event( time() + 1, 'surefeedback_auto_verify' );
		}

		/**
		 * Perform automatic verification with smart retry logic
		 * - Limits attempts to 10 maximum
		 * - Schedules every 1 minute until verified or limit reached
		 * - Switches to hourly updates after successful verification
		 *
		 * @return void
		 */
		public function perform_auto_verification() {
			// Get current verification status and attempt count
			$current_status = get_option( 'surefeedback_verification_status', 'pending' );
			$attempts = intval( get_option( 'surefeedback_verification_attempts', 0 ) );
			
			// If already verified, schedule hourly update and exit
			if ( $current_status === 'verified' ) {
				// Clear any existing scheduled events
				wp_clear_scheduled_hook( 'surefeedback_auto_verify' );
				
				// Schedule hourly verification update to keep database fresh
				if ( ! wp_next_scheduled( 'surefeedback_hourly_verify' ) ) {
					wp_schedule_event( time() + 3600, 'hourly', 'surefeedback_hourly_verify' );
					error_log( 'SureFeedback: Scheduled hourly verification updates' );
				}
				return;
			}
			
			// Check if we've exceeded maximum attempts (10)
			if ( $attempts >= 10 ) {
				error_log( 'SureFeedback: Maximum verification attempts (10) reached, stopping scheduled verification' );
				update_option( 'surefeedback_verification_status', 'failed' );
				wp_clear_scheduled_hook( 'surefeedback_auto_verify' );
				return;
			}
			
			// Increment attempt counter
			$attempts++;
			update_option( 'surefeedback_verification_attempts', $attempts );
			error_log( "SureFeedback: Verification attempt {$attempts}/10" );
			
			// Perform verification
			$verification_result = $this->verify_script_integration();
			$verified = $verification_result['success'] && $verification_result['verified'];
			
			if ( $verified ) {
				// Success! Update status and switch to hourly updates
				update_option( 'surefeedback_verification_status', 'verified' );
				error_log( "SureFeedback: Verification successful after {$attempts} attempts" );
				
				// Clear minute-based scheduling
				wp_clear_scheduled_hook( 'surefeedback_auto_verify' );
				
				// Schedule hourly updates to keep database fresh
				wp_schedule_event( time() + 3600, 'hourly', 'surefeedback_hourly_verify' );
				error_log( 'SureFeedback: Switched to hourly verification updates' );
			} else {
				// Not yet verified, schedule next attempt in 1 minute
				$status = $verification_result['status'] ?? 'unknown';
				update_option( 'surefeedback_verification_status', 'pending' );
				
				if ( $attempts < 10 ) {
					// Schedule next attempt in 1 minute
					wp_schedule_single_event( time() + 60, 'surefeedback_auto_verify' );
					error_log( "SureFeedback: Verification failed ({$status}), scheduling attempt " . ($attempts + 1) . "/10 in 1 minute" );
				} else {
					error_log( 'SureFeedback: Final verification attempt failed, stopping scheduled verification' );
					update_option( 'surefeedback_verification_status', 'failed' );
				}
			}
		}
		
		/**
		 * Perform hourly verification update to keep database fresh
		 * Called after initial verification succeeds
		 *
		 * @return void
		 */
		public function perform_hourly_verification_update() {
			// Perform verification to get fresh data
			$verification_result = $this->verify_script_integration();
			$verified = $verification_result['success'] && $verification_result['verified'];
			
			if ( $verified ) {
				update_option( 'surefeedback_verification_status', 'verified' );
				error_log( 'SureFeedback: Hourly verification update - still verified' );
			} else {
				// Verification failed, switch back to retry mode
				update_option( 'surefeedback_verification_status', 'pending' );
				update_option( 'surefeedback_verification_attempts', 0 );
				
				// Cancel hourly updates and restart minute-based verification
				wp_clear_scheduled_hook( 'surefeedback_hourly_verify' );
				wp_schedule_single_event( time() + 60, 'surefeedback_auto_verify' );
				
				error_log( 'SureFeedback: Hourly verification failed, switching back to retry mode' );
			}
		}

		/**
		 * Verify script integration with SaaS platform
		 * Uses the widget verification endpoint (no JWT auth required)
		 * Follows the same flow as the SaaS dashboard
		 *
		 * @return array
		 */
		public function verify_script_integration() {
			// Debug: Start logging
			error_log( '=== SureFeedback Verification Debug Start ===' );
			
			$script_token = get_option( 'surefeedback_script_token' );
			$site_token = get_option( 'surefeedback_api_key' ); // This is the site's API token (sf_*)
			$parent_url = get_option( 'surefeedback_parent_url' );
			
			// Debug: Log configuration
			error_log( 'Script Token: ' . ( $script_token ? substr( $script_token, 0, 10 ) . '...' : 'NOT SET' ) );
			error_log( 'Site Token: ' . ( $site_token ? substr( $site_token, 0, 10 ) . '...' : 'NOT SET' ) );
			error_log( 'Parent URL: ' . ( $parent_url ? $parent_url : 'NOT SET' ) );
			
			if ( ( ! $script_token && ! $site_token ) || ! $parent_url ) {
				error_log( 'VERIFICATION FAILED: Missing script token or parent URL' );
				error_log( '=== SureFeedback Verification Debug End ===' );
				return array(
					'success' => false,
					'message' => 'Missing script token or parent URL',
				);
			}

			// Use script_token if available, otherwise fall back to site_token
			// Both should work since they're the same token in our WordPress integration
			$token_to_use = $script_token ?: $site_token;
			error_log( 'Token to use: ' . ( $token_to_use ? substr( $token_to_use, 0, 10 ) . '...' : 'NONE' ) );

			// Call the widget verification endpoint (no JWT auth required)
			// Same endpoint used by the SaaS dashboard polling
			$api_base_url = $parent_url;
			$verification_url = trailingslashit( $api_base_url ) . 'api/v1/admin/verify-integration?script_token=' . $token_to_use;
			
			error_log( 'API Base URL: ' . $api_base_url );
			error_log( 'Verification URL: ' . $verification_url );
			
			$response = wp_remote_get( $verification_url, array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type' => 'application/json',
					'User-Agent' => 'SureFeedback-WordPress-Plugin/1.0',
					'Authorization' => 'Bearer ' . $token_to_use,
				),
			) );

			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
				error_log( 'VERIFICATION FAILED: WP Error - ' . $error_message );
				error_log( '=== SureFeedback Verification Debug End ===' );
				return array(
					'success' => false,
					'message' => 'Failed to connect to verification service: ' . $error_message,
				);
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			$response_body = wp_remote_retrieve_body( $response );
			$response_headers = wp_remote_retrieve_headers( $response );
			$data = json_decode( $response_body, true );
			
			// Debug: Log response details
			error_log( 'Response Code: ' . $response_code );
			error_log( 'Response Headers: ' . wp_json_encode( $response_headers ) );
			error_log( 'Response Body: ' . $response_body );
			error_log( 'Parsed Data: ' . wp_json_encode( $data ) );

			// The widget verification endpoint returns 'integrated' instead of 'success'
			// Same as SaaS dashboard IntegrationStep.tsx line 110-114
			if ( $response_code === 200 && isset( $data['integrated'] ) && $data['integrated'] ) {
				// Success
				error_log( 'VERIFICATION SUCCESS: Script integration verified' );
				
				// Store verification result
				update_option( 'surefeedback_last_verification', current_time( 'mysql' ) );
				update_option( 'surefeedback_verification_status', 'verified' );
				
				// Extract verification details (same structure as SaaS dashboard)
				$verification_data = $data['verification'] ?? array();
				error_log( 'Verification data: ' . wp_json_encode( $verification_data ) );
				error_log( '=== SureFeedback Verification Debug End ===' );
				
				return array(
					'success' => true,
					'verified' => true,
					'message' => $data['message'] ?? 'Integration verified successfully',
					'data' => $data,
					'verification' => $verification_data,
				);
			}

			// Handle specific error cases (same as SaaS dashboard)
			if ( isset( $data['status'] ) ) {
				$error_messages = array(
					'SCRIPT_NOT_LOADED' => 'Script integration is valid but widget is not currently loaded on website',
					'TOKEN_NOT_FOUND' => 'Invalid site token or site is inactive',
					'INVALID_TOKEN_FORMAT' => 'Invalid token format',
					'VERIFICATION_ERROR' => 'Failed to verify script integration',
				);
				
				$message = $error_messages[ $data['status'] ] ?? ( $data['error'] ?? 'Verification failed' );
				
				$failure_reason = sprintf( 
					'Status: %s, Message: %s', 
					$data['status'], 
					$message
				);
				error_log( 'VERIFICATION FAILED: ' . $failure_reason );
				error_log( '=== SureFeedback Verification Debug End ===' );
				
				return array(
					'success' => false,
					'message' => $message,
					'status' => $data['status'],
					'data' => $data,
				);
			}

			$failure_reason = sprintf( 
				'Status: %d, Data: %s', 
				$response_code, 
				wp_json_encode( $data ) 
			);
			error_log( 'VERIFICATION FAILED: ' . $failure_reason );
			error_log( '=== SureFeedback Verification Debug End ===' );

			return array(
				'success' => false,
				'message' => isset( $data['error'] ) ? $data['error'] : 'Verification failed',
				'data' => $data,
			);
		}

		/**
		 * Feedback page - custom settings page content.
		 * Now loads Vue.js admin interface
		 *
		 * @return void
		 */
		public function options_page() {
			// Enqueue admin scripts and styles
			$this->enqueue_admin_scripts();

			// Set initial tab from URL
			$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
			?>
			<div id="surefeedback-admin-app"></div>
			<script>
				// Set initial tab from URL hash or query parameter
				if (window.location.hash) {
					window.location.hash = window.location.hash;
				} else {
					window.location.hash = '<?php echo esc_js( $active_tab ); ?>';
				}
			</script>
			<?php
		}

		/**
		 * Enqueue admin scripts and styles for Vue.js app
		 *
		 * @return void
		 */
		public function enqueue_admin_scripts() {
			$screen = get_current_screen();
			
			// Only load on our settings pages
			$allowed_screens = array(
				'settings_page_feedback-connection-options',
				'surefeedback_page_surefeedback#settings'
			);
			
			if ( ! in_array( $screen->id, $allowed_screens, true ) ) {
				return;
			}

			// Check if built assets exist
			$js_file = SUREFEEDBACK_PLUGIN_DIR . 'assets/dist/admin.js';
			$css_file = SUREFEEDBACK_PLUGIN_DIR . 'assets/dist/admin.css';

			if ( file_exists( $js_file ) ) {
				wp_enqueue_script(
					'surefeedback-admin',
					SUREFEEDBACK_PLUGIN_URL . 'assets/dist/admin.js',
					array(),
					filemtime( $js_file ),
					true
				);
				
				// Add module type for ES6 imports
				add_filter( 'script_loader_tag', array( $this, 'add_module_type_to_admin_script' ), 10, 3 );

				// Localize script with WordPress data
				wp_localize_script(
					'surefeedback-admin',
					'sureFeedbackAdmin',
					array(
						'rest_url'         => rest_url(),
						'rest_nonce'       => wp_create_nonce( 'wp_rest' ),
						'admin_url'        => admin_url(),
						'ajax_url'         => admin_url( 'admin-ajax.php' ),
						'plugin_url'       => SUREFEEDBACK_PLUGIN_URL,
						'nonce'            => wp_create_nonce( 'surefeedback_admin_nonce' ),
						'installer_nonce'  => wp_create_nonce( 'surefeedback_installer_nonce' ),
						'disconnect_nonce' => wp_create_nonce( 'surefeedback-site-disconnect-nonce' ),
						'showWhiteLabel'   => ! defined( 'PH_HIDE_WHITE_LABEL' ) || true !== PH_HIDE_WHITE_LABEL,
						'verification_status' => get_option( 'surefeedback_verification_status', 'unverified' ),
						'connection_status' => get_option( 'surefeedback_connection_status', 'not_connected' ),
						'surerank_icon'    => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/surerank.svg',
						'surecart_icon'    => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/surecart.svg',
						'sureforms_icon'   => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/sureforms.svg',
						'presto_player_icon' => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/pplayer.svg',
						'suretriggers_icon' => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/OttoKit-Symbol-Primary.svg',
					)
				);
			}

			if ( file_exists( $css_file ) ) {
				wp_enqueue_style(
					'surefeedback-admin',
					SUREFEEDBACK_PLUGIN_URL . 'assets/dist/admin.css',
					array(),
					filemtime( $css_file )
				);
			}

			// Enqueue WordPress admin styles that we depend on
			wp_enqueue_style( 'common' );
			wp_enqueue_style( 'forms' );
			
			// Enqueue dashicons for icons
			wp_enqueue_style( 'dashicons' );
		}

		/**
		 * Register webhook endpoint for SureFeedback backend
		 */
		public function register_webhook_endpoint() {
			register_rest_route('surefeedback/v1', '/webhook', array(
				'methods' => 'POST',
				'callback' => array($this, 'handle_webhook_callback'),
				'permission_callback' => array($this, 'verify_webhook_permission'),
			));
		}

		/**
		 * Verify webhook permission - basic security check
		 */
		public function verify_webhook_permission($request) {
			// For now, allow all requests - can add more security later
			return true;
		}

		/**
		 * Handle webhook callback from SureFeedback backend
		 * This processes the same data that was previously sent via URL parameters
		 */
		public function handle_webhook_callback($request) {
			$params = $request->get_params();
			
			// Log webhook received for debugging
			error_log('SureFeedback: Webhook endpoint called with params: ' . print_r($params, true));
			
			// Validate required parameters
			if (empty($params['success']) || empty($params['site_token'])) {
				error_log('SureFeedback: Webhook validation failed - missing required parameters');
				return new WP_Error('missing_params', 'Missing required parameters', array('status' => 400));
			}

			if ($params['success'] === '1') {
				// Connection successful - save the connection data
				$site_token = sanitize_text_field($params['site_token']);
				$script_token = sanitize_text_field($params['script_token'] ?? $site_token);
				$site_id = sanitize_text_field($params['site_id'] ?? '');
				$organization_id = sanitize_text_field($params['organization_id'] ?? '');
				$site_name = sanitize_text_field($params['site_name'] ?? '');
				$domain = sanitize_text_field($params['domain'] ?? '');
				$integration_script = $params['integration_script'] ?? '';
				$script_instructions = $params['script_instructions'] ?? '';
				$user_token = sanitize_text_field($params['user_token'] ?? '');

				// Update options with the connection data
				update_option('surefeedback_site_token', $site_token);
				update_option('surefeedback_script_token', $script_token);
				update_option('surefeedback_id', $site_id); // Match what script injection expects
				update_option('surefeedback_site_id', $site_id); // Keep for compatibility
				update_option('surefeedback_organization_id', $organization_id);
				update_option('surefeedback_site_name', $site_name);
				update_option('surefeedback_domain', $domain);
				update_option('surefeedback_integration_script', $integration_script);
				update_option('surefeedback_script_instructions', $script_instructions);
				update_option('surefeedback_user_token', $user_token);
				update_option('surefeedback_connection_status', 'connected');
				update_option('surefeedback_widget_enabled', true);
				
				// Add required options for script injection
				update_option('surefeedback_access_token', $site_token); // Use site_token as access_token
				update_option('surefeedback_parent_url', 'http://localhost:8000'); // SaaS backend URL

				// Log successful connection with saved options for debugging
				error_log('SureFeedback: Webhook received - site connected successfully');
				error_log('SureFeedback: Saved options - site_id: ' . $site_id . ', script_token: ' . $script_token);
				
				// Trigger immediate script injection by making internal HTTP request
				// This forces wp_footer to run right after webhook completes
				$this->trigger_script_injection_immediately();

				$this->auto_verify_script();
				
				// Trigger the connection updated action for any other listeners
				do_action('surefeedback_connection_updated');

				return rest_ensure_response(array(
					'success' => true,
					'message' => 'Connection data saved successfully',
				));
			} else {
				// Connection failed
				error_log('SureFeedback: Webhook received - connection failed');
				
				return rest_ensure_response(array(
					'success' => false,
					'message' => 'Connection failed',
				));
			}
		}
	}

// Initialize the plugin
SureFeedback::get_instance();
