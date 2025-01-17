<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.


class DT_Generic_Porch_Landing extends DT_Magic_Url_Base
{
    public $page_title = 'Set me in the construct()';
    public $root = PORCH_LANDING_ROOT;
    public $type = PORCH_LANDING_TYPE;
    public $post_type = PORCH_LANDING_POST_TYPE;
    public $meta_key = PORCH_LANDING_META_KEY;

    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()

    public function __construct() {
        parent::__construct();
        add_action( 'rest_api_init', [ $this, 'add_endpoints' ] );
        $this->page_title = __( 'Prayer Fuel', 'disciple-tools-prayer-campaigns' ). ' - ' . DT_Porch_Settings::get_field_translation( 'title' );

        /**
         * tests if other URL
         */
        $url = dt_get_url_path();
        $length = strlen( $this->root . '/' . $this->type );
        if ( substr( $url, 0, $length ) !== $this->root . '/' . $this->type ) {
            return;
        }
        /**
         * tests magic link parts are registered and have valid elements
         */
        if ( !$this->check_parts_match( false ) ){
            return;
        }

        // load if valid url
        add_action( 'dt_blank_body', [ $this, 'body' ] ); // body for no post key

        require_once( 'landing-enqueue.php' );
        require_once( 'enqueue.php' );
        add_filter( 'dt_magic_url_base_allowed_css', [ $this, 'dt_magic_url_base_allowed_css' ], 10, 1 );
        add_filter( 'dt_magic_url_base_allowed_js', [ $this, 'dt_magic_url_base_allowed_js' ], 10, 1 );
        add_action( 'wp_enqueue_scripts', [ $this, 'wp_enqueue_scripts' ], 99 );
        add_filter( 'language_attributes', [ $this, 'dt_custom_dir_attr' ] );
    }

    public function dt_custom_dir_attr( $lang ){
        return dt_campaign_custom_dir_attr( $lang );
    }

    public function dt_magic_url_base_allowed_js( $allowed_js ) {
        return array_merge( $allowed_js, DT_Generic_Porch_Landing_Enqueue::load_allowed_scripts() );
    }

    public function dt_magic_url_base_allowed_css( $allowed_css ) {
        return DT_Generic_Porch_Landing_Enqueue::load_allowed_styles();
    }

    public function wp_enqueue_scripts() {
        DT_Generic_Porch_Landing_Enqueue::load_scripts();
    }

    public function body(){
        DT_Generic_Porch::instance()->require_once( 'top-section.php' );
        require_once( 'landing-body.php' );
        require_once( 'post-list-body.php' );
    }

    public function footer_javascript(){
        require_once( 'footer.php' );
    }

    public function header_javascript(){
        require_once( 'header.php' );
    }

    public function add_endpoints() {
        $namespace = $this->root . '/v1/'. $this->type;
        register_rest_route(
            $namespace, 'group-count', [
                [
                    'methods'  => 'POST',
                    'callback' => [ $this, 'record_group_count' ],
                    'permission_callback' => '__return_true',
                ],
            ]
        );
    }

    public function record_group_count( WP_REST_Request $request ){
        $params = $request->get_params();
        $params = dt_recursive_sanitize_array( $params );
        if ( !isset( $params['number'] ) ){
            return false;
        }
        $campaign = DT_Campaign_Settings::get_campaign();
        if ( empty( $campaign ) ){
            return false;
        }
        $campaign_id = $campaign['ID'];
        $args = [
            'parent_id' => $campaign_id,
            'post_id' => 0,
            'post_type' => 'campaigns',
            'type' => 'fuel',
            'subtype' => $campaign['type']['key'],
            'payload' => null,
            'value' => $params['number'],
        ];
        Disciple_Tools_Reports::insert( $args, true, false );

        return $params['number'];
    }
}
DT_Generic_Porch_Landing::instance();
