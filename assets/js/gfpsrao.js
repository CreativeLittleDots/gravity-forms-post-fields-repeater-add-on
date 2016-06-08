var repeaterFieldsValuesSet = false
jQuery(window).bind('gform_repeater_init_done', function() {
	if(!repeaterFieldsValuesSet) {
			for(var form_id in gfpsrao_settings.row_field_values) {
			var form = jQuery('#gform_' + form_id)
			for(var repeater in gfpsrao_settings.row_field_values[form_id]) {
    			for(var row in gfpsrao_settings.row_field_values[form_id][repeater]) {
    				for(var child_id in gfpsrao_settings.row_field_values[form_id][repeater][row]) {
        				var field_values = gfpsrao_settings.row_field_values[form_id][repeater][row][child_id];
    					for(var value = 0; (value+1) <= field_values.length; value++) {
        					var field_value = field_values[value];
        					var field_id = 'input_' + form_id + '_' + child_id + ( ( field_values.length > 1 ) ? '_' + ( parseInt(value) + 1 ) : '' ) + '-' + ( parseInt(repeater) + 1 ) + '-' + ( parseInt(row) + 1 );
        					console.log(field_id, field_values, field_values.length);
        					var field = form.find('[name="' + field_id + '"]');
        					if (field.is(':checkbox, :radio')) {
        					    form.find('input[name="' + field_id + '"][value="' + field_value + '"]').prop('checked', true);
        					} else {
        						field.val(field_id).trigger('change');
        					}
    					}
    				}
                }
			}
		}
		repeaterFieldsValuesSet = true;
	}
});