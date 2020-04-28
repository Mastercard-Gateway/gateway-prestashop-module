/*
 * Copyright (c) 2019-2020 Mastercard
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

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
