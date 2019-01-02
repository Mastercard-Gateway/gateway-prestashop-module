function mpgsLiveFields() {
    $("#mpgs_merchant_id").parent().parent().show();
    $("#mpgs_api_password").parent().parent().show();
    $("#mpgs_webhook_secret").parent().parent().show();
    $("#test_mpgs_merchant_id").parent().parent().hide();
    $("#test_mpgs_api_password").parent().parent().hide();
    $("#test_mpgs_webhook_secret").parent().parent().hide();
}

function mpgsTestFields() {
    $("#mpgs_merchant_id").parent().parent().hide();
    $("#mpgs_api_password").parent().parent().hide();
    $("#mpgs_webhook_secret").parent().parent().hide();
    $("#test_mpgs_merchant_id").parent().parent().show();
    $("#test_mpgs_api_password").parent().parent().show();
    $("#test_mpgs_webhook_secret").parent().parent().show();
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
        $('#mpgs_api_url_custom').parent().parent().show();
    } else {
        $('#mpgs_api_url_custom').parent().parent().hide();
    }
    $('input[name=mpgs_api_url]').on('change', function() {
        value = $('input[name=mpgs_api_url]:checked').val();
        if (value === "") {
            $('#mpgs_api_url_custom').parent().parent().show();
        } else {
            $('#mpgs_api_url_custom').parent().parent().hide();
        }
    });
});
