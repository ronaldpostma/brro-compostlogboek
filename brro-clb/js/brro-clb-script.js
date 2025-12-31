jQuery(document).ready(function($) {
    // Action change
    var clbAction = undefined;
    $('input[name="clb_action"]').change(function() {
        // show the rest of the form
        $('#clb-post-action-content').slideDown();
        // deselect radio with units
        $('input[name="clb_unit_type"]').prop('checked', false);
        if ($(this).val() === 'input') {
            $('.input-only').show();
            $('.output-only').hide();
            clbAction = 'input';
        } else {
            $('.input-only').hide();
            $('.output-only').show();
            clbAction = 'output';
        }
        // Recalculate weight when action changes (in case liter is selected)
        calculateTotalWeight();
    });

    // Change the '#unit_amount' number field to not show the native browser number input, but add a plus and minus button to increase and decrease the value by 0.25
    $('#unit_amount_decrease').click(function() {
        var currentValue = parseFloat($('#unit_amount').val()) || 0;
        if (currentValue > 0.25) {
            $('#unit_amount').val(currentValue - 0.25);
            calculateTotalWeight();
        }
    });
    $('#unit_amount_increase').click(function() {
        var currentValue = parseFloat($('#unit_amount').val()) || 0;
        if (currentValue < 100) {
            $('#unit_amount').val(currentValue + 0.25);
            calculateTotalWeight();
        }
    });

    // Function to calculate and update total weight
    function calculateTotalWeight() {
        var unitAmount = parseFloat($('#unit_amount').val()) || 0;
        var selectedUnit = $('input[name="clb_unit_type"]:checked').val();
        var totalWeight = 0;
        var unitWeight = 0;

        // Get current action if not set
        if (typeof clbAction === 'undefined') {
            clbAction = $('input[name="clb_action"]:checked').val();
        }

        // If no unit selected or no action selected, weight is 0
        if (!selectedUnit || !clbAction) {
            $('#clb_total_weight').val(0);
            return;
        }

        // Handle 'liter' unit type - use volume weights from settings
        if (selectedUnit === 'liter') {
            if (clbAction === 'input' && typeof brro_clb_ajax !== 'undefined' && brro_clb_ajax.units.input_volweight) {
                unitWeight = parseFloat(brro_clb_ajax.units.input_volweight) || 0;
            } else if (clbAction === 'output' && typeof brro_clb_ajax !== 'undefined' && brro_clb_ajax.units.output_volweight) {
                unitWeight = parseFloat(brro_clb_ajax.units.output_volweight) || 0;
            }
        } 
        // Handle 'kg' unit type - 1 kg = 1 kg
        else if (selectedUnit === 'kg') {
            unitWeight = 1;
        }
        // Handle custom units - get weight from data-weight attribute
        else {
            var selectedInput = $('input[name="clb_unit_type"]:checked');
            var dataWeight = selectedInput.data('weight');
            if (dataWeight !== undefined && !isNaN(parseFloat(dataWeight))) {
                unitWeight = parseFloat(dataWeight);
            }
        }

        // Calculate total weight
        if (unitWeight > 0) {
            if (selectedUnit === 'kg' || selectedUnit === 'liter') {
                // For kg and liter, use unitAmount
                if (unitAmount > 0) {
                    totalWeight = unitWeight * unitAmount;
                }
            } else {
                // For custom units, ignore unitAmount and use data-weight as-is
                totalWeight = unitWeight;
            }
        }

        // Update the field
        $('#clb_total_weight').val(totalWeight.toFixed(2));
        // If total weight is greater than 0, remove the 'disabled' attribute from the submit button
        if (totalWeight > 0) {
            $('input[name="compostlogboek_submit"]').removeAttr('disabled');
        } else {
            $('input[name="compostlogboek_submit"]').attr('disabled', 'disabled');
        }
    }

    // Calculate weight when unit type changes
    $('input[name="clb_unit_type"]').change(function() {
        // Toggle active classes on unit amount buttons based on selected unit
        var selectedId = $(this).attr('id');
        var $buttonsWrapper = $('.clb-unit-amount-buttons');

        if (selectedId === 'unit_type_liter') {
            $buttonsWrapper.addClass('clb-active-liter').removeClass('clb-active-kilo');
        } else if (selectedId === 'unit_type_kg') {
            $buttonsWrapper.addClass('clb-active-kilo').removeClass('clb-active-liter');
        } else {
            $buttonsWrapper.removeClass('clb-active-liter clb-active-kilo');
        }

        calculateTotalWeight();
    });

    // Calculate weight when unit amount changes
    $('#unit_amount').change(function() {
        calculateTotalWeight();
    });

    // Also recalculate on input (for real-time updates as user types)
    $('#unit_amount').on('input', function() {
        calculateTotalWeight();
    });

    // Privacy policy trigger
    $('.clb_privacy_policy_trigger').click(function() {
        $('.clb_privacy_policy_content').slideToggle();
    });

    // Generate a random 10 character (numbers and letters) device ID and store it in localStorage
    var deviceId = localStorage.getItem('brro_clb_device_id');
    if (!deviceId) {
        deviceId = Math.random().toString(36).substring(2, 12);
        localStorage.setItem('brro_clb_device_id', deviceId);
        $('#clb_device_id').val(deviceId);
    } else {
        $('#clb_device_id').val(deviceId);
    }

    // Email validation function
    function isValidEmail(email) {
        if (!email || email.trim() === '') {
            return false;
        }
        // Basic email regex validation
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email.trim());
    }

    // Function to update email save checkbox state based on email validity
    function updateEmailSaveCheckbox() {
        var email = $('#clb_user_email').val();
        var isValid = isValidEmail(email);
        
        if (isValid) {
            // Enable checkboxes
            $('#clb_user_email_save').css({
                'opacity': '1',
                'pointer-events': 'auto'
            }).prop('disabled', false);
            $('#clb_send_confirmation_email').css({
                'opacity': '1',
                'pointer-events': 'auto'
            }).prop('disabled', false);
        } else {
            // Disable checkboxes
            $('#clb_user_email_save').css({
                'opacity': '0.5',
                'pointer-events': 'none'
            }).prop('disabled', true).prop('checked', false);
            $('#clb_send_confirmation_email').css({
                'opacity': '0.5',
                'pointer-events': 'none'
            }).prop('disabled', true).prop('checked', false);
        }
    }

    // Check email validity on input and change events
    $('#clb_user_email').on('input change blur', function() {
        updateEmailSaveCheckbox();
    });

    // If email is already in localStorage, set it in the form and check the "Onthoud mijn emailadres" checkbox
    var email = localStorage.getItem('brro_clb_user_email');
    if (email) {
        $('#clb_user_email').val(email);
        // Validate the email before checking the checkbox
        if (isValidEmail(email)) {
            $('#clb_user_email_save').prop('checked', true);
        }
    }
    
    // Load saved confirmation email preference from localStorage
    var sendConfirmationEmail = localStorage.getItem('brro_clb_send_confirmation_email');
    if (sendConfirmationEmail === 'true') {
        $('#clb_send_confirmation_email').prop('checked', true);
    }
    
    // Initial check on page load
    updateEmailSaveCheckbox();


    // Submit form
    $('#compostlogboek_formulier').submit(function(e) {
    // If the "Onthoud mijn emailadres" checkbox is checked, save the email in localStorage
    var email = $('#clb_user_email').val();
    var saveEmail = $('#clb_user_email_save').is(':checked');
    if (saveEmail && email) {
        try {
            localStorage.setItem('brro_clb_user_email', email);
        } catch (e) {
            // localStorage might be unavailable (privacy mode etc.), ignore errors
        }
    }
    // Save confirmation email preference if checkbox is checked
    var sendConfirmationEmail = $('#clb_send_confirmation_email').is(':checked');
    if (sendConfirmationEmail) {
        try {
            localStorage.setItem('brro_clb_send_confirmation_email', 'true');
        } catch (e) {
            // localStorage might be unavailable (privacy mode etc.), ignore errors
        }
    } else {
        try {
            localStorage.removeItem('brro_clb_send_confirmation_email');
        } catch (e) {
            // localStorage might be unavailable (privacy mode etc.), ignore errors
        }
    }
    });
});