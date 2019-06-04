function mpgsLiveFields() {
    $("#mpgs_merchant_id").parents('.form-group').show();
    $("#mpgs_api_password").parents('.form-group').show();
    $("#mpgs_webhook_secret").parents('.form-group').show();
    $("#test_mpgs_merchant_id").parents('.form-group').hide();
    $("#test_mpgs_api_password").parents('.form-group').hide();
    $("#test_mpgs_webhook_secret").parents('.form-group').hide();
}

function mpgsTestFields() {
    $("#mpgs_merchant_id").parents('.form-group').hide();
    $("#mpgs_api_password").parents('.form-group').hide();
    $("#mpgs_webhook_secret").parents('.form-group').hide();
    $("#test_mpgs_merchant_id").parents('.form-group').show();
    $("#test_mpgs_api_password").parents('.form-group').show();
    $("#test_mpgs_webhook_secret").parents('.form-group').show();
}

$(document).ready(function() {
    var value = 0;
    value = $('input[name=mpgs_mode]:checked').val();

    if (value == 1) {
        mpgsLiveFields();
    } else {
        mpgsTestFields();
    }
    $('input[name=mpgs_mode]').on('change', function() {
        value = $('input[name=mpgs_mode]:checked').val();

        if (value == 1) {
            mpgsLiveFields();
        } else {
            mpgsTestFields();
        }
    });

    var value = 0;
    value = $('input[name=mpgs_api_url]:checked').val();
    if (value === "") {
        $('#mpgs_api_url_custom').parents('.form-group').show();
    } else {
        $('#mpgs_api_url_custom').parents('.form-group').hide();
    }
    $('input[name=mpgs_api_url]').on('change', function() {
        value = $('input[name=mpgs_api_url]:checked').val();
        if (value === "") {
            $('#mpgs_api_url_custom').parents('.form-group').show();
        } else {
            $('#mpgs_api_url_custom').parents('.form-group').hide();
        }
    });
});
