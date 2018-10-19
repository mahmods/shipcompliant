<?php
/**
 * Created by PhpStorm.
 * User: fred
 * Date: 7/21/14
 * Time: 6:43 PM
 */

namespace H2\ShipCompliant\WooCommerce;

use H2\ShipCompliant\API\Security;
use H2\ShipCompliant\Plugin;
use H2\ShipCompliant\Util\Logger;

class Authentication extends \WC_Settings_API {

    public $id = "auth";
    public $title = "ShipCompliant Authentication";
    public $method_title = "ShipCompliant Authentication";
    public $method_description = "Your ShipCompliant API Credentials";

    public $messages = array();
    public $errors = array();


    /**
     *
     */
    function __construct()
    {
        //add_action('woocommerce_update_options_shipcompliant_auth', array($this, 'process_admin_options'));
        add_action('woocommerce_update_options_shipcompliant_auth', array($this, 'process_admin_options' ) );
        
        add_action('wp_ajax_shipcompliant_check_credentials', array($this, 'ajax_check_credentials'));
        
        $this->init_form_fields();
        $this->init_settings();
        $this->username = $this->get_option('username');
        $this->password = $this->get_option('password');
    }


    /*public function plugin_options_validate($form_fields = false)
    {
        //parent::validate_settings_fields($form_fields);

        if (!Plugin::getInstance()->confirmAccess($this->sanitized_fields['username'], $this->sanitized_fields['password'], $this->sanitized_fields['api_mode']))
        {
            $this->errors[] = "Invalid API Credentials";
        }
        else
        {
            $this->messages[] = "Successfully validated API Credentials";
        }

    }*/

    function process_admin_options()
    {

        // Save regular options
        parent::process_admin_options();
        
	    if ( empty( $_POST ) ) {
		    return false;
	    }

        //delete_transient('shipcompliant_fulfillment_options'); -- commented out as it was deleting the stored info
        //return parent::process_admin_options();
        //$this->init_settings();
        try {
            $hasAccess = Plugin::getInstance()->confirmAccess($_POST['woocommerce_auth_username'],
            $_POST['woocommerce_auth_password'], $_POST['woocommerce_auth_api_mode'], true);
        } catch ( Exception $e ) {
            //Logger::debug( 'Could not confirm API access', $e->getMessage() );
            $this->add_error( $e->getMessage() );
            $redirect = add_query_arg( 'wc_error', urlencode( __( 'Could not confirm API access', 'woocommerce' ) ) );
            wp_redirect( $redirect );
            exit;
        }

        if ($hasAccess) {
//Logger::debug( 'process_admin_options', $hasAccess );
            $this->messages[] = 'Successfully validated API Credentials';
            //parent::process_admin_options();
            set_transient( 'shipcompliant_api_status', true, ( 3600 * 24 ) );
            update_option('shipcompliant_api_status', true);
            //return parent::process_admin_options();
        } else {
            //$this->errors[] = 'Invalid API Credentials &mdash; please note that this is not your regular ShipCompliant login but separate API specific credentials that you can obtain from ShipCompliant support.' ;
            //parent::display_errors();
            set_transient( 'shipcompliant_api_status', false, ( 3600 * 24 ) );
            update_option('shipcompliant_api_status', false);

            $redirect = add_query_arg( 'wc_error', urlencode( __( 'Invalid API Credentials &mdash; please note that this is not your regular ShipCompliant login but separate API specific credentials that you can obtain from ShipCompliant support.', 'woocommerce' ) ) );
            wp_redirect( $redirect );
            exit;
        }
    }


    /**
     * @return void
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'username' => array(
                'title'    => __('Username', 'woocommerce'),
                'desc_tip' => __('Your ShipCompliant API Username', 'woocommerce'),
                'type'     => 'text'
            ),
            'password' => array(
                'title'    => __('Password', 'woocommerce'),
                'desc_tip' => __('Your ShipCompliant API Password', 'woocommerce'),
                'type'     => 'password'
            ),
            'api_mode' => array(
                'title'       => 'API Access Mode',
                'description' => 'If you are unsure, please use Production Mode.',
                'type'        => 'select',
                'options'     => array(
                    'production' => 'Production',
                    'staging'    => 'Staging',
                    'developer'  => 'Developer',
                ),
                'default'     => 'production'
            )
        );
    }

    public function display_errors()
    {
        foreach ($this->errors as $message)
        {
            printf("<div class='error'><p>%s</p></div>", $message);
        }
    }

    public function display_messages()
    {
        foreach ($this->messages as $message)
        {
            printf("<div class='updated'><p>%s</p></div>", $message);
        }
    }

    public function get_form_fields() {
		return apply_filters( 'woocommerce_settings_api_form_fields_' . $this->plugin_id, array_map( array( $this, 'set_defaults' ), $this->form_fields ) );
    }
    
    protected function set_defaults( $field ) {
		if ( ! isset( $field['default'] ) ) {
			$field['default'] = '';
		}
		return $field;
	}

    /**
     * Admin Options
     *
     * Setup the gateway settings screen.
     * Override this in your gateway.
     *
     * @access public
     * @return void
     */
    public function admin_options()
    {

        ?>
        <style type="text/css">
            #branding {
                margin: 20px 0px;
            }

        </style>

        <a href="http://h2medialabs.com">
            <img id="branding" src="<?php echo Plugin::BRANDING; ?>"/>
        </a>
        <h3>Authentication</h3>

        <?php echo isset($this->method_description) ? wpautop($this->method_description) : ''; ?>

        <?php $this->display_messages(); ?>

        <table class="form-table">
            <?php $this->generate_settings_html( $this->get_form_fields() ); ?>
        </table>

        <!-- Section -->
        <div><input type="hidden" name="section" value="<?php echo $this->id; ?>"/></div>

    <?php
    }

}
