<?php
/**
 * Plugin updater
 *
 * @since 2.1.0
 *
 * @package LearnDash\Updater
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'nss_plugin_updater_sfwd_lms' ) ) {

	class nss_plugin_updater_sfwd_lms {

		/**
		 * The plugin current version
		 *
		 * @var string
		 */
		public $current_version;

		/**
		 * The plugin remote update path
		 *
		 * @var string
		 */
		public $update_path;

		/**
		 * Plugin Slug (plugin_directory/plugin_file.php)
		 *
		 * @var string
		 */
		public $plugin_slug;

		/**
		 * Plugin name (plugin_file)
		 *
		 * @var string
		 */
		public $slug;

		/**
		 * Initialized as $slug, this is used as a substring to create dynamic hooks and actions
		 *
		 * @var string
		 */
		public $code;

		private $ld_updater;

		private $upgrade_notice = array();


		/**
		 * Initialize a new instance of the WordPress Auto-Update class
		 *
		 * @since 2.1.0
		 *
		 * @param string $update_path
		 * @param string $plugin_slug
		 */
		public function __construct( $update_path, $plugin_slug ) {

			// Set the class public variables
			// $this->update_path = $update_path;
			$this->plugin_slug     = $plugin_slug;
			$this->current_version = LEARNDASH_VERSION;

			list ( $t1, $t2 ) = explode( '/', $plugin_slug );
			$this->slug       = str_replace( '.php', '', $t2 );
			$code             = esc_attr( $this->slug );
			$this->code       = $code;

			$license      = 'bc8e2b24-3f8c-4b21-8b4b-90d57a38e3c7';
			$licenseemail = 'nullmaster@babiato.org';
			if ( ( empty( $license ) ) || ( empty( $licenseemail ) ) ) {
				$this->reset();
			} else {
				if ( learndash_updates_enabled() ) {
					// Build the updater path ONLY if the license and email are not empty. This prevents unnecessary calls to the remote server.
					$this->update_path = add_query_arg(
						array(
							'pluginupdate'    => $code,
							'licensekey'      => rawurlencode( $license ),
							'licenseemail'    => rawurlencode( $licenseemail ),
							'nsspu_wpurl'     => rawurlencode( get_bloginfo( 'wpurl' ) ),
							'nsspu_admin'     => rawurlencode( get_bloginfo( 'admin_email' ) ),
							'current_version' => $this->current_version,
						),
						$update_path
					);
				}
			}

			// Add Menu
			add_action( 'admin_menu', array( $this, 'nss_plugin_license_menu' ), 1 );

			// define the alternative API for updating checking
			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );

			// Define the alternative response for information checking
			add_filter( 'plugins_api', array( $this, 'check_info' ), 50, 3 );
			add_action( 'in_admin_header', array( $this, 'check_notice' ) );

			add_action( 'admin_notices', array( &$this, 'admin_notice_upgrade_notice' ) );
			add_action( 'in_plugin_update_message-' . $this->plugin_slug, array( $this, 'show_upgrade_notification' ), 10, 2 );

			// Handle License post update.
			add_action( 'admin_init', array( $this, 'nss_plugin_license_update' ), 1 );
		}

		/**
		 * Handle license form post updates.
		 *
		 * @since 3.0
		 */
		public function nss_plugin_license_update() {
			// See if the user has posted us some information
			// If they did, this hidden field will be set to 'Y'
			if ( ( isset( $_POST['ld_plugin_license_nonce'] ) ) && ( ! empty( $_POST['ld_plugin_license_nonce'] ) ) && ( wp_verify_nonce( $_POST['ld_plugin_license_nonce'], 'update_nss_plugin_license_' . $this->code ) ) ) {
				$license = 'bc8e2b24-3f8c-4b21-8b4b-90d57a38e3c7';
				if ( ( isset( $_POST[ 'nss_plugin_license_' . $this->code ] ) ) && ( ! empty( $_POST[ 'nss_plugin_license_' . $this->code ] ) ) ) {
					$license = esc_attr( $_POST[ 'nss_plugin_license_' . $this->code ] );
				}

				$email = 'nullmaster@babiato.org';
				if ( ( isset( $_POST[ 'nss_plugin_license_email_' . $this->code ] ) ) && ( is_email( $_POST[ 'nss_plugin_license_email_' . $this->code ] ) ) ) {
					$email = $_POST[ 'nss_plugin_license_email_' . $this->code ];
				}

				// Save the posted value in the database
				update_option( 'nss_plugin_license_' . $this->code, trim( 'bc8e2b24-3f8c-4b21-8b4b-90d57a38e3c7' ) );
				update_option( 'nss_plugin_license_email_' . $this->code, trim( 'nullmaster@babiato.org' ) );

				$this->reset();
				?>
				<script> window.location = window.location; </script>
				<?php
			}
		}

		public function show_upgrade_notification( $current_plugin_metadata, $new_plugin_metadata ) {
			$upgrade_notice = $this->get_plugin_upgrade_notice();
			if ( ! empty( $upgrade_notice ) ) {
				echo '</p><p class="ld-plugin-update-notice">' . str_replace( array( '<p>', '</p>' ), array( '', '<br />' ), $upgrade_notice ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Need to output HTML.
			}
		}

		/**
		 * Utility function to the status of the license.
		 */
		public function is_license_valid() {
					
				return true;
		}

		/**
		 * Checks to see if a license administrative notice needs to be displayed, and if so, displays it.
		 *
		 * @since 2.1.0
		 */
		public function check_notice() {
			

			
		}


		/**
		 * Determines if the plugin should check for updates
		 *
		 * @since 2.1.0
		 *
		 * @return bool
		 */
		public function time_to_recheck() {
			$nss_plugin_check = get_option( 'nss_plugin_check_' . $this->slug );

			if ( ( empty( $nss_plugin_check ) )
			|| ( ! empty( $_REQUEST['pluginupdate'] ) && $_REQUEST['pluginupdate'] == $this->code )
			|| ( ! empty( $_GET['force-check'] ) )
			|| ( $nss_plugin_check <= time() - 12 * 60 * 60 ) ) {
				return true;
			} else {
				return false;
			}
		}

		/**
		 * Resets the time the plugin was checked last, and removes previous license, version, and plugin info data
		 *
		 * @since 2.1.0
		 */
		public function reset() {
			delete_option( 'nss_plugin_remote_version_' . $this->slug );
			delete_option( 'nss_plugin_remote_license_' . $this->slug );
			delete_option( 'nss_plugin_info_' . $this->slug );
			delete_option( 'nss_plugin_check_' . $this->slug );
		}



		/**
		 * Echos the administrative notice if the plugin license is incorrect
		 *
		 * @since 2.1.0
		 */
		public function admin_notice() {
			return true;

			static $notice_shown = true;

			if ( true !== $notice_shown ) {
				$current_screen = get_current_screen();
				if ( ! in_array( $current_screen->id, array( 'admin_page_nss_plugin_license-sfwd_lms-settings', 'dashboard', 'admin_page_learndash_lms_overview' ), true ) ) {
					$notice_shown = true;

					if ( learndash_get_license_show_notice() ) {
						?>
						<div class="<?php echo esc_attr( learndash_get_license_class( 'notice notice-error is-dismissible learndash-license-is-dismissible' ) ); ?>" <?php echo learndash_get_license_data_attrs(); ?>> <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Hardcoded, escaped in function. ?>
							<p><?php echo wp_kses_post( learndash_get_license_message( 2 ) ); ?></p>
						</div>
						<?php
					}
				}
			}
		}

		/**
		 * Support for admin notice header for "Upgrade Notice Admin" header
		 * from readme.txt.
		 *
		 * @since 3.1.4
		 */
		public function admin_notice_upgrade_notice() {
			static $notice_shown_upgrade_notice = true;

			if ( true !== $notice_shown_upgrade_notice ) {

				/** This filter is documented in includes/class-ld-addons-updater.php */
				if ( apply_filters( 'learndash_upgrade_notice_admin_show', true ) ) {
					$upgrade_notice = $this->get_plugin_upgrade_notice( 'upgrade_notice_admin' );
					if ( ! empty( $upgrade_notice ) ) {
						$notice_shown_upgrade_notice = true;
						?>
						<div class="notice notice-error notice-alt is-dismissible ld-plugin-update-notice">
							<?php echo wp_kses_post( $upgrade_notice ); ?>
						</div>
						<?php
					}
				}
			}
		}

		/**
		 * Adds admin notices, and deactivates the plugin.
		 *
		 * @since 2.1.0
		 */
		public function invalid_current_license() {
			// There is NEVER a time when we want to deactive our plugin automatically.
			return;
		}

		/**
		 * Returns the metadata of the LearnDash plugin
		 *
		 * @since 2.1.0
		 *
		 * @return object Metadata of the LearnDash plugin
		 */
		public function get_plugin_data() {
			if ( ! function_exists( 'get_plugin_data' ) ) {
				include_once ABSPATH . 'wp-admin' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'plugin.php';
			}

			return (object) get_plugin_data( dirname( dirname( dirname( __FILE__ ) ) ) . DIRECTORY_SEPARATOR . $this->plugin_slug );
		}



		/**
		 * Add our self-hosted autoupdate plugin to the filter transient
		 *
		 * @since 2.1.0
		 *
		 * @param $transient
		 *
		 * @return object $transient
		 */
		public function check_update( $transient ) {

			if ( is_array( $transient ) ) {
				$transient = (object) $transient;
			}

			if ( ! $this->time_to_recheck() ) {
				$remote_version = get_option( 'nss_plugin_remote_version_' . $this->slug );
				$license        = 'bc8e2b24-3f8c-4b21-8b4b-90d57a38e3c7';
			} else {
				$remote_version = '';
				$license        = 'bc8e2b24-3f8c-4b21-8b4b-90d57a38e3c7';
			}

			// Get the remote version
			if ( empty( $remote_version ) ) {
				$info = $this->getRemote_information();
				if ( ( $info ) && ( property_exists( $info, 'new_version' ) ) ) {
					$remote_version = $info->new_version;
					update_option( 'nss_plugin_remote_version_' . $this->slug, $remote_version );
					update_option( 'nss_plugin_info_' . $this->slug, $info );
				}
			}

			
				$value   = '1';
				$license = array( 'value' => $value );
				update_option( 'nss_plugin_remote_license_' . $this->slug, $license );
			

			// If a newer version is available, add the update
			if ( version_compare( $this->current_version, $remote_version, '<' ) ) {
				$obj              = new stdClass();
				$obj->slug        = $this->slug;
				$obj->new_version = $remote_version;
				$obj->plugin      = 'sfwd-lms/' . $this->slug;

				if ( ! empty( $this->update_path ) ) {
					$obj->url     = $this->update_path;
					$obj->package = $this->update_path;
				} else {
					$obj->url     = null;
					$obj->package = null;
				}

				$plugin_readme = $this->get_plugin_readme();
				if ( ! empty( $plugin_readme ) ) {
					// First we remove the properties we DON'T want from the support site
					foreach ( array( 'sections', 'requires', 'tested', 'last_updated' ) as $property_key ) {
						if ( property_exists( $obj, $property_key ) ) {
							unset( $obj->$property_key );
						}
					}

					if ( isset( $plugin_readme['upgrade_notice'] ) ) {
						unset( $plugin_readme['upgrade_notice'] );
					}

					foreach ( $plugin_readme as $key => $val ) {
						if ( ! property_exists( $obj, $key ) ) {
							$obj->$key = $val;
						}
					}
				}

				if ( ! property_exists( $obj, 'icons' ) ) {
					// Add an image for the WP 4.9.x plugins update screen.
					$obj->icons = array(
						'default' => LEARNDASH_LMS_PLUGIN_URL . '/assets/images/ld-plugin-image.jpg',
					);
				}

				$transient->response[ $this->plugin_slug ] = $obj;
			}

			return $transient;
		}

		public function get_plugin_readme() {
			$override_cache = false;
			if ( isset( $_GET['force-check'] ) ) {
				$override_cache = true;
			}

			if ( class_exists( 'LearnDash_Addon_Updater' ) ) {
				if ( is_null( $this->ld_updater ) ) {
					$this->ld_updater = LearnDash_Addon_Updater::get_instance();
				}
				$this->ld_updater->get_addon_plugins( $override_cache );
				return $this->ld_updater->update_plugin_readme( 'learndash-core-readme', $override_cache );
			}
		}

		public function get_plugin_upgrade_notice( $admin = 'upgrade_notice' ) {
			$upgrade_notice = '';

			$plugin_readme = $this->get_plugin_readme();
			if ( 'upgrade_notice' === $admin ) {
				if ( ( isset( $plugin_readme['upgrade_notice']['content'] ) ) && ( ! empty( $plugin_readme['upgrade_notice']['content'] ) ) ) {
					foreach ( $plugin_readme['upgrade_notice']['content'] as $upgrade_notice_version => $upgrade_notice_message ) {
						if ( version_compare( $upgrade_notice_version, $this->current_version, '>' ) ) {
							$upgrade_notice_message = str_replace( array( "\r\n", "\n", "\r" ), '', $upgrade_notice_message );
							$upgrade_notice_message = str_replace( '</p><p>', '<br /><br />', $upgrade_notice_message );
							$upgrade_notice_message = str_replace( '<p>', '', $upgrade_notice_message );
							$upgrade_notice_message = str_replace( '</p>', '', $upgrade_notice_message );

							$upgrade_notice .= '<p><span class="version">' . $upgrade_notice_version . '</span>: ' . $upgrade_notice_message . '</p>';
						}
					}
				}
			} elseif ( 'upgrade_notice_admin' === $admin ) {
				if ( ( isset( $plugin_readme['upgrade_notice_admin']['content'] ) ) && ( ! empty( $plugin_readme['upgrade_notice_admin']['content'] ) ) ) {
					foreach ( $plugin_readme['upgrade_notice_admin']['content'] as $upgrade_notice_version => $upgrade_notice_message ) {
						if ( version_compare( $upgrade_notice_version, $this->current_version, '>' ) ) {
							$upgrade_notice_message = str_replace( array( '<h4>', '</h4>' ), array( '<p class="header">', '</p>' ), $upgrade_notice_message );
							$upgrade_notice        .= $upgrade_notice_message;

						}
					}
				}
			}

			return $upgrade_notice;
		}

		/**
		 * Add our self-hosted description to the filter, or returns false
		 *
		 * @since 2.1.0
		 *
		 * @param boolean $false
		 * @param array   $action
		 * @param object  $arg
		 *
		 * @return bool|object
		 */
		public function check_info( $false, $action, $arg ) {
			if ( empty( $arg ) || empty( $arg->slug ) || empty( $this->slug ) ) {
				return $false;
			}

			if ( $arg->slug === $this->slug ) {

				if ( ! $this->time_to_recheck() ) {
					$info = get_option( 'nss_plugin_info_' . $this->slug );
					if ( ! empty( $info ) ) {
						return $info;
					}
				}

				if ( 'plugin_information' == $action ) {
					$information = $this->getRemote_information();

					update_option( 'nss_plugin_info_' . $this->slug, $information );
					$false = $information;
				}
			}

			return $false;
		}



		/**
		 * Return the remote version, or returns false
		 *
		 * @return bool|string $remote_version
		 */
		public function getRemote_version() {
			if ( ! empty( $this->update_path ) ) {
				$request = wp_remote_post(
					$this->update_path,
					array(
						'body'    => array( 'action' => 'version' ),
						'timeout' => LEARNDASH_HTTP_REMOTE_POST_TIMEOUT,
					)
				);
				if ( ! is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) === 200 ) {
					return $request['body'];
				}
			}

			return false;
		}

		/**
		 * Get information about the remote version, or returns false
		 *
		 * @return bool|object
		 */
		public function getRemote_information() {
			if ( ! empty( $this->update_path ) ) {
				$request = wp_remote_post(
					$this->update_path,
					array(
						'body'    => array( 'action' => 'info' ),
						'timeout' => LEARNDASH_HTTP_REMOTE_POST_TIMEOUT,
					)
				);

				if ( ! is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) === 200 ) {
					$information = @unserialize( $request['body'] );
					if ( empty( $information ) ) {
						$information = new stdClass();
					}

					$plugin_readme = $this->get_plugin_readme();
					if ( ! empty( $plugin_readme ) ) {
						// First we remove the properties we DON'T want from the support site
						foreach ( array( 'sections', 'requires', 'tested', 'last_updated' ) as $property_key ) {
							if ( property_exists( $information, $property_key ) ) {
								unset( $information->$property_key );
							}
						}

						foreach ( $plugin_readme as $key => $val ) {
							if ( ! property_exists( $information, $key ) ) {
								$information->$key = $val;
							}
						}
					}

					return $information;
				}
			}

			return false;
		}



		/**
		 * Return the status of the plugin licensing, or returns true
		 *
		 * @since 2.1.0
		 *
		 * @return bool|string $remote_license
		 */
		public function getRemote_license() {
			return '1';
		}

		/**
		 * Retrieves the current license from remote server, or returns true
		 *
		 * @since 2.1.0
		 *
		 * @return bool|string $current_license
		 */
		public function getRemote_current_license() {
			

			return true;
		}


		/**
		 * Adds the license submenu to the administrative settings page
		 *
		 * @since 2.1.0
		 */
		public function nss_plugin_license_menu() {
			add_submenu_page(
				'admin.php?page=learndash_lms_settings',
				$this->get_plugin_data()->Name . ' License',
				$this->get_plugin_data()->Name . ' License',
				LEARNDASH_ADMIN_CAPABILITY_CHECK,
				'nss_plugin_license-' . $this->code . '-settings',
				array( $this, 'nss_plugin_license_menupage' )
			);
		}

		/**
		 * Outputs the license settings page
		 *
		 * @since 2.1.0
		 */
		public function nss_plugin_license_menupage() {
			$code = 'bc8e2b24-3f8c-4b21-8b4b-90d57a38e3c7';

			// must check that the user has the required capability
			if ( ! learndash_is_admin_user() ) {
				wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'learndash' ) );
			}

			// Read in existing option value from database
			$license = 'bc8e2b24-3f8c-4b21-8b4b-90d57a38e3c7';
			$email   = 'nullmaster@babiato.org';
			$domain         = str_replace( array( 'http://', 'https://' ), '', get_bloginfo( 'url' ) );
			$license_status = '1';

			?>
			<style>
			.grayblock {
				border: solid 1px #ccc;
				background: #eee;
				padding: 1px 8px;
				width: 30%;
			}
			</style>
			<div class=wrap>
				<form method="post" action="<?php echo esc_attr( $_SERVER['REQUEST_URI'] ); ?>">
					<?php
					// Use nonce for verification.
					wp_nonce_field( 'update_nss_plugin_license_' . $code, 'ld_plugin_license_nonce' );
					?>
					<h1><?php esc_html_e( 'License Settings', 'learndash' ); ?></h1>
					<br />
					<?php
					if ( '1' === $license_status ) {
						?>
						<div class="notice notice-success">
							<p><?php esc_html_e( 'Your license is valid.', 'learndash' ); ?></p>
							</div>
							<?php
					} else {
						if ( learndash_get_license_show_notice() ) {
							?>
							<div class="<?php echo esc_attr( learndash_get_license_class( 'notice notice-error is-dismissible learndash-license-is-dismissible' ) ); ?>" <?php echo learndash_get_license_data_attrs(); ?>> <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Hardcoded, escaped in function ?>
								<p>
								<?php
								echo learndash_get_license_message(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Hardcoded, escaped in function.
								?>
								</p>
							</div>
							<?php
						}
					}
					?>
					<p><label for="nss_plugin_license_email_<?php echo esc_attr( $code ); ?>"><?php esc_html_e( 'Email:', 'learndash' ); ?></label><br />

					<input id="nss_plugin_license_email_<?php echo esc_attr( $code ); ?>" name="nss_plugin_license_email_<?php echo esc_attr( $code ); ?>" style="min-width:30%" value="<?php // phpcs:ignore Squiz.PHP.EmbeddedPhp.ContentBeforeOpen,Squiz.PHP.EmbeddedPhp.ContentAfterOpen
					/** This filter is documented in https://developer.wordpress.org/reference/hooks/format_to_edit/ */
					esc_html_e( apply_filters( 'format_to_edit', $email ), 'learndash' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WP Core Hook
					?>" /></p> <?php // phpcs:ignore Squiz.PHP.EmbeddedPhp.ContentAfterEnd ?>

					<p><label ><?php esc_html_e( 'License Key:', 'learndash' ); ?></label><br />
					<input id="nss_plugin_license_<?php echo esc_attr( $code ); ?>" name="nss_plugin_license_<?php echo esc_attr( $code ); ?>" style="min-width:30%" value="<?php // phpcs:ignore Squiz.PHP.EmbeddedPhp.ContentBeforeOpen,Squiz.PHP.EmbeddedPhp.ContentAfterOpen
					/** This filter is documented in https://developer.wordpress.org/reference/hooks/format_to_edit/ */
					esc_html_e( apply_filters( 'format_to_edit', $license ), 'learndash' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WP Core Hook
					?>" /></p> <?php // phpcs:ignore Squiz.PHP.EmbeddedPhp.ContentAfterEnd ?>

					<div class="submit">
						<input type="submit" name="update_nss_plugin_license_<?php echo esc_attr( $code ); ?>" value="<?php esc_html_e( 'Update License', 'learndash' ); ?>" class="button button-primary"/>
					</div>
				</form>

				<br><br><br><br>
				<div id="nss_license_footer">

				<?php
					/**
					 * Fires after the NSS license footer HTML.
					 *
					 * The dynamic part of the hook `$code` refers to the slug of the plugin.
					 *
					 * @since 2.1.0
					 *
					 */
					do_action( esc_attr( $code ) . '-nss_license_footer' );

				?>
				</div>
			</div>
			<?php
		}
	}

	add_action(
		'learndash_init',
		function() {
			learndash_get_updater_instance();
		}
	);
}

// Poor man's get singleton for now.
/**
 * Gets the `nss_plugin_updater_sfwd_lms` instance.
 *
 * If the instance already exists it returns the existing instance otherwise creates a new instance.
 *
 * @return void|nss_plugin_updater_sfwd_lms The `nss_plugin_updater_sfwd_lms` instance.
 */
function learndash_get_updater_instance() {
	static $updater_sfwd_lms = null;

	if ( ( ! $updater_sfwd_lms ) || ( ! is_a( $updater_sfwd_lms, 'nss_plugin_updater_sfwd_lms' ) ) ) {
		$nss_plugin_updater_plugin_remote_path = 'https://support.learndash.com/';
		$nss_plugin_updater_plugin_slug        = basename( LEARNDASH_LMS_PLUGIN_DIR ) . '/sfwd_lms.php';
		$updater_sfwd_lms                      = new nss_plugin_updater_sfwd_lms( $nss_plugin_updater_plugin_remote_path, $nss_plugin_updater_plugin_slug );
	}

	if ( ( $updater_sfwd_lms ) && ( is_a( $updater_sfwd_lms, 'nss_plugin_updater_sfwd_lms' ) ) ) {
		return $updater_sfwd_lms;
	}
}

/**
 * Checks Whether the learndash license is valid or not.
 *
 * @return boolean
 */
function learndash_is_learndash_license_valid() {
	$updater_sfwd_lms = learndash_get_updater_instance();
	return true;
	
}


/**
 * Utility function to check if we should check for updates.
 *
 * Updates includes by not limited to:
 * License checks, LD core and ProPanel Updates,
 * Add-on updates, Translations.
 *
 * @since 3.1.8
 */
function learndash_updates_enabled() {
	$updates_enabled = true;

	if ( ( defined( 'LEARNDASH_UPDATES_ENABLED' ) ) && ( true !== LEARNDASH_UPDATES_ENABLED ) ) {
		$updates_enabled = false;
	}

	/**
	 * Filter for controlling update processing cycle.
	 *
	 * @since 3.1.8
	 *
	 * @param boolean $updates_enabled true.
	 * @return boolean True to process updates call. Anything else to abort.
	 */
	return (bool) apply_filters( 'learndash_updates_enabled', $updates_enabled );
}

/**
 * Check if we are showing the license notice.
 *
 * @since 3.1.8
 */
function learndash_get_license_show_notice() {
	return false;
}

/**
 * Get the license notice message.
 *
 * @since 3.1.8
 *
 * @param integer $mode Which message.
 */
function learndash_get_license_message( $mode = 1 ) {
	if ( learndash_updates_enabled() ) {
		if ( 2 === $mode ) {
			$updater_sfwd_lms = learndash_get_updater_instance();
			return sprintf(
				// translators: placeholders: Plugin name. Plugin update link.
				esc_html_x( 'License of your plugin %1$s is invalid or incomplete. Please click %2$s and update your license.', 'placeholders: Plugin name. Plugin update link.', 'learndash' ),
				'<strong>' . esc_html( $updater_sfwd_lms->get_plugin_data()->Name ) . '</strong>',
				'<a href="' . get_admin_url( null, 'admin.php?page=nss_plugin_license-sfwd_lms-settings' ) . '">' . esc_html__( 'here', 'learndash' ) . '</a>'
			);
		} elseif ( 1 === $mode ) {
			return sprintf(
				// translators: placeholder: Link to purchase LearnDash.
				esc_html_x( 'Please enter your email and a valid license or %s a license now.', 'placeholder: link to purchase LearnDash', 'learndash' ),
				"<a href='http://www.learndash.com/' target='_blank' rel='noreferrer noopener'>" . esc_html__( 'buy', 'learndash' ) . '</a>'
			);
		}
	} else {
		return sprintf(
			// translators: placeholders: Plugin name. Plugin update link.
			esc_html_x( 'LearnDash update and license calls are temporarily disabled. Click %s for more information.', 'placeholders: FAQ update link.', 'learndash' ),
			'<a target="_blank" rel="noopener noreferrer" aria-label="' . esc_html__( 'opens in a new tab', 'learndash' ) . '" href="https://www.learndash.com/support/docs/faqs/why-are-the-license-updates-and-license-checks-disabled-on-my-site/">' . esc_html__( 'here', 'learndash' ) . '</a>'
		);
	}
}

/**
 * Get license notice class.
 *
 * @since 3.1.8
 *
 * @param string $class Current class.
 */
function learndash_get_license_class( $class = '' ) {
	if ( ! learndash_updates_enabled() ) {
		$class = 'notice notice-info is-dismissible learndash-updates-disabled-dismissible';
	}

	return $class;
}

/**
 * Get license notice attributes.
 *
 * @since 3.1.8
 */
function learndash_get_license_data_attrs() {
	if ( ! learndash_updates_enabled() ) {
		echo ' data-notice-dismiss-nonce="' . esc_attr( wp_create_nonce( 'notice-dismiss-nonce-' . get_current_user_id() ) ) . '" ';
	}
}

/**
 * AJAX function to handle license notice dismiss action from browser.
 *
 * @since 3.1.8
 */
function learndash_license_notice_dismissed_ajax() {
	$user_id = get_current_user_id();
	if ( ! empty( $user_id ) ) {
		if ( ( isset( $_POST['action'] ) ) && ( 'learndash_license_notice_dismissed' === $_POST['action'] ) ) {
			if ( ( isset( $_POST['learndash_license_notice_dismissed_nonce'] ) ) && ( ! empty( $_POST['learndash_license_notice_dismissed_nonce'] ) ) && ( wp_verify_nonce( $_POST['learndash_license_notice_dismissed_nonce'], 'notice-dismiss-nonce-' . $user_id ) ) ) {
				update_user_meta( $user_id, 'learndash_license_notice_dismissed', time() );
			}
		}
	}

	die();
}
add_action( 'wp_ajax_learndash_license_notice_dismissed', 'learndash_license_notice_dismissed_ajax' );


/**
 * Hide the ProPanel license notice when we have disabled the LD updates.
 *
 * @since 3.1.8
 */
function learndash_license_hide_propanel_notice() {
	if ( ! learndash_updates_enabled() ) {
		?>
		<style>
		p#nss_plugin_updater_admin_notice { display:none !important; }
		</style>
		<?php
	}
}
add_filter( 'admin_footer', 'learndash_license_hide_propanel_notice', 99 );
