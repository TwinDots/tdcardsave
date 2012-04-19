<?php
/*
 * Cardsave direct payment module
 * 
 * Developed by Matthew Caddoo, Twin Dots Limited 
 * Follow us @twindots
 * twindots.co.uk
 */
 
class Tdcardsave_Direct_Payment extends Shop_PaymentType
{
    /**
     * Payment method information
     * 
     * @return array 
     */
    public function get_info()
    {
        return array(
            'name' => 'Cardsave Direct Integration',
            'description' => 'A more advanced method of Cardsave payment integration'
        );
    }
    
    /**
     * Construct form for administration area to configure module
     * 
     * @param $host_obj ActiveRecord object containing configuration fields values
     * @param string $context Form context 
     */
    public function build_config_ui($host_obj, $context = null)
    {
        if ($context !== 'preview') 
        {
            $host_obj->add_field('merchant_id', 'Merchant ID')->tab('Configuration')->
            renderAs(frm_text)->comment('Cardsave 15 digit Merchant ID', 'above')->
            validation()->fn('trim')->required('Please provide Merchant ID.');
            
            $host_obj->add_field('password', 'Password')->tab('Configuration')->
            renderAs(frm_text)->comment('Cardsave account password', 'above')->
            validation()->fn('trim')->required('Please provide a Cardsave account password');
            
            $host_obj->add_field('hash_method', 'Hash Method')->tab('Configuration')->
            renderAs(frm_dropdown)->comment('Hashing Method', 'above')->
            validation()->fn('trim')->required('Please provide a Hash Method');
            
            $host_obj->add_field('shared_key', 'Shared Key')->tab('Configuration')->
            renderAs(frm_text)->comment('Shared Key', 'above')->
            validation()->fn('trim')->required('Please provide a Shared Key');
            
            $host_obj->add_field('transaction_type', 'Transaction Type')->
            tab('Configuration')->renderAs(frm_dropdown)->
            comment('The type of credit card transaction you want to perform.', 'above');
 
            $host_obj->add_field('order_status', 'Order Status')->tab('Configuration')->
            renderAs(frm_dropdown)->comment('Select status to assign the order in 
            case of successful payment.', 'above', true);
        }
    }
    
    /**
     * Defines the types of payments
     * 
     * @param int $current_key_value
     * @return array 
     */
    public function get_transaction_type_options($current_key_value = -1)
    {
        $options = array(
            'PREAUTH'=>'Pre-authorization',
            'SALE'=>'Purchase'
        );
 
        if ($current_key_value === -1) {
            return $options;
        }
 
        return isset($options[$current_key_value]) ? $options[$current_key_value] : null;
    }
    
    
    /**
     * Hashing option dropdown
     * 
     * @param int $current_key_value
     * @return array 
     */
    public function get_hash_method_options($current_key_value = -1)
    {
        $options = array(
            'SHA1' => 'SHA1',
            'HMACMD5' => 'HMACMD5',
            'MD5' => 'MD5',
            'HMACSHA1' => 'HMACSHA1'
        );
        if ($current_key_value === -1) {
            return $options;
        }
        return isset($options[$current_key_value]) ? $options[$current_key_value] : null;
    }
    
    /**
     * Gets order status
     * 
     * @param int $current_key_value
     * @return string 
     */
    public function get_order_status_options($current_key_value = -1)
    {
        if ($current_key_value === -1)
            return Shop_OrderStatus::create()->order('name')->find_all()->as_array('name', 'id');
 
        return Shop_OrderStatus::create()->find($current_key_value)->name;
    }
 
    /**
     * Peforms basic validation on payment gateway options
     * 
     * @param $host_obj 
     */
    public function validate_config_on_save($host_obj)
    {
        if (strlen($host_obj->password)>15) {
            $host_obj->field_error('password', 'Password must be 15 characters or shorter');
        }
    }
 
    /**
     * Prevent orders place with this payment method from being deleted
     * 
     * @param $host_obj
     * @param object $status 
     */
    public function status_deletion_check($host_obj, $status)
    {
        if ($host_obj->order_status == $status->id)
            throw new Phpr_ApplicationException('This status cannot be deleted because it is used in the Cardsave direct payment method.');
    }
    
    /**
     * Defines the payment form fields
     * 
     * @param $host_obj 
     */
    public function build_payment_form($host_obj)
    {
        $host_obj->add_field('CardName', 'Card Holder Name')->renderAs(frm_text)->
            comment('Cardholder Name', 'above')->validation()->fn('trim')->
            required('Please specify a cardholder name');
        
        $host_obj->add_field('CardNumber', 'Credit Card Number')->renderAs(frm_text)->
            validation()->fn('trim')->required('Please specify a credit card number')->
            regexp('/^[0-9]+$/', 'Credit card number can contain only digits.');
 
        $host_obj->add_field('StartMonth', 'Start Month', 'left')->renderAs(frm_text)->
            renderAs(frm_text)->validation()->fn('trim')->numeric();
        
        $host_obj->add_field('StartYear', 'Start Year', 'right')->renderAs(frm_text)->
            renderAs(frm_text)->validation()->fn('trim')->numeric();
        
        $host_obj->add_field('ExpMonth', 'Expiration Month', 'left')->renderAs(frm_text)->
            renderAs(frm_text)->validation()->fn('trim')->required('Please specify card expiration month')->numeric();
        
        $host_obj->add_field('ExpYear', 'Expiration Year', 'right')->renderAs(frm_text)->
            renderAs(frm_text)->validation()->fn('trim')->required('Please specify card expiration year')->numeric();
        
        $host_obj->add_field('CV2', 'CV2', 'left')->renderAs(frm_text)->validation()->
            fn('trim')->required('Please specify Card Verification Number')->numeric();
                
        $host_obj->add_field('IssueNumber', 'Issue Number', 'right')->renderAs(frm_text)->validation()->
            fn('trim')->numeric();
    }
    
    /**
     * Prevent sensitive information being logged
     * 
     * @param array $fields
     * @return array
     */
    private function prepare_fields_log($fields)
    {
        unset($fields['CV2']);
        if(isset($fields['IssueNumber']))
            unset($fields['IssueNumber']);
        $fields['CardNumber'] = '...'.substr($fields['CardNumber'], -4);
 
        return $fields;
    }
    
    /**
     * Prevent sensitive information from gateway
     * 
     * @param array $response
     * @return array 
     */
    private function prepare_response_log($response)
    {
        return $response;
    }
    
    /**
     * Process the payment and catch any errors 
     * 
     * @param array $data
     * @param $host_obj
     * @param Shop_Order $order
     * @param bool $back_end 
     */
    public function process_payment_form($data, $host_obj, $order, $back_end = false)
    {
        /*
        * Validate input data
        */
        $validation = new Phpr_Validation();
        
        $validation->add('CardName', 'Card Holder Name')->fn('trim')->
            required('Please enter the name as it appears on the card');
        
        $validation->add('CardNumber', 'Credit Card Number')->fn('trim')->
            required('Please enter a credit card number')->regexp('/^[0-9]*$/','Credit card number can only contain digits');
        
        $validation->add('CV2', 'CV2')->fn('trim')->required('Please enter the card\'s security code')->
            regexp('/^[0-9]*$/', 'Card security code must contain only digits');
        
        $validation->add('StartMonth', 'Start month')->fn('trim')->
            regexp('/^[0-9]*$/', 'Credit card start month can contain only digits.');
        
        $validation->add('StartYear', 'Start year')->fn('trim')->
            regexp('/^[0-9]*$/', 'Credit card start year can contain only digits.');
        
        $validation->add('ExpMonth', 'Expiration month')->fn('trim')->
            required('Please specify a card expiration month.')->
            regexp('/^[0-9]*$/', 'Credit card expiration month can contain only digits.');
        
        $validation->add('ExpYear', 'Expiration year')->fn('trim')->
            required('Please specify a card expiration year.')->
            regexp('/^[0-9]*$/', 'Credit card expiration year can contain only digits.');
 
        $validation->add('IssueNumber', 'Issue Number')->fn('trim')->
            regexp('/^[0-9]*$/', 'Issue number must contain only digits');
        
        
        try
        {
            /*
            * Prepare and send request to the payment gateway, and parse the server response
            */
            if (!$validation->validate($data)) {
                $validation->throwException();
            } else {
                require_once(PATH_APP.'/modules/tdcardsave/classes/ThePaymentGateway/PaymentSystem.php');
                /**
                 * Get list of entry points
                 */
                $gateway_entry_points = new RequestGatewayEntryPointList;
                $gateway_entry_points->add('https://gw1.cardsaveonlinepayments.com:4430', 100, 2);
                $gateway_entry_points->add('https://gw2.cardsaveonlinepayments.com:4430', 200, 2);
                $gateway_entry_points->add('https://gw3.cardsaveonlinepayments.com:4430', 300, 2);
                /**
                 * Save login details
                 */
                $merchant_credentials = new MerchantDetails($host_obj->merchant_id,$host_obj->password);
                
                /**
                 * Define transaction type
                 */
                $transaction_type = (string)$host_obj->transaction_type;
                $transaction_type = new NullableTRANSACTION_TYPE(TRANSACTION_TYPE::SALE);
                $message_details = new MessageDetails($transaction_type);
                
                /**
                 * Options for response
                 */
                $echo_card_type = new NullableBool(true);
                $echo_amount_received = new NullableBool(true);
                $echo_avs_check_result = new NullableBool(true);
                $echo_cv2_check_result = new NullableBool(true);
                
                /**
                 * 3D Secure To be implemented
                 */
                $three_d_secure_override_policy = new NullableBool(true);
                
                /**
                 * Time Options
                 */
                $duplicate_delay = new NullableInt(1);
                
                /**
                 * Transaction Control
                 */
                $transaction_control = new TransactionControl($echo_card_type, 
                    $echo_avs_check_result, $echo_cv2_check_result, $echo_amount_received,
                    $duplicate_delay, '', '', $three_d_secure_override_policy, '', null, null
                );
                
                /**
                 * Create transaction details
                 */
                $amount = new NullableInt($order->total * 100);
                
                $currency = Shop_CurrencySettings::get();
                $currency_code = new NullableInt($currency->iso_4217_code);
                
                $device_category = new NullableInt(0); // 3D secure device category is computer
                $three_d_secure_browser_details = new ThreeDSecureBrowserDetails(
                    $device_category, '*/*', $_SERVER['HTTP_USER_AGENT']
                );
                
                $transaction_details = new TransactionDetails($message_details,
                    $amount, $currency_code, $order->id, 'Web Order', $transaction_control,
                    $three_d_secure_browser_details
                );
                
                /**
                 * Card Details
                 */
                $card_expiry_month = new NullableInt($validation->fieldValues['ExpMonth']);
                $card_expiry_year = new NullableInt($validation->fieldValues['ExpYear']);
                $card_expiry_date = new CreditCardDate($card_expiry_month, $card_expiry_year);
                
                $card_start_month = new NullableInt($validation->fieldValues['StartMonth']);
                $card_start_year = new NullableInt($validation->fieldValues['StartYear']);
                $card_start_date = new CreditCardDate($card_start_month,$card_start_year);
                
                $card_details = new CardDetails(
                    $validation->fieldValues['CardName'], $validation->fieldValues['CardNumber'],
                    $card_expiry_date, $card_start_date, $validation->fieldValues['IssueNumber'],
                    $validation->fieldValues['CV2']
                );              
                
                /**
                 * Billing Address Information
                 */
                $country_code = new NullableInt($order->billing_country->code_iso_numeric);

                // Added a check in here to see if the state is set as LemonStand doesn't have states for all countries
                if ( isset($order->billing_state->code) ) {
                    $billing_state_code = $order->billing_state->code;
                } else {
                    $billing_state_code = '';
                }

                $billing_address = new AddressDetails($order->billing_street_addr,
                    '', $order->billing_company, '', $order->billing_city,
                    $billing_state_code, $order->billing_zip, $country_code
                );
                
                /**
                 * Customer Details
                 */
                $customer_details = new CustomerDetails($billing_address, $order->billing_email,
                    $order->billing_phone, Phpr::$request->getUserIp()
                );
                
                /**
                 * The transaction
                 */
                $card_details_transaction = new CardDetailsTransaction(
                    $gateway_entry_points, 1, null, $merchant_credentials,
                    $transaction_details, $card_details, $customer_details, ''
                );
                
                /**
                 *  Process the transaction 
                 */
                $transaction_processed = $card_details_transaction->processTransaction(
                    $gateway_output, $transaction_output_message
                ); 
                
                if ($transaction_processed == false) {
                    throw new Exception('Unable to communicate with payment gateway');
                } else {
                    $response_message = $gateway_output->getMessage();
                    $response_code = $gateway_output->getStatusCode();
                    
                    switch ($response_code)
                    {
                        case 0: // Success
                            /* Log successfuly payment */
                            
                            $response_data = array(
                                'Auth Code' => $transaction_output_message->getAuthCode(),
                                'Address Numeric Check Result' => $transaction_output_message->getAddressNumericCheckResult()->getValue(),
                                'Postcode Check Result' => $transaction_output_message->getPostCodeCheckResult()->getValue(),
                                'CV2 Result' => $transaction_output_message->getCV2CheckResult()->getValue(),
                                'Card Issuer' => $transaction_output_message->getCardTypeData()->getIssuer()->getValue(),
                                'Card Type' => $transaction_output_message->getCardTypeData()->getCardType(),
                            );
 
                            $this->log_payment_attempt(
                                $order,
                                'Successful payment',
                                1, 
                                $this->prepare_fields_log($data),
                                $response_data,
                                $response_message,
                                $response_data['CV2 Result'],
                                '',
                                $response_data['Address Numeric Check Result']
                            );         
                                                 
                            /* Update order status */
                            Shop_OrderStatusLog::create_record($host_obj->order_status,
                                $order
                            );                   
                            
                            /* Mark as processed */
                            $order->set_payment_processed();       
                        break;
                        case 3: // 3D Secure required and not implemented
                            throw new Exception('Credit Card requires 3D secure but it has not been implemented');
                        break;
                        case 4: // Referred 
                            throw new Exception('Transaction referred');
                        break;
                        case 5: // Declined
                            throw new Exception("Credit card payment declined: $response_message");
                        break;
                        case 20: // Duplicate transaction (prevents double payments)
                            throw new Exception("Duplicate transaction: $response_message");
                        break;
                        case 30: // Error occured
                            $message = $response_message;
                            if ($gateway_output->getErrorMessages()->getCount() > 0) {
                                for ($i=0; $i<$gateway_output->getErrorMessages()->getCount(); $i++) {
                                    $message .= "$message ".$gateway_output->getErrorMessages()->getAt($i)."n";
                                }
                            }
                            throw new Exception("Error: $message");
                        break;
                        default: // Unknown error code so we just create a generic message
                            throw new Exception("Unknown Error Response Code: $response_code");                            
                        break;                            
                    }
                }                
            }
        }
        catch (Exception $ex)
        {
            /*
            * Log invalid payment attempt
            */
            $this->log_payment_attempt($order, $ex->getMessage(), 0, array(), array(), null);
            
            if (!$back_end)
                throw new Phpr_ApplicationException('Payment Declined');
            else
                throw new Phpr_ApplicationException('Error: '.$ex->getMessage().' on line: '.$ex->getLine());
        }
    }
}