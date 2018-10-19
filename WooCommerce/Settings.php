<?php
/**
 * Created by PhpStorm.
 * User: fred
 * Date: 9/5/14
 * Time: 2:39 PM
 */

namespace H2\ShipCompliant\WooCommerce;

use H2\ShipCompliant\Compliance;
use H2\ShipCompliant\Plugin;
use H2\ShipCompliant\API\SalesOrderService;
use H2\ShipCompliant\Util\Logger;

class Settings extends \WC_Settings_API {

    public $id = "config";
    public $title = "ShipCompliant Settings";
    public $method_title = "ShipCompliant Settings";
    public $method_description = "Settings for ShipCompliant";

    public $default_fulfillment_location;
    public $default_shipment_status;
    public $compliance_mode;
    public $api_mode;
    public $disable_address_suggest;
    public $debug_mode;

    private $salesOrderService = null;

    private $complianceModeHelpText = <<<EOL
<dl>
    <dt><b>Quarantine</b></dt>
    <dd>
        Allow checkout process to proceed as normal, but do not display messages to end customer.<br/>
        Site Admins can manually resolve issues for non-compliant orders.
    </dd>
    <dt><b>Compliance Override</b></dt>
    <dd>Commit all orders to fulfillment. Do not record compliance information.</dd>
    <dt><b>Reject</b></dt>
    <dd>Reject all non-compliant orders.  Give customers an error message.</dd>
</ul>
EOL;


    public function __construct()
    {
        try {
            $this->salesOrderService = new SalesOrderService(Plugin::getInstance()->getSecurity());
            $this->init_form_fields();
            $this->init_settings();
        } catch ( Exception $e ) {
            $redirect = add_query_arg( 'wc_error', urlencode( __( 'There was a problem communicating with the ShipCompliant API - Your API credentials did not work.', 'woocommerce' ) ) );
            wp_redirect( $redirect );
            exit;
            $msg            = "There was a problem communicating with the ShipCompliant API - Your API credentials did not work.";
            $this->errors[] = $msg;
            $this->errors[] = $this->login_message();
            
            Logger::error($msg, array('security' => $this->salesOrderService->getSecurity()));
            return false;
        }
        
        $this->default_fulfillment_location = $this->get_option('default_fulfillment_location');
        $this->default_shipment_status      = $this->get_option('default_shipment_status');
        $this->compliance_mode              = $this->get_option('compliance_mode');
        $this->api_mode                     = $this->get_option('api_mode');
        $this->disable_address_suggest      = $this->get_option('disable_address_suggest');
        $this->debug_mode                   = $this->get_option('debug_mode');
        
        // Actions.
        
        add_action('woocommerce_update_options_shipcompliant_config', array($this, 'process_admin_options'));
    }
    
    /**
     * @return string|void
     */
    public function init_form_fields()
    {
        if (!$this->salesOrderService->getSecurity()->isReady()) {
            return;
        }

        $fulfillment_description = "Choose InHouse if you are self-fulfilling orders.";

        $fulfillment_options = array();
        if ($this->salesOrderService->getSecurity()->isReady())
        {
            $fulfillment_options = $this->loadFulfillmentOptions();
        }

        //var_dump($fulfillment_options);
        if (empty($fulfillment_options))
        {
            $fulfillment_description =
                "<p class='shipcompliant-form-error'>Please choose a fulfillment location in your account settings in ShipCompliant."
                . "<br/> (In your ShipCompliant Portal under Account Settings -> Fulfillment Location Settings)</p>";
        }

	    // API call to get possible shipping options
	    $request = array();
	    $response = $this->salesOrderService->GetPossibleShippingServices($request);

	    if(strtolower($response->GetPossibleShippingServicesResult->ResponseStatus) == 'success') {

		    $shipping_options_obj = $response->GetPossibleShippingServicesResult;

		    foreach ($shipping_options_obj->PossibleValues->PossibleValue as $key => $value) {

			    if (!preg_match('/^CST/', $value->Value)) {
				    $shipping_options[$value->Value] = $value->Description;
			    }
		    }
	    }

        $this->form_fields = array(
            'default_fulfillment_location' => array(
                'title'       => 'Default Fulfillment Location',
                'desc_tip'    => "Default Fulfillment Location",
                'description' => $fulfillment_description,
                'type'        => 'select',
                'options'     => $fulfillment_options,
                'default'     => 'InHouse'
            ),
            'default_shipment_status' => array(
                'title'    => 'Default Shipment Status',
                'desc_tip' => 'Default Shipment Status',
                'type'     => 'select',
                'options'  => array(
                    'InProcess'         => 'InProcess',
                    'Shipped'           => 'Shipped',
                    'PaymentAccepted'   => 'PaymentAccepted',
                    'SentToFulfillment' => 'SentToFulfillment',
                ),
                'default'  => 'SentToFulfillment'
            ),
            'compliance_mode' => array(
                'title'       => __('Compliance Mode', 'shipcompliant_config'),
                'description' => $this->complianceModeHelpText,
                'type'        => 'select',
                'options'     => array(
                    Compliance::COMPLIANCE_MODE_QUARANTINE => 'Quarantine',
                    Compliance::COMPLIANCE_MODE_OVERRIDE   => 'Compliance Override',
                    Compliance::COMPLIANCE_MODE_REJECT     => "Reject"
                ),
                'default'     => Compliance::COMPLIANCE_MODE_QUARANTINE
            ),
            'disable_address_suggest' => array(
                'title'       => __( 'Disable Address Suggestions', 'shipcompliant_config' ),
                'description' => 'Disable ShipCompliant address suggestions during checkout.',
                'type'        => 'checkbox',
                'label'       => __( 'Disable Address Suggestions', 'shipcompliant_config' ),
                'default'     => 'no'
            ),
            'debug_mode' => array(
                'title'       => __( 'Debugging Log', 'shipcompliant_config' ),
                'description' => 'Please be aware that this on could impact the performance of your site.',
                'type'        => 'checkbox',
                'label'       => __( 'Debugging Log', 'shipcompliant_config' ),
                'default'     => 'yes'
            ),
            'default_free_shipping' => array(
	            'title'       => __( 'Default for Free Shipping', 'shipcompliant_config' ),
	            'description' => 'Choose your default shipping method for free shipping.',
	            'type'        => 'select',
	            'options'     => $shipping_options,
                'label'       => __( 'Default for Free Shipping', 'shipcompliant_config' ),
	            'default'     => 'OTHER'
            )
        );

    }

    /**
     * @return array Fulfillment Options
     * @throws \Exception
     */
    public function loadFulfillmentOptions()
    {

        $fulfullmentOptions = get_transient('shipcompliant_fulfillment_options');

        if ($fulfullmentOptions === false)
        {
            $fulfullmentOptions = array();
            $request = array("ReturnOnlyActiveForSupplier" => fals);
            
            try
            {
                $response = $this->salesOrderService->getPossibleFulfillmentHouses($request);
                //var_dump($response->GetPossibleFulfillmentHousesResult);
                $result   = $response->GetPossibleFulfillmentHousesResult;
            }
            catch(\Exception $ex)
            {
                $this->errors[] = $ex->getMessage();
                return array();
            }

            if (strtolower($result->ResponseStatus) != "success")
            {
                $msg            = "There was a problem communicating with the ShipCompliant API: Could not load fulfillment options";
                $this->errors[] = $msg;
                $this->errors[] = $this->login_message();

                Logger::error($msg, array('result' => $result));

                return array();
            }

            $values = $result->PossibleValues->PossibleValue;

            if (count($values) > 1)
            {
                foreach ($result->PossibleValues->PossibleValue as $pv)
                {
                    $fulfullmentOptions[$pv->Value] = $pv->Description;
                }
            }
            else
            {
                $fulfullmentOptions[$values->Value] = $values->Description;
            }

			set_transient( 'shipcompliant_fulfillment_fulfullmentOptions', $fulfullmentOptions, 2 * HOUR_IN_SECONDS );
		}

		return $fulfullmentOptions;
	}

    public function process_admin_options()
    {
        // Save regular options
        parent::process_admin_options();

        if ( empty( $_POST ) ) {
            return false;
        }


        if (empty($this->errors))
        {
            delete_transient('shipcompliant_fulfillment_options');
        }
    }

    public function login_message()
    {
        return '<strong>
                    Please go to
                    <a href="admin.php?page=woocommerce_settings&tab=shipcompliant&section=shipcompliant_auth">
                        ShipCompliant Authentication
                    </a>
                    to enter your API login credentials.
                </strong>';
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
        if (!$this->salesOrderService->getSecurity()->isReady())
        {
            $msg            = "There was a problem communicating with the ShipCompliant API - Your API credentials did not work.";
            $this->errors[] = $msg;
            $this->errors[] = $this->login_message();

            Logger::error($msg, array('security' => $this->salesOrderService->getSecurity()));
        } else {
        ?>
        <a href="http://h2medialabs.com">
            <img id="branding" style="padding: 20px 0px;" src="<?php echo Plugin::BRANDING; ?>"/>
        </a>
        <h3>Settings</h3>
        <?php $this->display_errors(); ?>
        <table class="form-table">
            <?php $this->generate_settings_html( $this->get_form_fields() ); ?>
        </table>

        <!-- Section -->
        <div><input type="hidden" name="section" value="<?php echo $this->id; ?>"/></div>

	<?php
        }
	}
}
