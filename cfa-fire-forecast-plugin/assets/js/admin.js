jQuery(document).ready(function($) {
    $('#cfa_color_scheme').change(function() {
        if ($(this).val() === 'custom') {
            $('#cfa_custom_colors').slideDown();
        } else {
            $('#cfa_custom_colors').slideUp();
        }
    });
    
    $('#cfa_custom_colors input[type="color"]').on('input', function() {
        $(this).next('span').text($(this).val());
    });
});
