<?php
/**
 * Plugin Name: Disciple Tools Extension - Static Section
 * Plugin URI: https://github.com/ZumeProject/disciple-tools-static-section
 * Description: This DT extension adds either a top tab of section to metrics and allows you to build iframe or html content into pages.
 * Version:  0.1.0
 * Author URI: https://github.com/DiscipleTools
 * GitHub Plugin URI: https://github.com/ZumeProject/disciple-tools-static-section
 * Requires at least: 4.7.0
 * (Requires 4.7+ because of the integration of the REST API at 4.7 and the security requirements of this milestone version.)
 * Tested up to: 5.3
 *
 * @package Disciple_Tools
 * @link    https://github.com/DiscipleTools
 * @license GPL-2.0 or later
 *          https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly

add_action( 'after_setup_theme', function (){
    $required_dt_theme_version = '0.22.0';
    $wp_theme = wp_get_theme();
    $version = $wp_theme->version;
    /*
     * Check if the Disciple.Tools theme is loaded and is the latest required version
     */
    $is_theme_dt = strpos( $wp_theme->get_template(), "disciple-tools-theme" ) !== false || $wp_theme->name === "Disciple Tools";
    if ( !$is_theme_dt || version_compare( $version, $required_dt_theme_version, "<" ) ) {
        if ( ! is_multisite() ) {
            add_action('admin_notices', function () {
                ?>
                <div class="notice notice-error notice-static_section is-dismissible" data-notice="static_section">
                    Disciple Tools Theme not active or not latest version for Static Section plugin.
                </div><?php
            });
        }

        return false;
    }
    /**
     * Load useful function from the theme
     */
    if ( !defined( 'DT_FUNCTIONS_READY' ) ){
        require_once get_template_directory() . '/dt-core/global-functions.php';
    }
    /*
     * Don't load the plugin on every rest request. Only those with the 'sample' namespace
     */
    $is_rest = dt_is_rest();
    if ( !$is_rest || strpos( dt_get_url_path(), 'static-section' ) != false ){
        return Static_Section::instance();
    }
    return false;
} );


/**
 * Class Static_Section
 */
class Static_Section {

    public $token = 'dt_static_section';
    public $title = 'Static Section';
    public $permissions = 'manage_dt';

    /**  Singleton */
    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    /**
     * Constructor function.
     * @access  public
     * @since   0.1.0
     */
    public function __construct() {
        add_action( 'init', [ $this, 'register_static_section_post_type' ] );
        add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );

        if ( is_admin() ) {
            add_action( "admin_menu", [ $this, "register_menu" ] );
        }

        add_action( 'dt_top_nav_desktop', [ $this, 'top_nav_desktop' ], 50 );
        add_action( 'dt_off_canvas_nav', [ $this, 'dt_off_canvas_nav' ], 50 );

        if ( isset( $_SERVER["SERVER_NAME"] ) ) {
            $url  = ( !isset( $_SERVER["HTTPS"] ) || @( $_SERVER["HTTPS"] != 'on' ) ) ? 'http://'. sanitize_text_field( wp_unslash( $_SERVER["SERVER_NAME"] ) ) : 'https://'. sanitize_text_field( wp_unslash( $_SERVER["SERVER_NAME"] ) );
            if ( isset( $_SERVER["REQUEST_URI"] ) ) {
                $url .= sanitize_text_field( wp_unslash( $_SERVER["REQUEST_URI"] ) );
            }
        }
        $url_path = trim( str_replace( get_site_url(), "", $url ), '/' );

        if ( 'ss' === substr( $url_path, '0', 2 ) ) {

            add_filter( 'dt_templates_for_urls', [ $this, 'add_url' ] ); // add custom URL
            add_filter( 'dt_metrics_menu', [ $this, 'menu' ], 99 );
            add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ], 99 );

        }
    } // End __construct()


    /**
     * Loads the subnav page
     * @since 0.1
     */
    public function register_menu() {
        add_menu_page( 'Extensions (DT)', 'Extensions (DT)', $this->permissions, 'dt_extensions', [ $this, 'extensions_menu' ], 'dashicons-admin-generic', 59 );
        add_submenu_page( 'dt_extensions', $this->title, $this->title, $this->permissions, $this->token, [ $this, 'content' ] );
    }

    /**
     * Menu stub. Replaced when Disciple Tools Theme fully loads.
     */
    public function extensions_menu() {}

    /**
     * Builds page contents
     * @since 0.1
     */
    public function content() {

        if ( !current_user_can( $this->permissions ) ) { // manage dt is a permission that is specific to Disciple Tools and allows admins, strategists and dispatchers into the wp-admin
            wp_die( 'You do not have sufficient permissions to access this page.' );
        }

        ?>
        <div class="wrap">
            <h2><?php echo esc_html( $this->title ) ?></h2>
            <div class="wrap">
                <div id="poststuff">
                    <div id="post-body" class="metabox-holder columns-2">
                        <div id="post-body-content">
                            <!-- Main Column -->

                            <?php $this->main_column(); ?>

                            <!-- End Main Column -->
                        </div><!-- end post-body-content -->
                        <div id="postbox-container-1" class="postbox-container">
                            <!-- Right Column -->

                            <?php $this->right_column(); ?>

                            <!-- End Right Column -->
                        </div><!-- postbox-container 1 -->
                        <div id="postbox-container-2" class="postbox-container">
                        </div><!-- postbox-container 2 -->
                    </div><!-- post-body meta box container -->
                </div><!--poststuff end -->
            </div><!-- wrap end -->
        </div><!-- End wrap -->

        <?php
    }

    public function main_column() {

        $ss_post_id = $this->get_ss_post_id();

        $ss_post_id = $this->process_postback( $ss_post_id );

        $ss_title = $this->get_ss_tab_title( $ss_post_id );

        $nav_meta = $this->get_ss_nav_fields( $ss_post_id );

        ?>
        <form method="post">
            <?php wp_nonce_field('static-section' . get_current_user_id(), 'static-section-nonce', true, true  ) ?>
            <!-- Title -->
            <table class="widefat striped">
                <thead>
                <tr>
                    <th>Tab Title</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td>
                        <input name="tab_title" type="text" value="<?php echo ( ! empty( $ss_title ) ) ? esc_html( $ss_title ) : '' ?>" style="width:100%" />
                    </td>
                    <td style="text-align:right;">
                        <button class="button">save</button>
                        <?php if ( ! empty( $ss_title ) ) : ?>
                            <button class="button" name="clear_title" value="true">clear</button>
                        <?php endif; ?>
                    </td>
                </tr>
                </tbody>
            </table>
            <br>
            <!-- End Box -->

        <?php if ( ! empty( $ss_title ) ) : ?>

            <!-- Menu Items -->
            <table class="widefat striped">
                <thead>
                <tr>
                    <th>Menu</th>
                    <th style="text-align:right;"><a class="button" onclick="add_new_section()">add</a></th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td colspan="2" id="menu-box-wrapper">
                        <!-- Menu Items Container -->
                        <?php
                        if ( ! empty( $nav_meta ) ) {
                            foreach ( $nav_meta as $key => $nav ) {
                                ?>
                                <table class="widefat striped">
                                    <tbody>
                                    <tr>
                                        <td>
                                            Navigation Title<br>
                                            <input name="nav[<?php echo $key ?>][title]" value="<?php echo $nav['title'] ?>" style="width:100%" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            Page Content<br>
                                            <textarea name="nav[<?php echo $key ?>][content]" style="width:100%; height:100px;"><?php echo $nav['content'] ?></textarea>
                                        </td>
                                    </tr>
                                    <tr style="text-align:right;">
                                        <td>
                                            <button class="button">save</button> <button class="button" name="delete" value="<?php echo $key ?>">delete</button>
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                                <br>
                                <?php
                            }
                        }
                        ?>
                    </td>
                </tr>
                </tbody>
            </table>
            <br>

            <?php endif; // tab title ?>

        </form>
        <!-- End Box -->
        <script>
            function add_new_section() {
                let d = new Date();
                let id = d.getMilliseconds() + Math.round((d).getTime() / 1000);

                jQuery('#menu-box-wrapper').append(`
                    <table class="widefat striped" id="${id}">
                        <tbody>
                        <tr>
                            <td>
                                Navigation Title<br>
                                <input name="new_nav[${id}][title]" style="width:100%" />
                            </td>
                        </tr>
                        <tr>
                            <td>
                                Page Content<br>
                                <textarea name="new_nav[${id}][content]" style="width:100%; height:100px;"></textarea>
                            </td>
                        </tr>
                        <tr style="text-align:right;">
                            <td>
                                <button class="button">save</button> <button class="button" onclick="jQuery('#${id}').remove()">delete</button>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                    <br>
                `)
            }
        </script>


        <?php
    }

    public function right_column() {
        ?>
        <!-- Box -->
        <table class="widefat striped">
            <thead>
            <tr><th>Instructions</th></tr>
            </thead>
            <tbody>
            <tr>
                <td>
                    <ul>
                        <li>Purpose:</li>
                        <li>What can be done in boxes:</li>
                    </ul>
                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <?php
    }

    public function get_ss_post_id() {
        // gets the post_id for the static section post. This one record is the primary storage for all sections through the meta data.
        global $wpdb;
        $ss_post_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type = %s AND post_title = %s", $this->token, $this->token ) );
        if ( ! $ss_post_id ) {
            $ss_post_id = wp_insert_post([
                'post_title' => $this->token,
                'post_type' => $this->token,
                'post_status' => $this->token,
                'comment_status' => 'closed',
                'ping_status' => 'closed',
                'meta_input' => [
                    'tab_title' => ''
                ]
            ]);
        }
        return $ss_post_id;
    }

    public function get_ss_tab_title( $ss_post_id = null ) {
        if ( ! $ss_post_id ) {
            $ss_post_id = $this->get_ss_post_id();
        }
        return get_post_meta($ss_post_id, 'tab_title', true );
    }

    public function get_ss_nav_ids(  $ss_post_id = null ) {
        if ( ! $ss_post_id ) {
            $ss_post_id = $this->get_ss_post_id();
        }
        $ss_post_meta = $this->get_ss_post_meta( $ss_post_id );
        $nav_meta = [];

        foreach( $ss_post_meta as $key => $item ) {
            if ( substr($key, 0, 15) === 'nav_menu_title_' ) {
                $i = str_replace('nav_menu_title_', '', $key );
                $nav_meta[] = $i;
            }
            if ( substr($key, 0, 17) === 'nav_menu_content_' ) {
                $i = str_replace('nav_menu_content_', '', $key );
                $nav_meta[] = true;
            }
        }

        return array_unique( $nav_meta );
    }

    public function get_ss_post_meta( $ss_post_id = null ) {
        if ( ! $ss_post_id ) {
            $ss_post_id = $this->get_ss_post_id();
        }

        $ss_post_meta = array_map( function ( $a ) { return maybe_unserialize( $a[0] );
        }, get_post_meta( $ss_post_id ) );

        if ( ! isset( $ss_post_meta['tab_title'] ) ) {
            $ss_post_meta['tab_title'] = '';
        }
        return $ss_post_meta;
    }

    public function get_ss_content( $ss_key ) {
        $ss_key = sanitize_text_field( wp_unslash( $ss_key ) );
        return get_post_meta( $this->get_ss_post_id(), 'nav_menu_content_' . $ss_key, true );
    }

    public function get_ss_nav_fields( $ss_post_id = null ) {
        if ( $ss_post_id ) {
            $ss_post_id = $this->get_ss_post_id();
        }
        $ss_post_meta = $this->get_ss_post_meta( $ss_post_id );
        $nav_meta = [];

        foreach( $ss_post_meta as $key => $item ) {
            if ( substr($key, 0, 15) === 'nav_menu_title_' ) {
                $i = str_replace('nav_menu_title_', '', $key );
                $nav_meta[$i]['title'] = $item;
            }
            if ( substr($key, 0, 17) === 'nav_menu_content_' ) {
                $i = str_replace('nav_menu_content_', '', $key );
                $nav_meta[$i]['content'] = $item;
            }
        }

        return $nav_meta;
    }

    public function process_postback( $ss_post_id ) {
        if ( isset( $_POST['static-section-nonce'] )
            && isset( $_POST['_wp_http_referer' ] )
            && sanitize_text_field( wp_unslash( $_POST['_wp_http_referer' ] ) ) === '/wp-admin/admin.php?page=dt_static_section'
            && wp_verify_nonce( sanitize_text_field( wp_unslash(  $_POST['static-section-nonce'] ) ), 'static-section' . get_current_user_id() )
        ) {
            if ( ! $ss_post_id ) {
                $ss_post_id = $this->get_ss_post_id();
            }
            $ss_post_meta = $this->get_ss_post_meta( $ss_post_id );

            $current_title = $ss_post_meta['tab_title'] ?? '';
            $new_title = sanitize_text_field( wp_unslash( $_POST['tab_title' ] ) ) ?? '';
            if ( $new_title !== $current_title ) {
                update_post_meta( $ss_post_id, 'tab_title', $new_title );
            }
            if ( isset( $_POST['clear_title'] ) ) {
                update_post_meta( $ss_post_id, 'tab_title', false );
            }

            if ( isset( $_POST['new_nav'] ) ) /* process menu items */ {
                $nav = array_map( function ( $a ) {
                    $b = [];
                    foreach ( $a as $key => $value ) {
                        $b[$key] = $value ; // @todo need sanitization
                    }
                    return $b;
                }, $_POST['new_nav'] );


                foreach ( $nav as $key => $item ) {
                    if ( empty( $item['title'] ) && empty( $item['content'] ) ) {
                        continue;
                    }
                    add_post_meta( $ss_post_id, 'nav_menu_title_'.$key, $item['title'], true);
                    add_post_meta( $ss_post_id, 'nav_menu_content_'.$key, $item['content'], true );
                }
            }
            if ( isset( $_POST['nav'] ) ) /* process menu items */ {
                $nav = array_map( function ( $a ) {
                    $b = [];
                    foreach ( $a as $key => $value ) {
                        $b[$key] = $value ; // @todo need sanitization
                    }
                    return $b;
                }, $_POST['nav'] );

                foreach ( $nav as $key => $item ) {
                    if ( empty( $item['title'] ) && empty( $item['content'] ) ) {
                        continue;
                    }
                    update_post_meta( $ss_post_id, 'nav_menu_title_'.$key, $item['title']);
                    update_post_meta( $ss_post_id, 'nav_menu_content_'.$key, $item['content']);
                }
            }
            if ( isset( $_POST['delete'] ) ) /* process menu items */ {
                $delete_id = sanitize_text_field( wp_unslash( $_POST['delete'] ) );

                delete_post_meta( $ss_post_id, 'nav_menu_title_'.$delete_id );
                delete_post_meta( $ss_post_id, 'nav_menu_content_'.$delete_id );
            }
        }

        return $ss_post_id;

    }

    public function register_static_section_post_type() {
        $args = array(
            'public'    => false
        );
        register_post_type( $this->token, $args );
    }

    public function menu( $content ) {
        $nav_menu = $this->get_ss_nav_fields();
        if ( ! empty( $nav_menu ) ) {
            foreach( $nav_menu as $key => $item ) {
                $content .= '<li><a href="'. site_url( '/ss/' ) . '#' . esc_attr( $key ) . '" onclick="load_static_section_content('. esc_attr( $key ).')">' .  esc_html( $item['title'] ) . '</a></li>';
            }
        }

        return $content;
    }

    /**
     * Load scripts for the plugin
     */
    public function scripts() {
        $url_path = trim( parse_url( add_query_arg( array() ), PHP_URL_PATH ), '/' );

        if ( 'ss' === substr( $url_path, '0', 2 ) ) {
            wp_enqueue_script( 'dt_ss',
                 plugin_dir_url(__FILE__) . 'static.js',
                [ 'jquery' ],
                filemtime( plugin_dir_path(__FILE__). 'static.js' ),
                true );

            wp_localize_script(
                'dt_ss',
                'dtStatic',
                [
                    'root' => esc_url_raw( rest_url() ),
                    'plugin_uri' => plugin_dir_url(__FILE__),
                    'nonce' => wp_create_nonce( 'wp_rest' ),
                    'current_user_id' => get_current_user_id(),
                    'nav_ids' => $this->get_ss_nav_ids(),
                ]
            );
        }
    }

    public function add_api_routes() {
        $namespace = 'static-section/v1';

        register_rest_route(
            $namespace, '/content', [
                [
                    'methods'  => WP_REST_Server::CREATABLE,
                    'callback' => [ $this, 'content_endpoint' ],
                ],
            ]
        );
    }


    public function content_endpoint( WP_REST_Request $request ) {
        if ( user_can( get_current_user_id(), 'view_contacts' ) || user_can( get_current_user_id(), 'view_project_metrics' ) ) {
            $params = $request->get_json_params();
            if ( ! isset($params['id'] ) ) {
                return new WP_Error( __METHOD__, "Missing Parameters", [ 'status' => 403 ] );
            }

           return $this->get_ss_content( $params['id'] );
        }

        return new WP_Error( __METHOD__, "Missing Permissions", [ 'status' => 400 ] );
    }

    public function add_url( $template_for_url ) {

        $template_for_url['ss'] = 'template-metrics.php';
        return $template_for_url;
    }

    public function top_nav_desktop() {
        if ( user_can( get_current_user_id(), 'view_contacts' ) || user_can( get_current_user_id(), 'view_project_metrics' ) ) {
            ?><li><a href="<?php echo esc_url( site_url( '/ss/' ) ); ?>"><?php esc_html_e( $this->get_ss_tab_title() ); ?></a></li><?php
        }
    }
    public function dt_off_canvas_nav() {
        if ( user_can( get_current_user_id(), 'view_contacts' ) || user_can( get_current_user_id(), 'view_project_metrics' ) ) {
            ?><li><a href="<?php echo esc_url( site_url( '/ss/' ) ); ?>"><?php esc_html_e( $this->get_ss_tab_title() ); ?></a></li><?php
        }
    }

    /**
     * Method that runs only when the plugin is activated.
     *
     * @since  0.1
     * @access public
     * @return void
     */
    public static function activation() {

    }

    /**
     * Method that runs only when the plugin is deactivated.
     *
     * @since  0.1
     * @access public
     * @return void
     */
    public static function deactivation() {

    }

    /**
     * Magic method to output a string if trying to use the object as a string.
     *
     * @since  0.1
     * @access public
     * @return string
     */
    public function __toString() {
        return $this->token;
    }

    /**
     * Magic method to keep the object from being cloned.
     *
     * @since  0.1
     * @access public
     * @return void
     */
    public function __clone() {
        _doing_it_wrong( __FUNCTION__, esc_html( 'Whoah, partner!' ), '0.1' );
    }

    /**
     * Magic method to keep the object from being unserialized.
     *
     * @since  0.1
     * @access public
     * @return void
     */
    public function __wakeup() {
        _doing_it_wrong( __FUNCTION__, esc_html( 'Whoah, partner!' ), '0.1' );
    }

    /**
     * Magic method to prevent a fatal error when calling a method that doesn't exist.
     *
     * @param string $method
     * @param array $args
     *
     * @return null
     * @since  0.1
     * @access public
     */
    public function __call( $method = '', $args = array() ) {
        // @codingStandardsIgnoreLine
        _doing_it_wrong( __FUNCTION__, esc_html('Whoah, partner!'), '0.1' );
        unset( $method, $args );
        return null;
    }
}

// Register activation hook.
register_activation_hook( __FILE__, [ 'Static_Section', 'activation' ] );
register_deactivation_hook( __FILE__, [ 'Static_Section', 'deactivation' ] );