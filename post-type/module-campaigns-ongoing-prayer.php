<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

class DT_Campaign_Ongoing_Prayer extends DT_Module_Base {
    public $module = "campaigns_ongoing_prayer";
    public $post_type = 'campaigns';
    public $magic_link_root = "campaign_app";
    public $magic_link_type = "ongoing";

    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()

    public function __construct() {
        $module_enabled = dt_is_module_enabled( "subscriptions_management", true );
        if ( !$module_enabled ){
            return;
        }
        parent::__construct();
        // register tiles if on details page
//        add_filter( 'dt_details_additional_tiles', [ $this, 'dt_details_additional_tiles' ], 30, 2 );
        add_action( 'dt_details_additional_section', [ $this, 'dt_details_additional_section' ], 30, 2 );
//        add_filter( 'dt_post_update_fields', [ $this, 'dt_post_update_fields' ], 20, 3 );
        add_filter( 'dt_custom_fields_settings', [ $this, 'dt_custom_fields_settings' ], 10, 2 );
//        add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );
    }

    public function dt_custom_fields_settings( $fields, $post_type ){
        if ( $post_type === $this->post_type ){
            $fields["type"]["default"]["ongoing"] = [
                "label" => "Ongoing",
                'description' => __( '247 Prayer for months', 'disciple_tools' ),
                "visibility" => __( "Collaborators", 'disciple_tools' ),
                'color' => "#4CAF50",
            ];
        }
        $key_name = 'public_key';
        if ( method_exists( "DT_Magic_URL", "get_public_key_meta_key" ) ){
            $key_name = DT_Magic_URL::get_public_key_meta_key( "campaign_app", $this->magic_link_type );
        }
        $fields[$key_name] = [
            'name'   => 'Private Key',
            'description' => 'Private key for subscriber access',
            'type'   => 'hash',
            'default' => dt_create_unique_key(),
            'hidden' => true,
            "customizable" => false,
        ];
        return $fields;
    }


    public function dt_details_additional_tiles( $tiles, $post_type = "" ){
        return $tiles;
    }

    public function dt_post_update_fields( $fields, $post_type, $post_id ){
        return $fields;
    }

    public function dt_details_additional_section( $section, $post_type ) {
        if ( $post_type === $this->post_type ) {
            $record = DT_Posts::get_post( $post_type, get_the_ID() );
            if ( !isset( $record['type']['key'] ) || 'ongoing' !== $record['type']['key'] ){
                return;
            }
            if ( $section === "status" ){
                $link = DT_Magic_URL::get_link_url_for_post( $post_type, $record["ID"], $this->magic_link_root, $this->magic_link_type );
                ?>
                <div class="cell small-12 medium-4">
                    <div class="section-subheader">
                        <?php esc_html_e( 'Magic Link', 'disciple_tools' ); ?>
                    </div>
                    <a class="button hollow small" target="_blank" href="<?php echo esc_html( $link ); ?>"><?php esc_html_e( 'View Components', 'disciple_tools' ); ?></a>
                </div>
                <?php
            }
        }

    }

    /**
     * Register REST Endpoints
     * @link https://github.com/DiscipleTools/disciple-tools-theme/wiki/Site-to-Site-Link for outside of wordpress authentication
     */
    public function add_api_routes() {
        $namespace = $this->magic_link_root . '/v1';
        register_rest_route(
            $namespace, '/'.$this->magic_link_type, [
                [
                    'methods'  => WP_REST_Server::CREATABLE,
                    'callback' => [ $this, 'create_subscription' ],
                    'permission_callback' => function( WP_REST_Request $request ){
                        $magic = new DT_Magic_URL( $this->magic_link_root );
                        return $magic->verify_rest_endpoint_permissions_on_post( $request );
                    },
                ],
            ]
        );
        register_rest_route(
            $namespace, '/'.$this->magic_link_type . '/access_account', [
                [
                    'methods'  => WP_REST_Server::CREATABLE,
                    'callback' => [ $this, 'access_account' ],
                    'permission_callback' => function( WP_REST_Request $request ){
                        $magic = new DT_Magic_URL( $this->magic_link_root );
                        return $magic->verify_rest_endpoint_permissions_on_post( $request );
                    },
                ],
            ]
        );
        register_rest_route(
            $namespace, '/'.$this->magic_link_type . '/campaign_info', [
                [
                    'methods'  => "GET",
                    'callback' => [ $this, 'campaign_info' ],
                    'permission_callback' => function( WP_REST_Request $request ){
                        $magic = new DT_Magic_URL( $this->magic_link_root );
                        return $magic->verify_rest_endpoint_permissions_on_post( $request );
                    },
                ],
            ]
        );
        /**
         * route for sending pre sign up email
         */
        register_rest_route(
            $namespace, '/'.$this->magic_link_type . '/send-pre-signup-email', [
                [
                    'methods'  => "GET",
                    'callback' => [ $this, 'send_pre_sign_up_email' ],
                    'permission_callback' => function ( WP_REST_Request $request ){
                        $params = $request->get_params();
                        if ( !isset( $params["campaign_id"] ) ){
                            return false;
                        }
                        return DT_Posts::can_update( "campaigns", $params["campaign_id"] );
                    }
                ],
            ]
        );
    }

    public function send_pre_sign_up_email( WP_REST_Request $request ){
        $params = $request->get_params();
        if ( !isset( $params["campaign_id"] ) ){
             new WP_Error( __METHOD__, "Missing campaign id", [ 'status' => 400 ] );
        }
        $campaign_post_id = sanitize_text_field( wp_unslash( $params['campaign_id'] ) );
        return DT_Prayer_Campaigns_Send_Email::send_pre_sign_up_email( $campaign_post_id );

    }

    public function create_subscription( WP_REST_Request $request ) {
        $params = $request->get_params();
        $params = dt_recursive_sanitize_array( $params );
        $post_id = $params["parts"]["post_id"]; //has been verified in verify_rest_endpoint_permissions_on_post()

        if ( !$post_id || !isset( $params["campaign_id"] ) || (int) $post_id !== (int) $params["campaign_id"] ){
            return new WP_Error( __METHOD__, "Missing post record", [ 'status' => 400 ] );
        }

        // create
        if ( ! isset( $params['email'] ) || empty( $params['email'] ) ) {
            return new WP_Error( __METHOD__, "Missing email", [ 'status' => 400 ] );
        }
        if ( ! isset( $params['selected_times'] ) ) {
            return new WP_Error( __METHOD__, "Missing times and locations", [ 'status' => 400 ] );
        }
        if ( ! isset( $params['timezone'] ) || empty( $params['timezone'] ) ) {
            return new WP_Error( __METHOD__, "Missing timezone", [ 'status' => 400 ] );
        }

        $email = $params['email'];
        $title = $params['name'];
        if ( empty( $title ) ) {
            $title = $email;
        }

        $receive_prayer_time_notifications = isset( $params["receive_prayer_time_notifications"] ) && !empty( $params["receive_prayer_time_notifications"] );

        $existing_posts = DT_Posts::list_posts( "subscriptions", [
            "campaigns" => [ $params["campaign_id"] ],
            "contact_email" => [ $email ]
        ], false );

        if ( (int) $existing_posts["total"] === 1 ){
            $subscriber_id = $existing_posts["posts"][0]["ID"];
            $added_times = DT_Subscriptions::add_subscriber_times( $params["campaign_id"], $subscriber_id, $params['selected_times'] );
            if ( is_wp_error( $added_times ) ){
                return $added_times;
            }
        } else {
            $lang = "en_US";
            if ( isset( $params["parts"]["lang"] ) ){
                $lang = $params["parts"]["lang"];
            }
            $subscriber_id = DT_Subscriptions::create_subscriber( $params["campaign_id"], $email, $title, $params['selected_times'], [
                "receive_prayer_time_notifications" => $receive_prayer_time_notifications,
                "timezone" => $params["timezone"],
                "lang" => $lang,
            ]);
            if ( is_wp_error( $subscriber_id ) ){
                return new WP_Error( __METHOD__, "Could not create record", [ 'status' => 400 ] );
            }
        }

        if ( !empty( $params['selected_times'] ) ){
            $email_sent = DT_Prayer_Campaigns_Send_Email::send_registration( $subscriber_id, $params["campaign_id"] );
        } else {
            $email_sent = DT_Prayer_Campaigns_Send_Email::send_pre_registration( $subscriber_id, $params["campaign_id"] );
        }

        if ( !$email_sent ){
            return new WP_Error( __METHOD__, "Could not sent email confirmation", [ 'status' => 400 ] );
        }

        return true;
    }

    public function access_account( WP_REST_Request $request ) {
        $params = $request->get_params();
        $params = dt_recursive_sanitize_array( $params );

        $post_id = $params["parts"]["post_id"]; //has been verified in verify_rest_endpoint_permissions_on_post()

        if ( !$post_id || !isset( $params["campaign_id"] ) || $post_id !== $params["campaign_id"] ){
            return new WP_Error( __METHOD__, "Missing post record", [ 'status' => 400 ] );
        }

        // @todo insert email reset link
        if ( ! isset( $params['email'], $params['campaign_id'] ) ) {
            return new WP_Error( __METHOD__, "Missing required parameter.", [ 'status' => 400 ] );
        }

        DT_Prayer_Campaigns_Send_Email::send_account_access( $params['campaign_id'], $params['email'] );

        return $params;
    }

    public function campaign_info( WP_REST_Request $request ){
        $params = $request->get_params();
        $params = dt_recursive_sanitize_array( $params );
        $post_id = $params["parts"]["post_id"]; //has been verified in verify_rest_endpoint_permissions_on_post()


        $record = DT_Posts::get_post( "campaigns", $post_id, true, false );
        if ( is_wp_error( $record ) ){
            return;
        }
        $coverage_levels = DT_Campaigns_Base::query_coverage_levels_progress( $post_id );
        $number_of_time_slots = DT_Campaigns_Base::query_coverage_total_time_slots( $post_id );

        $coverage_percentage = $coverage_levels[0]["percent"];
        $second_level = isset( $coverage_levels[1]["percent"] ) ? $coverage_levels[1]["percent"] : "";

        $min_time_duration = 15;
        if ( isset( $record["min_time_duration"]["key"] ) ){
            $min_time_duration = $record["min_time_duration"]["key"];
        }
        $minutes_committed = 0;
        foreach ( $coverage_levels as $level ){
            $minutes_committed += $level["blocks_covered"] * $min_time_duration;
        }

        $locale = $params["parts"]["lang"] ?: "en_US";
        $description = "";
        if ( isset( $record["campaign_strings"][$locale]["campaign_description"] ) ){
            $description = $record["campaign_strings"][$locale]["campaign_description"];
        }
        $grid_id = 1;
        if ( isset( $record['location_grid'] ) && ! empty( $record['location_grid'] ) ) {
            $grid_id = $record['location_grid'][0]['id'];
        }
        $current_commitments = DT_Time_Utilities::get_current_commitments( $post_id );

        $min_time_duration = 15;
        if ( isset( $record["min_time_duration"]["key"] ) ){
            $min_time_duration = $record["min_time_duration"]["key"];
        }
        $field_settings = DT_Posts::get_post_field_settings( "campaigns" );

        $return = [
            "description" => $description,
            "coverage_levels" => $coverage_levels,
            "number_of_time_slots" => $number_of_time_slots,
            "coverage_percentage" => $coverage_percentage,
            'campaign_id' => $post_id,
            'campaign_grid_id' => $grid_id,
            'translations' => [],
            'start_timestamp' => (int) DT_Time_Utilities::start_of_campaign_with_timezone( $post_id ),
            'end_timestamp' => (int) DT_Time_Utilities::end_of_campaign_with_timezone( $post_id ) + 86400,
            'current_commitments' => $current_commitments,
            'slot_length' => (int) $min_time_duration,
            'second_level' => $second_level,
            "duration_options" => $field_settings["duration_options"]["default"],
            'status' => $record["status"]["key"],
            'minutes_committed' => $minutes_committed,
            'prayers_count' => sizeof( $record["subscriptions"] ?? [] ),
        ];
        return apply_filters( "prayer_campaign_info_response", $return );
    }

}
