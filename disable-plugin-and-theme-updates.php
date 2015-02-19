<?php
/*
Plugin Name: Disable Plugin and Theme Updates
Plugin URI: http://wordpress.org/extend/plugins/disable-plugin-and-theme-updates
Description: Disable Plugin and Theme Updates - Works with Multisite
Author: ronalfy
Version: 1.0
Requires at least: 4.0
Author URI: http://www.ronalfy.com
Contributors: ronalfy
Text Domain: disable-plugin-and-theme-updates
Domain Path: /languages
Some code from:  https://wordpress.org/plugins/stops-core-theme-and-plugin-updates/, http://www.skyverge.com/blog/add-custom-bulk-action/
*/ 
class Disable_Plugin_And_Theme_Updates {
	private static $instance = null;
	
	//Singleton
	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	} //end get_instance
	
	private function __construct() {
		add_action( 'init', array( $this, 'init' ), 9 );
		//* Localization Code */
		load_plugin_textdomain( 'disable-plugin-and-theme-updates', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		
	} //end constructor
	
	public function init() {
		
		//Add Bulk Actions to Themes/Plugins Page
		add_action( 'admin_footer-plugins.php', array( $this, 'add_scripts_footer' ) );
		add_action( 'admin_footer-themes.php', array( $this, 'add_scripts_footer' ) );
		
		//Plugin Actions
		add_action( 'load-plugins.php', array( $this, 'bulk_actions_plugins' ) );
		add_filter( 'site_transient_update_plugins', array( $this, 'disable_plugin_notifications' ) );
		add_filter( 'network_admin_plugin_action_links', array( $this, 'plugin_action_links' ), 11, 2 );
		
		//Theme Actions
		add_action( 'load-themes.php', array( $this, 'bulk_actions_themes' ) );
		add_filter( 'site_transient_update_themes', array( $this, 'disable_theme_notifications' ) );
		add_filter( 'theme_action_links', array( $this, 'theme_action_links' ), 11, 2 ); //MS only
		
		//Plugin and Theme actions
		add_filter( 'http_request_args', array( $this, 'http_request_args_remove_plugins_themes' ), 5, 2 );
	} //end init
	
	public function disable_plugin_notifications( $plugins ) {
		if ( !isset( $plugins->response ) || empty( $plugins->response ) ) return $plugins;
		
		$plugin_options = get_site_option( 'dpatu_plugins', array(), false );
		foreach( $plugin_options as $plugin ) {
			unset( $plugins->response[ $plugin ] );
		}
		return $plugins;
	}
	
	public function theme_action_links( $settings, $theme ) {
		$stylesheet = $theme->get_stylesheet();
		$theme_options = get_site_option( 'dpatu_themes', array(), false );
		if ( false !== $key = array_search( $stylesheet, $theme_options ) ) {
			$enable_url = add_query_arg( array( 'action' => 'allow-update-selected', '_dpatu' => wp_create_nonce( 'dpatu_theme_update' ), 'checked' => array( $stylesheet ) ) );
			$settings[] = sprintf( '<a href="%s">%s</a>', esc_url( $enable_url ), esc_html__( 'Allow Updates', 'disable-plugin-and-theme-updates' ) );
		} else {
			$disable_url = add_query_arg( array( 'action' => 'disallow-update-selected', '_dpatu' => wp_create_nonce( 'dpatu_theme_update' ), 'checked' => array( $stylesheet ) ) );
			$settings[] = sprintf( '<a href="%s">%s</a>', esc_url( $disable_url ), esc_html__( 'Disallow Updates', 'disable-plugin-and-theme-updates' ) );
		}
		return $settings;	
	}
	
	public function plugin_action_links( $settings, $plugin ) {
		$plugin_options = get_site_option( 'dpatu_plugins', array(), false );
		if ( false !== $key = array_search( $plugin, $plugin_options ) ) {
			$enable_url = add_query_arg( array( 'action' => 'allow-update-selected', '_dpatu' => wp_create_nonce( 'dpatu_plugin_update' ), 'checked' => array( $plugin ) ) );
			$settings[] = sprintf( '<a href="%s">%s</a>', esc_url( $enable_url ), esc_html__( 'Allow Updates', 'disable-plugin-and-theme-updates' ) );
		} else {
			$disable_url = add_query_arg( array( 'action' => 'disallow-update-selected', '_dpatu' => wp_create_nonce( 'dpatu_plugin_update' ), 'checked' => array( $plugin ) ) );
			$settings[] = sprintf( '<a href="%s">%s</a>', esc_url( $disable_url ), esc_html__( 'Disallow Updates', 'disable-plugin-and-theme-updates' ) );
		}
		return $settings;	
	}
	
	public function disable_theme_notifications( $themes ) {
		if ( !isset( $themes->response ) || empty( $themes->response ) ) return $themes;
		
		$theme_options = get_site_option( 'dpatu_themes', array(), false );
		foreach( $theme_options as $theme ) {
			unset( $themes->response[ $theme ] );
		}
		return $themes;
	}
	
	public function http_request_args_remove_plugins_themes( $r, $url ) {
		if ( 0 !== strpos( $url, 'https://api.wordpress.org/plugins/update-check/1.1/' ) ) return $r;
		
		if ( isset( $r[ 'body' ][ 'plugins' ] ) ) {
			$r_plugins = json_decode( $r[ 'body' ][ 'plugins' ], true );
			$plugin_options = get_site_option( 'dpatu_plugins', array(), false );
			foreach( $plugin_options as $plugin ) {
				unset( $r_plugins[ $plugin ] );
				if ( false !== $key = array_search( $plugin, $r_plugins[ 'active' ] ) ) {
					unset( $r_plugins[ 'active' ][ $key ] );
					$r_plugins[ 'active' ] = array_values( $r_plugins[ 'active' ] );
				}
			}
			$r[ 'body' ][ 'plugins' ] = json_encode( $r_plugins );
		}
		if ( isset( $r[ 'body' ][ 'themes' ] ) ) {
			$r_themes = json_decode( $r[ 'body' ][ 'themes' ], true );
			$theme_options = get_site_option( 'dpatu_themes', array(), false );
			foreach( $theme_options as $theme ) {
				unset( $r_themes[ $theme ] );
			}
			$r[ 'body' ][ 'themes' ] = json_encode( $r_themes );
		}
		return $r;
	}
	
	public function add_scripts_footer() {
		if ( is_multisite() && !is_network_admin() ) return;
		?>
		<script type="text/javascript">
			jQuery(document).ready(function() {
				//Workaround until core allows custom bulk actions
		        jQuery('<option>').val('disallow-update-selected').text('<?php esc_html_e( 'Disallow Updates', 'disable-plugin-and-theme-updates' );?>').appendTo("select[name='action'],select[name='action2']");
		        jQuery('<option>').val('allow-update-selected').text('<?php esc_html_e( 'Allow Updates', 'disable-plugin-and-theme-updates' );?>').appendTo("select[name='action'],select[name='action2']");
	      }); 
		</script>
		<?php
	} //end add_scripts_footer
	
	public function bulk_actions_plugins() {
		//Code stolen from wp-admin/plugins.php
		
		$wp_list_table = _get_list_table( 'WP_Plugins_List_Table' );
		$action = $wp_list_table->current_action();
		if ( false === $action ) return;
		
		//Check capability
		$capability = 'update_plugins'; //On single site, admins can use this, on multisite, only network admins can
		if ( !current_user_can( $capability ) ) return;
		
		$plugins = isset( $_REQUEST[ 'checked' ] ) ? (array) $_REQUEST[ 'checked' ] : array();
		$plugin_options = get_site_option( 'dpatu_plugins', array(), false );
		switch( $action ) {
			case 'disallow-update-selected':
				foreach( $plugins as $plugin ) {
					$plugin_options[] = $plugin;	
				}
				
				break;
			case 'allow-update-selected':
				foreach( $plugins as $plugin ) {
					if ( ( $key = array_search( $plugin, $plugin_options ) ) !== false ) {
						unset( $plugin_options[ $key ] );
					}	
				}
				break;
			default:
				return; 
		}
		//Check nonce
		$wp_nonce = isset( $_REQUEST[ '_wpnonce' ] ) ? $_REQUEST[ '_wpnonce' ] : false;
		if ( false !== $wp_nonce ) {
			check_admin_referer( 'bulk-plugins' );
		} else {
			check_admin_referer( 'dpatu_plugin_update', '_dpatu' );
		}
		
		//Update option
		$plugin_options = array_values( array_unique( $plugin_options ) );
		update_site_option( 'dpatu_plugins', $plugin_options ); 
	}
	
	public function bulk_actions_themes() {
		//Code stolen from wp-admin/network/themes.php
		
		if ( !is_network_admin() ) return;
		
		$wp_list_table = _get_list_table( 'WP_MS_Themes_List_Table' );
		$action = $wp_list_table->current_action();
		if ( false === $action ) return;
		
		//Check capability
		$capability = 'update_themes'; //On single site, admins can use this, on multisite, only network admins can
		if ( !current_user_can( $capability ) ) return;
		
		$themes = isset( $_REQUEST[ 'checked' ] ) ? (array) $_REQUEST[ 'checked' ] : array();		
		$theme_options = get_site_option( 'dpatu_themes', array(), false );
		switch( $action ) {
			case 'disallow-update-selected':
				foreach( $themes as $theme ) {
					$theme_options[] = $theme;	
				}
				
				break;
			case 'allow-update-selected':
				foreach( $themes as $theme ) {
					if ( ( $key = array_search( $theme, $theme_options ) ) !== false ) {
						unset( $theme_options[ $key ] );
					}	
				}
				break;
			default:
				return; 
		}
		//Check nonce
		$wp_nonce = isset( $_REQUEST[ '_wpnonce' ] ) ? $_REQUEST[ '_wpnonce' ] : false;
		if ( false !== $wp_nonce ) {
			check_admin_referer( 'bulk-themes' );
		} else {
			check_admin_referer( 'dpatu_theme_update', '_dpatu' );
		}
		
		//Update option
		$plugin_options = array_values( array_unique( $theme_options ) );
		update_site_option( 'dpatu_themes', $theme_options ); 
	}
} //end class Disable_Plugin_And_Theme_Updates

add_action( 'plugins_loaded', 'dpatu_instantiate' );
function dpatu_instantiate() {
	if ( !is_admin() || defined( 'DOING_AJAX' ) ) return;
	Disable_Plugin_And_Theme_Updates::get_instance();
} //end sce_instantiate