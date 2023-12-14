<?php

class DT_Flexible_Time_Porch_Loader extends DT_Generic_Porch_Loader {

    public $id = 'flexible-time-porch';

    public function __construct() {
        parent::__construct( __DIR__ );

        $this->label = __( 'Flexible Time Landing Page', 'disciple-tools-prayer-campaigns' );
        add_filter( 'dt_campaigns_wizard_types', array( $this, 'wizard_types' ) );

        // require_once( __DIR__ . '/settings.php' );
    }

    public function wizard_types( $wizard_types ) {
        $wizard_types[$this->id] = [
            'campaign_type' => 'flexible',
            'porch' => $this->id,
            'label' => 'Flexible Campaign Template',
        ];

        return $wizard_types;
    }
}
( new DT_Flexible_Time_Porch_Loader() )->register_porch();
