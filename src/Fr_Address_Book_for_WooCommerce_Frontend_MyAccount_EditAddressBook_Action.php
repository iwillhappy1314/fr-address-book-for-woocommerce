<?php

/**
 * Front end my-account/edit-address-book page action.
 *
 * @since 1.0.0
 * @author Fahri Rusliyadi <fahri.rusliyadi@gmail.com>
 */
class Fr_Address_Book_for_WooCommerce_Frontend_MyAccount_EditAddressBook_Action {
    /**
     * Register actions and filters with WordPress.
     * 
     * @since 1.0.0
     */
    public function init() {
        add_action('template_redirect', array($this, 'on_template_redirect'));
    }
    
    /**
     * <code>template_redirect</code> action handler.
     * 
     * @since 1.0.0
     * @return void
     */
    public function on_template_redirect() {
        if (!wc()->customer->get_id() || !wp_verify_nonce(wc_get_post_data_by_key('fabfw_edit_address'), 'fabfw_edit_address')) {
            return;
        }
        
        $this->update_address();
    }
    
    /**
     * Update an address.
     * 
     * @since 1.0.0
     * @return void
     */
    private function update_address() {
        $post_data = filter_input_array(INPUT_POST);
        
        if (!isset($post_data['address_id'])) {
            return;
        }
        
        $address_fields = wc()->countries->get_address_fields(esc_attr($post_data['billing_country']));
        $address_data   = array();
        $post_data      = $this->sanitize_post_data($post_data, $address_fields);
        $post_data      = $this->validate_post_data($post_data, $address_fields);
        
        foreach($post_data as $key => $value) {
            if (
                // Exclude array and object values.
                is_array($value) || is_object($value) ||
                // Exclude non billing fields.
                strpos($key, 'billing_') !== 0 
            ) {
                continue;
            }
            
            $data_key                   = preg_replace('/^billing_/', '', $key);
            $address_data[$data_key]    = $value;
        }
        
        if (wc_notice_count('error') === 0) {
            $customer = new WC_Customer(wc()->customer->get_id());
            
            $customer->update_meta_data('fabfw_address', $address_data, $post_data['address_id']);
            $customer->save_meta_data();
            
            wc_add_notice(__('Address changed successfully.', 'fr-address-book-for-woocommerce'));
            wp_safe_redirect(wc_get_endpoint_url(fr_address_book_for_woocommerce()->Frontend_MyAccount_EditAddressBook->get_endpoint_name(), $post_data['address_id'], wc_get_page_permalink('myaccount')));
            exit;
        } 
    }
    
    /**
     * Sanitize post data.
     * 
     * @link https://github.com/woocommerce/woocommerce/blob/c01b7287989eaa0de7e80d2da9ca3cedf37971e4/includes/class-wc-form-handler.php#L78-L141
     * 
     * @since 1.0.0
     * @param array $post_data Posted data.
     * @param array $address_fields Address fields.
     * @return array Sanitized posted data.
     */
    private function sanitize_post_data($post_data, $address_fields) {
        // Sanitize default address fields.
        foreach ($address_fields as $key => $field) { 
            if (!isset($field['type'])) {
                $field['type'] = 'text';
            }
                        
            // Get Value.
            switch ($field['type']) {
                case 'checkbox' :
                    $post_data[$key] = (int) isset($post_data[$key]);
                    break;
                default :
                    $post_data[$key] = isset($post_data[$key]) ? wc_clean($post_data[$key]) : '';
                    break;
            }
        }
        
        // Sanitize custom address fields that may be provided by other plugins.
        foreach($post_data as $key => $value) {
            if (
                // Exclude default fields.
                isset($address_fields[$key]) || 
                // Exclude array and object values.
                is_array($value) || is_object($value) ||
                // Exclude non billing fields.
                strpos($key, 'billing_') !== 0) 
            {
                continue;
            }
            
            $post_data[$key] = wc_clean($value);
        }
        
        return $post_data;
    }
    
    /**
     * Validate posted data.
     * 
     * @link https://github.com/woocommerce/woocommerce/blob/c01b7287989eaa0de7e80d2da9ca3cedf37971e4/includes/class-wc-form-handler.php#L78-L141
     * 
     * @since 1.0.0
     * @param array $post_data Posted data.
     * @param array $address_fields Address fields.
     * @return array Sanitized posted data.
     */
    private function validate_post_data($post_data, $address_fields) {
        foreach ($address_fields as $key => $field) { 
            // Validation: Required fields.
            if (!empty($field['required']) && empty($post_data[$key])) {
                wc_add_notice(sprintf(__('%s is a required field.', 'fr-address-book-for-woocommerce'), $field['label']), 'error');
            }

            // Validation rules.
            if (!empty($post_data[$key]) && !empty($field['validate']) && is_array($field['validate'])) {
                foreach ($field['validate'] as $rule) {
                    switch ($rule) {
                        case 'postcode' :
                            $post_data[$key] = strtoupper(str_replace(' ', '', $post_data[$key]));

                            if (!WC_Validation::is_postcode($post_data[$key], $post_data['billing_country'])) {
                                wc_add_notice(__('Please enter a valid postcode / ZIP.', 'fr-address-book-for-woocommerce'), 'error');
                            } else {
                                $post_data[$key] = wc_format_postcode($post_data[$key], $post_data['billing_country']);
                            }

                            break;
                        case 'phone' :
                            $post_data[$key] = wc_format_phone_number($post_data[$key]);

                            if (!WC_Validation::is_phone($post_data[$key])) {
                                wc_add_notice(sprintf(__('%s is not a valid phone number.', 'fr-address-book-for-woocommerce'), '<strong>' . $field['label'] . '</strong>' ), 'error');
                            }

                            break;
                        case 'email' :
                            $post_data[$key] = strtolower($post_data[$key]);

                            if (!is_email($post_data[$key])) {
                                wc_add_notice(sprintf(__('%s is not a valid email address.', 'fr-address-book-for-woocommerce'), '<strong>' . $field['label'] . '</strong>'), 'error');
                            }
                            break;
                    }
                }
            }
        }
        
        return $post_data;
    }
}
