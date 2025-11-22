jQuery(document).ready(function($) {
    // Toggle custom colors panel
    $('#cfa_color_scheme').change(function() {
        if ($(this).val() === 'custom') {
            $('#cfa_custom_colors').slideDown();
        } else {
            $('#cfa_custom_colors').slideUp();
        }
    });
    
    // Sync color picker to hex input
    $('.cfa-color-picker').on('input', function() {
        var key = $(this).data('key');
        var color = $(this).val().toUpperCase();
        $('.cfa-hex-input[data-key="' + key + '"]').val(color);
        $('input[name="cfa_fire_forecast_options[custom_color_' + key + ']"]').val(color);
    });
    
    // Sync hex input to color picker
    $('.cfa-hex-input').on('input', function() {
        var key = $(this).data('key');
        var hex = $(this).val().toUpperCase();
        
        // Validate hex color
        if (/^#[0-9A-F]{6}$/i.test(hex)) {
            $('.cfa-color-picker[data-key="' + key + '"]').val(hex);
            $('input[name="cfa_fire_forecast_options[custom_color_' + key + ']"]').val(hex);
        }
    });
    
    // Reset button
    $('.cfa-reset-color').on('click', function() {
        var key = $(this).data('key');
        var defaultColor = $(this).data('default').toUpperCase();
        
        $('.cfa-color-picker[data-key="' + key + '"]').val(defaultColor);
        $('.cfa-hex-input[data-key="' + key + '"]').val(defaultColor);
        $('input[name="cfa_fire_forecast_options[custom_color_' + key + ']"]').val(defaultColor);
    });
});
