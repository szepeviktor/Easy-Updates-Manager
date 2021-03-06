<?php
class MPSUM_Admin_Plugins {
	private $slug = '';
	private $tab = 'plugins';
	
	public function __construct( $slug = '' ) {
		$this->slug = $slug;
		//Admin Tab Actions
		add_action( 'mpsum_admin_tab_plugins', array( $this, 'tab_output_plugins' ) );
		add_filter( 'mpsum_plugin_action_links', array( $this, 'plugin_action_links' ), 11, 2 );
		add_action( 'admin_init', array( $this, 'maybe_save_plugin_options' ) );
	}
	
	private function can_update() {
		$core_options = MPSUM_Updates_Manager::get_options( 'core' );
		if ( isset( $core_options[ 'all_updates' ] ) && 'off' == $core_options[ 'all_updates' ] ) {
			return false;
		}
		if ( isset( $core_options[ 'plugin_updates' ] ) && 'off' == $core_options[ 'plugin_updates' ] ) {
			return false;
		}
		return true;
	}
	
	public function maybe_save_plugin_options() {
		if ( !current_user_can( 'update_plugins' ) ) return;
		if ( !isset( $_GET[ 'page' ] ) || $_GET[ 'page' ] != $this->slug ) return;
		if ( !isset( $_GET[ 'tab' ] ) || $_GET[ 'tab' ] != $this->tab ) return;
		if ( !isset( $_REQUEST[ 'action' ] ) ) return;
		
		$action = $_REQUEST[ 'action' ];
		$plugin_disabled = false;
		if ( 'disallow-update-selected' == $action || 'disallow-automatic-selected' == $action ) {
			$plugin_disabled = true;
		}
		
		//Build Query Args
		$paged = isset( $_GET[ 'paged' ] ) ? absint( $_GET[ 'paged' ] ) : false;
		$query_args = array();
		$query_args[ 'page' ] = $this->slug;
		if ( false !== $paged ) {
			$query_args[ 'paged' ] = $paged;	
		}
		if ( false == $plugin_disabled ) {
			$query_args[ 'disabled' ] = 0;
		} else {
			$query_args[ 'disabled' ] = 1;
		}
		$query_args[ 'tab' ] = $this->tab;
		$plugin_status = isset( $_REQUEST[ 'plugin_status' ] ) ? $_REQUEST[ 'plugin_status' ] : false;
		if ( false !== $plugin_status ) {
			$query_args[ 'plugin_status' ] = $plugin_status;	
		}
		
		//Save theme options
		$this->save_plugin_update_options( $action );
				
		//Redirect back to settings screen
		wp_redirect( esc_url_raw( add_query_arg( $query_args, MPSUM_Admin::get_url() ) ) );
		exit;
	}
	
	public function plugin_action_links( $settings, $plugin ) {
		$plugin_options = MPSUM_Updates_Manager::get_options( 'plugins' );
		if ( false !== $key = array_search( $plugin, $plugin_options ) ) {
			$enable_url = add_query_arg( array( 'action' => 'allow-update-selected', '_mpsum' => wp_create_nonce( 'mpsum_plugin_update' ), 'checked' => array( $plugin ) ) );
			$settings[] = sprintf( '<a href="%s">%s</a>', esc_url( $enable_url ), esc_html__( 'Allow Updates', 'stops-core-theme-and-plugin-updates' ) );
		} else {
			//Disable Link
			$disable_url = add_query_arg( array( 'action' => 'disallow-update-selected', '_mpsum' => wp_create_nonce( 'mpsum_plugin_update' ), 'checked' => array( $plugin ) ) );
			$settings[] = sprintf( '<a href="%s">%s</a>', esc_url( $disable_url ), esc_html__( 'Disallow Updates', 'stops-core-theme-and-plugin-updates' ) );
			
			//Automatic Link
			$plugin_automatic_options = MPSUM_Updates_Manager::get_options( 'plugins_automatic' );
			$core_options = MPSUM_Updates_Manager::get_options( 'core' );
			if ( isset( $core_options[ 'automatic_plugin_updates' ] ) && 'individual' == $core_options[ 'automatic_plugin_updates' ] ) {
				if ( in_array( $plugin, $plugin_automatic_options ) ) {
					//Disable Link
					$disable_automatic_url = add_query_arg( array( 'action' => 'disallow-automatic-selected', '_mpsum' => wp_create_nonce( 'mpsum_plugin_update' ), 'checked' => array( $plugin ) ) );
					$settings[] = sprintf( '<a href="%s">%s</a>', esc_url( $disable_automatic_url ), esc_html__( 'Disallow Automatic Updates', 'stops-core-theme-and-plugin-updates' ) );
				} else {
					//Enable Link
					$enable_automatic_url = add_query_arg( array( 'action' => 'allow-automatic-selected', '_mpsum' => wp_create_nonce( 'mpsum_plugin_update' ), 'checked' => array( $plugin ) ) );
					$settings[] = sprintf( '<a href="%s">%s</a>', esc_url( $enable_automatic_url ), esc_html__( 'Enable Automatic Updates', 'stops-core-theme-and-plugin-updates' ) );
				}
			}
		}
		return $settings;	
	}
	
	private function save_plugin_update_options( $action ) {
		//Check capability
		$capability = 'update_plugins'; //On single site, admins can use this, on multisite, only network admins can
		if ( !current_user_can( $capability ) ) return;
		
		$plugins = isset( $_REQUEST[ 'checked' ] ) ? (array) $_REQUEST[ 'checked' ] : array();
		$plugin_options = MPSUM_Updates_Manager::get_options( 'plugins' );
		$plugin_automatic_options = MPSUM_Updates_Manager::get_options( 'plugins_automatic' );
		switch( $action ) {
			case 'disallow-update-selected':
				foreach( $plugins as $plugin ) {
					$plugin_options[] = $plugin;	
					if ( ( $key = array_search( $plugin, $plugin_automatic_options ) ) !== false ) {
						unset( $plugin_automatic_options[ $key ] );
					} 
				}
				
				break;
			case 'allow-update-selected':
				foreach( $plugins as $plugin ) {
					if ( ( $key = array_search( $plugin, $plugin_options ) ) !== false ) {
						unset( $plugin_options[ $key ] );
					}	
				}
				break;
			case 'allow-automatic-selected':
				foreach( $plugins as $plugin ) {
					$plugin_automatic_options[] = $plugin;
					if ( ( $key = array_search( $plugin, $plugin_options ) ) !== false ) {
						unset( $plugin_options[ $key ] );
					}		
				}
				break;
			case 'disallow-automatic-selected':
				foreach( $plugins as $plugin ) {
					if ( ( $key = array_search( $plugin, $plugin_automatic_options ) ) !== false ) {
						unset( $plugin_automatic_options[ $key ] );
					}	
				}
				break;
			default:
				return; 
		}
		//Check nonce
		check_admin_referer( 'mpsum_plugin_update', '_mpsum' );
				
		//Update option
		$plugin_options = array_values( array_unique( $plugin_options ) );
		$plugin_automatic_options = array_values( array_unique( $plugin_automatic_options ) );
		$options = MPSUM_Updates_Manager::get_options();
		$options[ 'plugins' ] = $plugin_options;
		$options[ 'plugins_automatic' ] = $plugin_automatic_options;
		MPSUM_Updates_Manager::update_options( $options ); 
	}
	
	public function tab_output_plugins() {
		if ( isset( $_GET[ 'disabled' ] ) ) {
			$message = __( 'The selected plugins have had their upgrades enabled.', 'stops-core-theme-and-plugin-updates' );
			if ( $_GET[ 'disabled' ] == 1 ) {
				$message = __( 'The selected plugins have had their upgrades disabled.', 'stops-core-theme-and-plugin-updates' );
			}	
			?>
			<div class="updated"><p><strong><?php echo esc_html( $message ); ?></strong></p></div>
			<?php
		}
		
		?>
        <form action="<?php echo esc_url( add_query_arg( array() ) ); ?>" method="post">
	    <?php
		$plugin_status = isset( $_GET[ 'plugin_status' ] ) ? $_GET[ 'plugin_status' ] : false;
		if ( false !== $plugin_status ) {
			printf( '<input type="hidden" name="plugin_status" value="%s" />', esc_attr( $plugin_status ) );	
		}
		wp_nonce_field( 'mpsum_plugin_update', '_mpsum' );
		?>
        <h3><?php esc_html_e( 'Plugin Update Options', 'stops-core-theme-and-plugin-updates' ); ?></h3>
        <?php
	    $core_options = MPSUM_Updates_Manager::get_options( 'core' );
	    if ( false === $this->can_update() ) {
			printf( '<div class="error"><p><strong>%s</strong></p></div>', esc_html__( 'All plugin updates have been disabled.', 'stops-core-theme-and-plugin-updates' ) );
		}
		$plugin_table = new MPSUM_Plugins_List_Table( $args = array( 'screen' => $this->slug, 'tab' => $this->tab ) );
		$plugin_table->prepare_items();
		$plugin_table->views();
		$plugin_table->display();
		?>
        </form>
    <?php
	} //end tab_output_plugins
}