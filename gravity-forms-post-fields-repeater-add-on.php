<?php
/*
Plugin Name: Gravity Forms Post Fields Repeater Add On
Plugin URI: http://wordpress.org/plugins/gravity-forms-post-fields-repeater-add-on/
Description: This is a plugin to connect Gravity Forms Post Updates and Gravity Forms Repeater Field
Author: Creative Little Dots
Version: 1.0
Author URI: http://www.creativelittledots.co.uk/
*/

add_action( 'init', array('gf_post_fields_repeater_addon', 'init') );

class gf_post_fields_repeater_addon
{

    private static $requirements;

    private static $form_update_id = null;
    
    private static $row_field_values = array();
    
    private static $deleted = array();

    public static function init()
    {
        self::$requirements = self::check_requrements();

        if (self::$requirements) {

            add_action('admin_notices', array(__CLASS__, 'admin_warnings'), 20);

            return false;

        }
        
        add_filter( 'gform_editor_repeater_field_settings', array(__CLASS__, 'add_repeater_field_settings') );

        add_action( 'gform_after_create_post', array(__CLASS__, 'form_handler'), 10, 3);

        add_action( 'gform_pre_render', array(__CLASS__, 'gform_pre_render_repeater_fields') );

        add_filter( 'shortcode_atts_gravityforms',   array(__CLASS__, 'gf_shortcode_atts'), 10, 3 );

        add_filter( 'gform_update_post_multi_fields', array(__CLASS__, 'update_post_multi_fields') );

    }

    /*
     |-----------------------------------------------------------------
     |      Check Required Plugins
     |-----------------------------------------------------------------
     |
     |  @return bool
     |
     */
    private static function check_requrements()
    {
        // Look for GF
        if (!class_exists('RGForms') || !class_exists('GFFormsModel') || !class_exists('GFCommon')) {
            return 'Gravity Forms';
        }
        // Look for the GFCommon object
        if (!class_exists('GFRepeater')) {
            return 'Gravity Forms Repeater Fields';
        }
        // Look for the GFCommon object
        if (!class_exists('gform_update_post')) {
            return 'Gravity Forms Update Post';
        }

        return false;

    }

    /*
     |-----------------------------------------------------------------
     |      Add Admin Notice If Missing a Required Plugin
     |-----------------------------------------------------------------
     |
     |  @return void
     |
     */
    public static function admin_warnings()
    {
        $message = 'Missing required plugin: ' . self::$requirements;
        ?>
        <div class="error">
            <p>
                <?php echo $message; ?>
            </p>
        </div>
        <?php

    }
    
    /*
     |-----------------------------------------------------------------
     |      Adding Repeater Field Setting to GF Edit Form
     |-----------------------------------------------------------------
     |
     |  @param array $settings
     |  @return Array
     |
     */
    public static function add_repeater_field_settings($settings) {
	        
	        return array_merge($settings, array(
				'post_custom_field_setting',
				'post_custom_field_unique',
			));

	        
        }

    /*
     |-----------------------------------------------------------------
     |      Handling The Form Before Saving
     |-----------------------------------------------------------------
     |
     |  @param int $post_id
     |  @param array $lead
     |  @param object $form
     |  @return void
     |
     */
    public static function form_handler($post_id, $lead, $form)
    {
        //getting all repeater fields in specified form
        $_repeater_fields = self::get_repeater_fields($form);

        if( empty($_repeater_fields) )
            return false;

        //do exactly the same for each repeater field
        foreach($_repeater_fields as $field){
            
            if($meta_value = self::_normalize( $field, $lead, $form )) {
                
                if(!isset(self::$deleted[$field->postCustomFieldName])) {
                    delete_post_meta($post_id, $field->postCustomFieldName);
                    self::$deleted[$field->postCustomFieldName] = true;
                }

    	        if($field->postCustomFieldUnique) {
                    update_post_meta($post_id, $field->postCustomFieldName, $meta_value );
                } else {
    		        add_post_meta($post_id, $field->postCustomFieldName, $meta_value );
    	        }
    	        
            }

        }

    }

    /*
     |-----------------------------------------------------------------
     |      Normalizing Repeater Field key => value Pairs
     |-----------------------------------------------------------------
     |
     |  @param object $field
     |  @param array $lead
     |  @param object $form
     |  @return Array
     |
     */
    private static function _normalize( $field , $lead , $form) {
	    
	    $_repeater_rows = array();
	    
	    if($data = unserialize($lead[$field->id])) {
	    
	        $_repeater_rows = array_values($data);
		        
	        foreach($_repeater_rows as &$repeater_row) {
	            
	            $repeater_row_meta = array();
	            
	            foreach($repeater_row as $key => &$value) {
	            	
	            	if($field = self::get_field_by_id( $form , $key )) {
	                	
	                	$meta_key = self::get_field_meta_key( $field );
	                	
	                	$repeater_row_meta[$meta_key] = self::_decode_field_value($value, $field);
	               	
	            	}
	            
	            }  
		    	
		    	$repeater_row = $repeater_row_meta;
		        
			}
	        
		}
		
		return $_repeater_rows;
        
    }
     
     /*
     |-----------------------------------------------------------------
     |      Decode Field Value based on Field Type
     |-----------------------------------------------------------------
     |
     |  @param object $value
     |  @param object $field
     |  @return Array/Int/String
     |
     */
    private static function _decode_field_value($value, $field) {
        
        $value = end($value);
            	
    	switch($field->inputType) {
        	
        	case 'date' :
        	    
        	    $format_name = 'mdy';
        	    
        	    switch ( $field->dateFormat ) {
					case 'mdy' :
						$format_name = 'mm/dd/yyyy';
						break;
					case 'dmy' :
						$format_name = 'dd/mm/yyyy';
						break;
					case 'dmy_dash' :
						$format_name = 'dd-mm-yyyy';
						break;
					case 'dmy_dot' :
						$format_name = 'dd.mm.yyyy';
						break;
					case 'ymd_slash' :
						$format_name = 'yyyy/mm/dd';
						break;
					case 'ymd_dash' :
						$format_name = 'yyyy-mm-dd';
						break;
					case 'ymd_dot' :
						$format_name = 'yyyy.mm.dd';
						break;
				}
				
				$date = GFCommon::parse_date($value, $format_name);
				
				$value = implode('-', array_reverse($date));
        	
        	break;
        	
        	case 'time' :
        	
        	    $value = $value ? implode(':', array_filter($value)) : $value;
        	    
            break;
        	    
        	
    	}
        
        return $value;
         
    }
     
     /*
     |-----------------------------------------------------------------
     |      Encode Field Value based on Field Type
     |-----------------------------------------------------------------
     |
     |  @param object $value
     |  @param object $field
     |  @return Array/Int/String
     |
     */
    private static function _encode_field_value($value, $field) {
            	
    	switch($field->inputType) {
        	
        	case 'date' :
        	    
        	    $format_name = 'mdy';
        	    
        	    switch ( $field->dateFormat ) {
					case 'mdy' :
						$format_name = 'm/d/Y';
						break;
					case 'dmy' :
						$format_name = 'd/m/Y';
						break;
					case 'dmy_dash' :
						$format_name = 'd-m-Y';
						break;
					case 'dmy_dot' :
						$format_name = 'd.m.Y';
						break;
					case 'ymd_slash' :
						$format_name = 'Y/m/d';
						break;
					case 'ymd_dash' :
						$format_name = 'Y-m-d';
						break;
					case 'ymd_dot' :
						$format_name = 'Y.m.d';
						break;
				}
				
				$value = array(date($format_name, strtotime($value)));
        	
        	break;
        	
        	case 'time' :
        	
        	    $value = explode(':', $value);
        	
        	break;
        	
        	default :
        	
        	    $value = is_array($value) ? $value : array($value);
        	    
            break;
        	
    	}
        
        return $value;
         
    }
    
    /*
     |-----------------------------------------------------------------
     |      ALl Multi Fields Output Array, We Need First Item which is actual field value
     |-----------------------------------------------------------------
     |
     |  @param Array $value
     |  @return String/Int
     |
     */
    public static function update_post_multi_fields($value) {
            
        return end($value);
        
    }

    private static function get_repeater_fields($form){
        return array_filter(GFAPI::get_fields_by_type($form, 'repeater'), function($field) {
            return property_exists($field, 'postCustomFieldName') && $field->postCustomFieldName;
        });
    }

    //the fields which will be shown generated here
    public static function gform_pre_render_repeater_fields($form) {
        if( self::$form_update_id ) {
            if($_repeater_fields = self::get_repeater_fields($form)) {
	            self::$row_field_values[$form['id']] = array();
	            foreach ($_repeater_fields as $repeater => $field) {
	                $_rows_meta = get_post_meta( self::$form_update_id , $field->postCustomFieldName , true );
	                if( empty( $_rows_meta ) || ! is_array( $_rows_meta ) ) { continue; }
	                $_index = array_search($field, $form['fields']); // get index of where repeater field is in $form['fields']
	                if($_index !== false) {
		                $form['fields'][$_index]->start = count($_rows_meta); // change the start value so that the number of rows match the meta
		                $position = self::get_field_position($form['fields'], $field->id);
		                foreach( $_rows_meta as $row => $_row_meta) {
		                    foreach ($field->repeaterChildren as $_key => $child) {
    		                    $childField = self::get_field_by_id( $form, $child );
		                        if( $_meta_key = self::get_field_meta_key( $childField ) ) {
			                        if( ! empty( $_row_meta[$_meta_key] ) ) {
				                        self::$row_field_values[$form['id']][$repeater][$row][$child] = self::_encode_field_value($_row_meta[$_meta_key], $childField);
									}
		                        }
		                    }
		                }
					}
	            } 
	            add_action( 'wp_footer', array(__CLASS__, 'enqueue_scripts') );
			}
        }
        return $form;
    }

    public static function gf_shortcode_atts( $output, $pairs, $atts ) {
        if( isset( $atts['update'] ) && is_numeric( $atts['update'] ) )
            self::$form_update_id = $atts['update'];
        return $output;
    }

    private static function get_field_by_id( $form , $id )
    {
        $fields = array_filter( $form['fields'] , function($field) use ($id) {
            return $field->id == $id;
        } );
        return end($fields);
    }

    private static function get_field_position( $fields , $id )
    {
	    $keys = array_keys( array_filter( $fields , function($field) use ($id) {
            return $field->id == $id;
        } ) );
        return end( $keys );
    }
    
    private static function get_field_meta_key( $field ) 
    {
	    return $field ? property_exists($field, 'postCustomFieldName') && $field->postCustomFieldName ? $field->postCustomFieldName : (!empty($field->label) ? str_replace( '-', '_',  sanitize_title( $field->label ) ) : 'repeater_field_'.$field->id) : false;
    }
    
    /**
	 * Get the plugin url.
	 * @return string
	 */
	public static function plugin_url() {
		return untrailingslashit( plugins_url( '/', __FILE__ ) );
	}

	/**
	 * Get the plugin path.
	 * @return string
	 */
	public static function plugin_path() {
		return untrailingslashit( plugin_dir_path( __FILE__ ) );
	}
    
    public static function enqueue_scripts() 
    {
		wp_register_script( 'gfpsrao-js', self::plugin_url() . '/assets/js/gfpsrao.js', array('jquery', 'gforms_repeater_js'), '1.0.0', true );
		wp_localize_script( 'gfpsrao-js', 'gfpsrao_settings', array(
			'row_field_values' => self::$row_field_values,
		));
		wp_enqueue_script( 'gfpsrao-js' );
    }
}