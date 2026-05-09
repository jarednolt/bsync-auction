(function($) {
    'use strict';

    function normalizeDob(raw) {
        var digits = String(raw || '').replace(/\D/g, '');
        if (digits.length !== 8) {
            return '';
        }

        var y1 = parseInt(digits.substring(0, 4), 10);
        var m1 = parseInt(digits.substring(4, 6), 10);
        var d1 = parseInt(digits.substring(6, 8), 10);
        if (y1 >= 1900 && y1 <= 2100 && m1 >= 1 && m1 <= 12 && d1 >= 1 && d1 <= 31) {
            return digits.substring(0, 4) + '-' + digits.substring(4, 6) + '-' + digits.substring(6, 8);
        }

        var m2 = parseInt(digits.substring(0, 2), 10);
        var d2 = parseInt(digits.substring(2, 4), 10);
        var y2 = parseInt(digits.substring(4, 8), 10);
        if (y2 >= 1900 && y2 <= 2100 && m2 >= 1 && m2 <= 12 && d2 >= 1 && d2 <= 31) {
            return digits.substring(4, 8) + '-' + digits.substring(0, 2) + '-' + digits.substring(2, 4);
        }

        return '';
    }

    function buildAddress(street, city, state, postalCode) {
        var lineOne = String(street || '').trim();
        var lineTwoParts = [String(city || '').trim(), String(state || '').trim(), String(postalCode || '').trim()].filter(Boolean);
        var lineTwo = '';

        if (lineTwoParts.length >= 2) {
            lineTwo = lineTwoParts[0] + ', ' + lineTwoParts.slice(1).join(' ');
        } else if (lineTwoParts.length === 1) {
            lineTwo = lineTwoParts[0];
        }

        return [lineOne, lineTwo].filter(Boolean).join('\n').trim();
    }

    function parseLicense(raw) {
        var text = String(raw || '').replace(/\r/g, '\n');
        var lines = text.split(/\n+/);
        var parsed = {
            firstName: '',
            lastName: '',
            middleName: '',
            licenseNumber: '',
            street: '',
            city: '',
            state: '',
            postalCode: '',
            birthdateRaw: '',
            birthdateIso: '',
            address: '',
            summary: ''
        };

        lines.forEach(function(line) {
            var value = String(line || '').trim();
            if (value.length < 4) {
                return;
            }

            var code = value.substring(0, 3);
            var data = value.substring(3).trim();

            if (code === 'DAC') {
                parsed.firstName = data;
            }
            if (code === 'DCS') {
                parsed.lastName = data;
            }
            if (code === 'DAD') {
                parsed.middleName = data;
            }
            if (code === 'DAQ') {
                parsed.licenseNumber = data;
            }
            if (code === 'DAG') {
                parsed.street = data;
            }
            if (code === 'DAI') {
                parsed.city = data;
            }
            if (code === 'DAJ') {
                parsed.state = data;
            }
            if (code === 'DAK') {
                parsed.postalCode = data;
            }
            if (code === 'DBB') {
                parsed.birthdateRaw = data;
            }
        });

        if (!parsed.firstName && !parsed.lastName) {
            var fullNameMatch = text.match(/DAA([^\n]+)/);
            if (fullNameMatch && fullNameMatch[1]) {
                var parts = fullNameMatch[1].trim().split(',');
                if (parts.length >= 2) {
                    parsed.lastName = parts[0].trim();
                    parsed.firstName = parts[1].trim();
                }
            }
        }

        parsed.birthdateIso = normalizeDob(parsed.birthdateRaw);
        parsed.address = buildAddress(parsed.street, parsed.city, parsed.state, parsed.postalCode);
        parsed.summary = [parsed.firstName, parsed.middleName, parsed.lastName].filter(Boolean).join(' ');

        return parsed;
    }

    $(document).on('click', '#bsync_auction_parse_license_button', function() {
        var raw = $('#bsync_auction_license_raw').val() || '';
        var parsed = parseLicense(raw);
        var result = [];

        if (parsed.summary) {
            result.push('Name: ' + parsed.summary);
        }

        if (parsed.licenseNumber) {
            result.push('License #: ' + parsed.licenseNumber);
        }

        if (parsed.address) {
            result.push('Address: parsed');
        }

        if (parsed.birthdateIso) {
            result.push('Birthdate: ' + parsed.birthdateIso);
        }

        if (!result.length) {
            $('#bsync_auction_license_result').text('No recognizable AAMVA fields were found.');
            return;
        }

        if ($('#first_name').length && parsed.firstName) {
            $('#first_name').val(parsed.firstName);
        }

        if ($('#last_name').length && parsed.lastName) {
            $('#last_name').val(parsed.lastName);
        }

        if ($('#bsync_member_number').length && parsed.licenseNumber && !String($('#bsync_member_number').val() || '').trim()) {
            $('#bsync_member_number').val(parsed.licenseNumber);
        }

        if ($('#bsync_member_address').length && parsed.address) {
            $('#bsync_member_address').val(parsed.address);
        }

        if ($('#bsync_member_main_birthdate').length && parsed.birthdateIso) {
            $('#bsync_member_main_birthdate').val(parsed.birthdateIso);
        }

        $('#bsync_auction_license_result').text(result.join(' | '));
    });
})(jQuery);
