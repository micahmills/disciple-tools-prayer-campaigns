<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

class DT_Campaign_24Hour_Prayer extends DT_Module_Base
{
    public $module = "campaigns_24hour_prayer";
    public $post_type = 'campaigns';

    public $magic = false;
    public $parts = false;
    public $root = "campaign_app"; // define the root of the url {yoursite}/root/type/key/action
    public $type = '24hour'; // define the type


    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()

    public function __construct() {
        parent::__construct();
        if ( !self::check_enabled_and_prerequisites() ){
            return;
        }
        // register tiles if on details page
        add_filter( 'dt_campaign_types', [ $this, 'dt_campaign_types'], 20, 1 );
        add_filter( 'dt_details_additional_tiles', [ $this, 'dt_details_additional_tiles' ], 30, 2 );
        add_action( 'dt_details_additional_section', [ $this, 'dt_details_additional_section' ], 30, 2 );
        add_action( 'wp_enqueue_scripts', [ $this, 'tile_scripts' ], 100 );

        // register type
        $this->magic = new DT_Magic_URL( $this->root );
        add_filter( 'dt_magic_url_register_types', [ $this, 'register_type' ], 10, 1 );

        // register REST and REST access
        add_filter( 'dt_allow_rest_access', [ $this, 'authorize_url' ], 10, 1 );
        add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );

        // fail if not valid url
        $this->parts = $this->magic->parse_url_parts();
        if ( ! $this->parts ){
            return;
        }

        // fail if does not match type
        if ( $this->type !== $this->parts['type'] ){
            return;
        }

        // load if valid url
        add_action( 'dt_blank_head', [ $this, 'form_head' ] );
        add_action( 'dt_blank_footer', [ $this, 'form_footer' ] );
        if ( $this->magic->is_valid_key_url( $this->type ) && '' === $this->parts['action'] ) {
            add_action( 'dt_blank_body', [ $this, 'form_body' ] );
        } else {
            // fail if no valid action url found
            return;
        }

        // load page elements
        add_action( 'wp_print_scripts', [ $this, 'print_scripts' ], 1500 );
        add_action( 'wp_print_styles', [ $this, 'print_styles' ], 1500 );

        // register url and access
        add_filter( 'dt_templates_for_urls', [ $this, 'register_url' ], 199, 1 );
        add_filter( 'dt_blank_access', [ $this, '_has_access' ] );
        add_filter( 'dt_allow_non_login_access', function(){ return true;
        }, 100, 1 );
    }

    public function dt_details_additional_tiles( $tiles, $post_type = "" ){
        if ( $post_type === 'campaigns' && ! isset( $tiles["apps"] ) ){
            $tiles["apps"] = [ "label" => __( "App Links", 'disciple-tools-campaigns' ) ];
        }
        return $tiles;
    }

    public function dt_details_additional_section( $section, $post_type ) {
        // test if campaigns post type and campaigns_app_module enabled
        if ( $post_type === $this->post_type ) {

            if ( 'apps' === $section ) {
                $record = DT_Posts::get_post( $post_type, get_the_ID() );

                if ( isset( $record['type']['key'] ) && '24hour' === $record['type']['key'] ) {
                    if ( isset( $record['public_key'])) {
                        $key = $record['public_key'];
                    } else {
                        $key = DT_Subscriptions_Base::instance()->create_unique_key();
                        update_post_meta( get_the_ID(), 'public_key', $key );
                    }
                    $link = trailingslashit( site_url() ) . $this->root . '/' . $this->type . '/' . $key;
                    ?>
                    <div class="cell">
                        <div class="section-subheader">
                            24hour Prayer
                        </div>
                        <a class="button hollow small" onclick="copyToClipboard('<?php echo esc_url( $link ) ?>')">Copy Link</a>
                        <a class="button hollow small" onclick="open_app('<?php echo esc_url( $link ) ?>')">Open</a>
                    </div>

                    <script>
                        const copyToClipboard = str => {
                            const el = document.createElement('textarea');
                            el.value = str;
                            el.setAttribute('readonly', '');
                            el.style.position = 'absolute';
                            el.style.left = '-9999px';
                            document.body.appendChild(el);
                            const selected =
                                document.getSelection().rangeCount > 0
                                    ? document.getSelection().getRangeAt(0)
                                    : false;
                            el.select();
                            document.execCommand('copy');
                            document.body.removeChild(el);
                            if (selected) {
                                document.getSelection().removeAllRanges();
                                document.getSelection().addRange(selected);
                            }
                            alert('Copied')
                        };

                        function open_app( link ){
                            jQuery('#modal-large-content').empty().html(`
                            <div class="iframe_container">
                                <span id="campaign-spinner" class="loading-spinner active"></span>
                                <iframe id="campaign-iframe" src="<?php echo esc_url( $link ) ?>" width="100%" height="${window.innerHeight -150}px" style="border:none;">Your browser does not support iframes</iframe>
                            </div>
                            <style>
                            .iframe_container {
                                position: relative;
                            }
                            .iframe_container .loading-spinner {
                                position: absolute;
                                top: 10%;
                                left: 50%;
                            }
                            .iframe_container iframe {
                                background: transparent;
                                z-index: 1;
                            }
                            </style>
                            `)
                            jQuery('#campaign-iframe').on('load', function() {
                                document.getElementById('campaign-spinner').style.display='none';
                            });
                            jQuery('#modal-large').foundation('open')
                        }
                    </script>
                    <?php
                } // end if 24hour prayer

            } // end if apps section


        } // end if campaigns and enabled
    }

    public function dt_campaign_types( $types ) {
        $types['24hour'] = [
            'label' => __( '24hr Prayer Calendar', 'disciple_tools' ),
            'description' => _x( 'No longer active.', 'field description', 'disciple_tools' ),
            'color' => "#4CAF50"
        ];
        return $types;
    }

    public function tile_scripts(){
        if ( is_singular( "campaigns" ) ){
            $magic = new DT_Magic_URL( 'campaigns_app' );
            $types = $magic->list_types();
            $campaigns = $types['campaigns'] ?? [];
            $campaigns['new_key'] = $magic->create_unique_key();

            wp_localize_script( // add object to campaigns-post-type.js
                'dt_campaigns', 'campaigns_campaigns_module', [
                    'campaigns' => $campaigns,
                ]
            );
        }
    }

    public function register_type( array $types ) : array {
        if ( ! isset( $types[$this->root] ) ) {
            $types[$this->root] = [];
        }
        $types[$this->root][$this->type] = [
            'name' => 'Subscriptions',
            'root' => $this->root,
            'type' => $this->type,
            'meta_key' => 'public_key', // coaching-magic_c_key
            'actions' => [
                '' => 'Manage',
            ],
            'post_type' => $this->post_type,
        ];
        return $types;
    }

    public function register_url( $template_for_url ){
        $parts = $this->parts;

        // test 1 : correct url root and type
        if ( ! $parts ){ // parts returns false
            return $template_for_url;
        }

        // test 2 : only base url requested
        if ( empty( $parts['public_key'] ) ){ // no public key present
            $template_for_url[ $parts['root'] . '/'. $parts['type'] ] = 'template-blank.php';
            return $template_for_url;
        }

        // test 3 : no specific action requested
        if ( empty( $parts['action'] ) ){ // only root public key requested
            $template_for_url[ $parts['root'] . '/'. $parts['type'] . '/' . $parts['public_key'] ] = 'template-blank.php';
            return $template_for_url;
        }

        // test 4 : valid action requested
        $actions = $this->magic->list_actions( $parts['type'] );
        if ( isset( $actions[ $parts['action'] ] ) ){
            $template_for_url[ $parts['root'] . '/'. $parts['type'] . '/' . $parts['public_key'] . '/' . $parts['action'] ] = 'template-blank.php';
        }

        return $template_for_url;
    }

    public function _has_access() : bool {
        $parts = $this->parts;

        // test 1 : correct url root and type
        if ( $parts ){ // parts returns false
            return true;
        }

        return false;
    }

    public function print_scripts(){
        // @link /disciple-tools-theme/dt-assets/functions/enqueue-scripts.php
        $allowed_js = [
            'jquery',
            'lodash',
            'moment',
            'datepicker',
            'site-js',
            'shared-functions',
            'mapbox-gl',
            'mapbox-cookie',
            'mapbox-search-widget',
            'google-search-widget',
            'jquery-cookie',
        ];

        global $wp_scripts;

        if ( isset( $wp_scripts ) ){
            foreach ( $wp_scripts->queue as $key => $item ){
                if ( ! in_array( $item, $allowed_js ) ){
                    unset( $wp_scripts->queue[$key] );
                }
            }
        }
        unset( $wp_scripts->registered['mapbox-search-widget']->extra['group'] );
//        dt_write_log($wp_scripts->queue);
//        dt_write_log($wp_scripts);
    }

    public function print_styles(){
        // @link /disciple-tools-theme/dt-assets/functions/enqueue-scripts.php
        $allowed_css = [
            'foundation-css',
            'jquery-ui-site-css',
            'site-css',
            'datepicker-css',
            'mapbox-gl-css'
        ];

        global $wp_styles;
        if ( isset( $wp_styles ) ) {
            foreach ($wp_styles->queue as $key => $item) {
                if ( !in_array( $item, $allowed_css )) {
                    unset( $wp_styles->queue[$key] );
                }
            }
        }
    }

    public function form_head(){
        wp_head(); // styles controlled by wp_print_styles and wp_print_scripts actions
        $this->campaigns_styles_header();
        $this->campaigns_javascript_header();
    }

    public function form_footer(){
        wp_footer(); // styles controlled by wp_print_styles and wp_print_scripts actions
    }

    public function campaigns_styles_header(){
        ?>
        <style>
            body {
                background-color: white;
            }

            #content {
                max-width:100%;
            }
            #title {
                font-size:1.7rem;
                font-weight: 100;
            }
            #top-bar {
                position:relative;
                padding-bottom:1em;
            }
            #add-new {
                padding-top:1em;
            }
            #top-loader {
                position:absolute;
                right:5px;
                top: 5px;
            }
            #wrapper {
                max-width:1000px;
                margin:0 auto;
                padding: .5em;
                background-color: white;
            }
            #value {
                width:50px;
                display:inline;
            }
            #type {
                width:75px;
                padding:5px 10px;
                display:inline;
            }
            #mapbox-search {
                padding:5px 10px;
                border-bottom-color: rgb(138, 138, 138);
            }
            #year {
                width:75px;
                display:inline;
            }
            #new-campaigns-form {
                padding: 1em .5em;
                background-color: #f4f4f4;;
                border: 1px solid #3f729b;
                font-weight: bold;
            }
            .number-input {
                border-top: 0;
                border-left: 0;
                border-right: 0;
                border-bottom: 1px solid gray;
                box-shadow: none;
                background: white;
                text-align:center;
            }
            .stat-heading {
                font-size: 2rem;
            }
            .stat-number {
                font-size: 3.5rem;
            }
            .stat-year {
                font-size: 2rem;
                color: darkgrey;
            }
            /* Chrome, Safari, Edge, Opera */
            input::-webkit-outer-spin-button,
            input::-webkit-inner-spin-button {
                -webkit-appearance: none;
                margin: 0;
            }
            /* Firefox */
            input[type=number] {
                -moz-appearance: textfield;
            }
            .select-input {
                border-top: 0;
                border-left: 0;
                border-right: 0;
                border-bottom: 1px solid gray;
                box-shadow: none;
                background: white;
                text-align:center;
            }
            select::-ms-expand {
                display: none;
            }
            .input-group-field {
                border-top: 0;
                border-left: 0;
                border-right: 0;
                padding:0;
                border-bottom: 1px solid gray;
                box-shadow: none;
                background: white;
            }
            .title-year {
                font-size:3em;
                font-weight: 100;
                color: #0a0a0a;
            }


            /* size specific style section */
            @media screen and (max-width: 991px) {
                /* start of large tablet styles */

            }
            @media screen and (max-width: 767px) {
                /* start of medium tablet styles */

            }
            @media screen and (max-width: 479px) {
                /* start of phone styles */
                body {
                    background-color: white;
                }
            }
        </style>
        <?php
    }

    public function campaigns_javascript_header(){
        $post = DT_Posts::get_post('campaigns', $this->parts['post_id'], true, false );
        if ( is_wp_error( $post ) ) {
            return $post;
        }
        $grid_id = 1;
        if ( isset( $post['location_grid'] ) && ! empty( $post['location_grid'] ) ) {
            $grid_id = $post['location_grid'][0]['id'];
        }
        ?>
        <script>
            var postObject = [<?php echo json_encode([
                'map_key' => DT_Mapbox_API::get_key(),
                'root' => esc_url_raw( rest_url() ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
                'parts' => $this->parts,
                'post' => $post,
                'campaign_id' => $post['ID'],
                'campaign_grid_id' => $grid_id,
                'campaign_times_lists' => DT_Time_Utilities::campaign_times_list( $this->parts['post_id']),
                'translations' => []
            ]) ?>][0]

            /* LOAD */

            /* FUNCTIONS */
            window.load_campaigns = () => {
                let spinner = $('.loading-spinner')
                let content = $('#content')
                let selected_times = $('#selected-prayer-times')
                content.empty()

                jQuery('#custom-style').empty().html(`
                    <style>
                        .day-cell {
                            max-width: 25%;
                            float:left;
                            padding-top: 10px;
                            text-align: center;
                            border: 1px solid grey;
                            font-size:1.2em;
                        }
                        .day-cell:hover {
                            background: lightblue;
                            border: 1px solid darkslategrey;
                            cursor:pointer;
                        }
                        .progress-bar {
                            height:10px;
                        }
                        .selected-hour {
                            max-width: 100%;
                            padding-top: 10px;
                            border: 1px solid grey;
                            font-size:1.2em;
                        }
                        .no-selection {
                            max-width: 100%;
                            padding-top: 10px;
                            border: 1px solid grey;
                            font-size:1.2em;
                        }
                        .remove-selection {
                            float: right;
                            color: red;
                            cursor:pointer;
                        }
                        #email {
                            display:none;
                        }
                    </style>
                    `)

                let list = ''
                jQuery.each( postObject.campaign_times_lists, function(i,v){
                    list += `<div class="cell day-cell" data-time="${v.key}" data-percent="${v.percent}" data-location="${postObject.campaign_grid_id}"><div>${v.formatted}</div><div class="progress-bar" data-percent="${v.percent}" style="background: dodgerblue; width:0"></div></div>`
                })
                content.html(`<div class="grid-x" id="selection-grid-wrapper">${list}</div>`)
                let percent = 0
                jQuery.each( jQuery('.progress-bar'), function(i,v){
                    percent = jQuery(this).data('percent')
                    jQuery(this).animate({
                        width: percent + '%'
                    })
                })

                // listen for click
                jQuery('.day-cell').on('click', function(e){
                    let id = jQuery(this).data('time')

                    let list_title = jQuery('#list-modal-title')
                    list_title.empty().html(`<h2 class="section_title">${postObject.campaign_times_lists[id].formatted}</h2>`)
                    let list_content = jQuery('#list-modal-content')
                    let row = '<div class="grid-x">'
                    jQuery.each(postObject.campaign_times_lists[id].hours, function(i,v){
                        if ( v.subscribers > 0) {
                            row += `<div class="cell day-cell time-cell" style="background-color:lightblue" id="${v.key}" data-time="${v.key}" data-location="${postObject.campaign_grid_id}" data-label="${postObject.campaign_times_lists[id].formatted} at ${v.formatted}">${v.formatted} (${v.subscribers} praying) </div>`
                        } else {
                            row += `<div class="cell day-cell time-cell" id="${v.key}" data-time="${v.key}" data-location="${postObject.campaign_grid_id}" data-label="${postObject.campaign_times_lists[id].formatted} at ${v.formatted}">${v.formatted}</div>`
                        }

                    })
                    row += `</div>`
                    list_content.empty().html(row)

                    jQuery('.time-cell').on('click', function(i,v){
                        jQuery('#no-selections').remove()

                        let selected_time_id = jQuery(this).data('time')
                        let selected_time_label = jQuery(this).data('label')
                        let selected_location_id = jQuery(this).data('location')

                        if( 'rgb(0, 128, 0)' === jQuery(this).css('background-color') ) {
                            jQuery(this).css('background-color', 'white')
                            jQuery('#selected-'+selected_time_id).remove()
                        } else {
                            jQuery(this).css('background-color', 'green')
                            selected_times.append(`<div id="selected-${selected_time_id}" class="cell selected-hour" data-time="${selected_time_id}" data-location="${selected_location_id}">${selected_time_label} <i class="fi-x remove-selection" onclick="jQuery('#selected-${selected_time_id}').remove()"></i></div>`)
                        }

                        if ( 0 === jQuery('#selected-prayer-times div').length ){
                            selected_times.html(`<div class="cell selected-hour" id="no-selections">No Selections</div>`)
                        }

                    })

                    jQuery('#list-modal').foundation('open')
                })

                spinner.removeClass('active')
            }

            window.create_subscription = () => {
                let spinner = $('.loading-spinner')
                spinner.addClass('active')
                let submit_button = jQuery('#submit-form')
                submit_button.prop('disabled', true)

                let honey = jQuery('#email').val()
                if ( honey ) {
                    jQuery('#next_1').html('Shame, shame, shame. We know your name ... ROBOT!').prop('disabled', true )
                    window.spinner.hide()
                    return;
                }

                let name_input = jQuery('#name')
                let name = name_input.val()
                if ( ! name ) {
                    jQuery('#name-error').show()
                    spinner.hide()
                    name_input.focus(function(){
                        jQuery('#name-error').hide()
                    })
                    submit_button.prop('disabled', false)
                    return;
                }

                let email_input = jQuery('#e2')
                let email = email_input.val()
                if ( ! email ) {
                    jQuery('#email-error').show()
                    spinner.hide()
                    email_input.focus(function(){
                        jQuery('#email-error').hide()
                    })
                    submit_button.prop('disabled', false)
                    return;
                }

                let selected_times_divs = jQuery(`.selected-hour`)
                let selected_times = []
                if ( selected_times_divs.length === 0 ) {
                    jQuery('#selection-error').show()
                    spinner.hide()
                    jQuery('#selection-grid-wrapper').click(function(){
                        jQuery('#selection-error').hide()
                    })
                    submit_button.prop('disabled', false)
                    return;
                } else {
                    jQuery.each(selected_times_divs, function(i,v){
                        selected_times.push({ time: jQuery(this).data('time'), grid_id: jQuery(this).data('location') } )
                    })
                }

                let data = {
                    name: name,
                    email: email,
                    selected_times: selected_times,
                    campaign_id: postObject.campaign_id
                }

                jQuery.ajax({
                    type: "POST",
                    data: JSON.stringify(data),
                    contentType: "application/json; charset=utf-8",
                    dataType: "json",
                    url: postObject.root + postObject.parts.root + '/v1/' + postObject.parts.type,
                    beforeSend: function (xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', postObject.nonce )
                    }
                })
                    .done(function(data){
                        console.log(data)
                        window.location = window.location.origin + '/subscriptions_app/manage/' + data
                        spinner.removeClass('active')
                    })
                    .fail(function(e) {
                        console.log(e)
                        $('#error').html(e)
                        spinner.removeClass('active')
                    })
            }

            jQuery(document).ready(function($){
                clearInterval(window.fiveMinuteTimer)
                jQuery('#submit-form').on('click', function(){
                    window.create_subscription()
                })
            })
        </script>
        <?php
        return true;
    }

    public function form_body(){

        // FORM BODY
        ?>
        <div id="custom-style"></div>
        <div id="wrapper">
            <div class="center"><h2>Enter Contact Info</h2></div>
            <div class="grid-x ">
                <div class="cell">
                    <span id="name-error" class="form-error">
                        You're name is required.
                    </span>
                    <label for="name">Name<br>
                        <input type="text" name="name" id="name" placeholder="Name" required/>
                    </label>
                </div>
                <div class="cell">
                    <span id="email-error" class="form-error">
                        You're email is required.
                    </span>
                    <label for="email">Email<br>
                        <input type="email" name="email" id="email" placeholder="Email" />
                        <input type="email" name="e2" id="e2" placeholder="Email" required />
                    </label>
                </div>
            </div>
            <div class="center"><h2>Select Prayer Times</h2></div>
            <div class="grid-x" style=" height: inherit !important;">
                <div class="cell center" id="bottom-spinner"><span class="loading-spinner active"></span></div>
                <div class="cell" id="content"><div class="center">... loading</div></div>
                <div class="cell grid" id="error"></div>
            </div>
            <br>
            <div class="center"><h2>Confirm Selections</h2></div>
            <span id="selection-error" class="form-error">
                You must select at least one time slot above.
            </span>
            <div id="selected-prayer-times" class="grid-x grid-padding-x grid-padding-y">
                <div class="cell no-selection" id="no-selections">No Selections</div>
            </div>
            <br>
            <div class="grid-x grid-padding-x grid-padding-y">
                <div class="cell center">
                    <input type="checkbox" id="receive_campaign_emails" name="receive_campaign_notifications" checked /> <label for="receive_campaign_emails">Receive Prayer Time Notifications</label>
                    <input type="checkbox" id="receive_campaign_emails" name="receive_campaign_emails" checked /> <label for="receive_campaign_emails">Receive Prayer Emails</label>
                </div>
                <div class="cell center">
                    <button class="button large" id="submit-form">Submit Your Prayer Commitment</button>
                </div>
            </div>
            <div class="reveal small" id="list-modal"  data-v-offset="0" data-reveal>
                <h3 id="list-modal-title"></h3>
                <div id="list-modal-content"></div>
                <br>
                <div class="center">
                    <button class="button hollow large" data-close="" aria-label="Close modal" type="button">
                        <span aria-hidden="true">Close</span>
                    </button>
                </div>
                <button class="close-button" data-close="" aria-label="Close modal" type="button">
                    Close <span aria-hidden="true">×</span>
                </button>
            </div>
        </div> <!-- form wrapper -->
        <script>
            jQuery(document).ready(function($){
                window.load_campaigns()
            })
        </script>
        <?php
    }

    /**
     * Open default restrictions for access to registered endpoints
     * @param $authorized
     * @return bool
     */
    public function authorize_url( $authorized ){
        if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), $this->root . '/v1/'.$this->type ) !== false ) {
            $authorized = true;
        }
        return $authorized;
    }

    /**
     * Register REST Endpoints
     * @link https://github.com/DiscipleTools/disciple-tools-theme/wiki/Site-to-Site-Link for outside of wordpress authentication
     */
    public function add_api_routes() {
        $namespace = $this->root . '/v1';
        register_rest_route(
            $namespace, '/'.$this->type, [
                [
                    'methods'  => WP_REST_Server::CREATABLE,
                    'callback' => [ $this, 'endpoint' ],
                ],
            ]
        );
    }

    public function endpoint( WP_REST_Request $request ) {
        $params = $request->get_params();

        // create
        if ( ! isset( $params['email'] ) || empty( $params['email'] ) ) {
            return new WP_Error( __METHOD__, "Missing email", [ 'status' => 400 ] );
        }
        if ( ! isset( $params['selected_times'] ) || empty( $params['selected_times'] ) ) {
            return new WP_Error( __METHOD__, "Missing times and locations", [ 'status' => 400 ] );
        }

        $params = dt_recursive_sanitize_array( $params );
        $email = $params['email'];
        $title = $params['name'];
        if ( empty( $title ) ) {
            $title = $email;
        }

        $user = wp_get_current_user();
        $user->add_cap( 'create_subscriptions' );

        $hash = dt_create_unique_key();

        $fields = [
            'title' => $title,
            "contact_email" => [
                ["value" => $email ],
            ],
            'campaigns' => [
                "values" => [
                    [ "value" => $params['campaign_id'] ],
                ],
            ],
            'public_key' => $hash,
        ];

        // create post

        $new_id = DT_Posts::create_post( 'subscriptions', $fields, true );
        if ( is_wp_error( $new_id ) ) {
            return $new_id;
        }

        // log reports

        foreach( $params['selected_times'] as $time ){
            $args = [
                'post_id' => $new_id['ID'],
                'post_type' => 'subscriptions',
                'type' => $this->root,
                'subtype' => $this->type,
                'payload' => null,
                'value' => $params['campaign_id'],
                'lng' => null,
                'lat' => null,
                'level' => null,
                'label' => null,
                'grid_id' => $time['grid_id'],
                'time_begin' => $time['time'],
                'time_end' => $time['time'] + 900,
            ];

            $grid_row = Disciple_Tools_Mapping_Queries::get_by_grid_id($time['grid_id']);
            if ( ! empty( $grid_row ) ){
                $full_name = Disciple_Tools_Mapping_Queries::get_full_name_by_grid_id($time['grid_id']);
                $args['lng'] = $grid_row['longitude'];
                $args['lat'] = $grid_row['latitude'];
                $args['level'] = $grid_row['level_name'];
                $args['label'] = $full_name;
            }
            Disciple_Tools_Reports::insert( $args );
        }

        return $hash;
    }
}