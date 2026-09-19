<?php

function countries()
{
    return array("Afghanistan", "Albania", "Algeria", "American Samoa", "Andorra", "Angola", "Anguilla", "Antarctica", "Antigua and Barbuda", "Argentina", "Armenia", "Aruba", "Australia", "Austria", "Azerbaijan", "Bahamas", "Bahrain", "Bangladesh", "Barbados", "Belarus", "Belgium", "Belize", "Benin", "Bermuda", "Bhutan", "Bolivia", "Bosnia and Herzegowina", "Botswana", "Bouvet Island", "Brazil", "British Indian Ocean Territory", "Brunei Darussalam", "Bulgaria", "Burkina Faso", "Burundi", "Cambodia", "Cameroon", "Canada", "Cape Verde", "Cayman Islands", "Central African Republic", "Chad", "Chile", "China", "Christmas Island", "Cocos (Keeling) Islands", "Colombia", "Comoros", "Congo", "Congo, the Democratic Republic of the", "Cook Islands", "Costa Rica", "Cote d'Ivoire", "Croatia (Hrvatska)", "Cuba", "Cyprus", "Czech Republic", "Denmark", "Djibouti", "Dominica", "Dominican Republic", "East Timor", "Ecuador", "Egypt", "El Salvador", "Equatorial Guinea", "Eritrea", "Estonia", "Ethiopia", "Falkland Islands (Malvinas)", "Faroe Islands", "Fiji", "Finland", "France", "France Metropolitan", "French Guiana", "French Polynesia", "French Southern Territories", "Gabon", "Gambia", "Georgia", "Germany", "Ghana", "Gibraltar", "Greece", "Greenland", "Grenada", "Guadeloupe", "Guam", "Guatemala", "Guinea", "Guinea-Bissau", "Guyana", "Haiti", "Heard and Mc Donald Islands", "Holy See (Vatican City State)", "Honduras", "Hong Kong", "Hungary", "Iceland", "India", "Indonesia", "Iran (Islamic Republic of)", "Iraq", "Ireland", "Israel", "Italy", "Jamaica", "Japan", "Jordan", "Kazakhstan", "Kenya", "Kiribati", "Korea, Democratic People's Republic of", "Korea, Republic of", "Kuwait", "Kyrgyzstan", "Lao, People's Democratic Republic", "Latvia", "Lebanon", "Lesotho", "Liberia", "Libyan Arab Jamahiriya", "Liechtenstein", "Lithuania", "Luxembourg", "Macau", "Macedonia, The Former Yugoslav Republic of", "Madagascar", "Malawi", "Malaysia", "Maldives", "Mali", "Malta", "Marshall Islands", "Martinique", "Mauritania", "Mauritius", "Mayotte", "Mexico", "Micronesia, Federated States of", "Moldova, Republic of", "Monaco", "Mongolia", "Montserrat", "Morocco", "Mozambique", "Myanmar", "Namibia", "Nauru", "Nepal", "Netherlands", "Netherlands Antilles", "New Caledonia", "New Zealand", "Nicaragua", "Niger", "Nigeria", "Niue", "Norfolk Island", "Northern Mariana Islands", "Norway", "Oman", "Pakistan", "Palau", "Panama", "Papua New Guinea", "Paraguay", "Peru", "Philippines", "Pitcairn", "Poland", "Portugal", "Puerto Rico", "Qatar", "Reunion", "Romania", "Russian Federation", "Rwanda", "Saint Kitts and Nevis", "Saint Lucia", "Saint Vincent and the Grenadines", "Samoa", "San Marino", "Sao Tome and Principe", "Saudi Arabia", "Senegal", "Seychelles", "Sierra Leone", "Singapore", "Slovakia (Slovak Republic)", "Slovenia", "Solomon Islands", "Somalia", "South Africa", "South Georgia and the South Sandwich Islands", "Spain", "Sri Lanka", "St. Helena", "St. Pierre and Miquelon", "Sudan", "Suriname", "Svalbard and Jan Mayen Islands", "Swaziland", "Sweden", "Switzerland", "Syrian Arab Republic", "Taiwan, Province of China", "Tajikistan", "Tanzania, United Republic of", "Thailand", "Togo", "Tokelau", "Tonga", "Trinidad and Tobago", "Tunisia", "Turkey", "Turkmenistan", "Turks and Caicos Islands", "Tuvalu", "Uganda", "Ukraine", "United Arab Emirates", "United Kingdom", "United States", "United States Minor Outlying Islands", "Uruguay", "Uzbekistan", "Vanuatu", "Venezuela", "Vietnam", "Virgin Islands (British)", "Virgin Islands (U.S.)", "Wallis and Futuna Islands", "Western Sahara", "Yemen", "Yugoslavia", "Zambia", "Zimbabwe");
}

function defaultPasswordStatus()
{
    return 'default';
}

function todayDateTime()
{
    return date('Y-m-d H:i:s', strtotime('now'));
}

function todayDate()
{
    return date('Y-m-d', strtotime('now'));
}

function getDateFormat($dateTime)
{
    return date('Y-m-d', strtotime($dateTime));
}

function getDateFormatWords($dateTime)
{
    return date('l M d, Y', strtotime($dateTime));
}

function fromDate()
{
    return date('Y-m-d' . ' 07:30:00', time());
}

function toDate()
{
    return date('Y-m-d' . ' 16:00:00', time());
}
function convertPercentToUnitScore($factor, $numerator, $denominator = 100)
{
    $converted_score = $numerator / $denominator * $factor;
    return sprintf("%01.1f", $converted_score);
}
function deleteSingleElementFromString($parent_string, $child_string)
{
    $string_array = explode('~', $parent_string);

    $count_array = count($string_array);

    for ($i = 0; $i < ($count_array); $i++) {

        if ($string_array[$i] == $child_string) {

            unset($string_array[$i]);
        }
    }
    return $new_parent_str = implode('~', array_unique($string_array));
}
function addSingleElementToString($parent_string, $child_string)
{
    if ($parent_string == '') {
        $str = $child_string;
    } else {
        $str = $parent_string . '~' . $child_string;
    }


    $string_array = array_unique(explode('~', $str));

    return $new_parent_str = implode('~', $string_array);
}

function randomColorCode()
{
    $tokens = 'ABC0123456789'; //'ABCDEF0123456789';
    $serial = '';
    for ($i = 0; $i < 6; $i++) {
        $serial .= $tokens[mt_rand(0, strlen($tokens) - 1)];
    }
    return '#' . $serial;
}
function randomPassword()
{
    // Used for real account passwords and password-reset tokens, so this must
    // be cryptographically secure — Str::random() uses random_bytes() under the hood.
    return \Illuminate\Support\Str::random(10);
}
function randomcode()
{
    $tokens = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ23456789';
    $serial = '';
    for ($i = 0; $i < 3; $i++) {
        $serial .= $tokens[mt_rand(0, strlen($tokens) - 1)];
    }
    return $serial;
}
function randomNumber()
{
    $tokens = '0123456789';
    $serial = '';
    for ($i = 0; $i < 4; $i++) {
        $serial .= $tokens[mt_rand(0, strlen($tokens) - 1)];
    }
    return $serial;
}

function hashing($string)
{
    $hash = hash('sha512', $string);
    return $hash;
}

function formatUniqNo($no)
{
    $no = $no * 1;
    if ($no < 10) {
        return '000' . $no;
    } else if ($no >= 10 && $no < 100) {
        return '00' . $no;
    } else if ($no >= 100 && $no < 1000) {
        return '0' . $no;
    } else {
        return $no;
    }
}
function mainDomainPublicPath($folder = '')
{
    return rtrim(env('APP_URL', ''), '/') . '/' . ltrim((string) $folder, '/');
}
function subdomainPublicPath($folder = '')
{
    return public_path((string) $folder);
}

function portalPulicPath($folder = '')
{
    return public_path((string) $folder);
}

function folderSize($dir)
{
    $size = 0;

    foreach (glob(rtrim($dir, '/') . '/*', GLOB_NOSORT) as $each) {
        $size += is_file($each) ? filesize($each) : folderSize($each);
    }

    // this size is in Byte
    // we want to convert it to GB
    // 1Gb = 1024 ^ 3 Bytes OR 1Gb = 2 ^ 30

    return $size;
    // return sizeFilter($size); //byteToGB($size);
}

function byteToGB($byte)
{
    $gb = $byte / 1024 / 1024 / 1024;
    return $gb;
}

function percentageDirUsage($dir_size, $total_usable)
{
    $used = $dir_size / $total_usable * 100;
    return (float) sprintf('%01.2f', $used);
}
function folderSizeFilter($bytes)
{
    $label = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');

    for ($i = 0; $bytes >= 1024 && $i < (count($label) - 1); $bytes /= 1024, $i++)
        ;

    return (round($bytes, 2) . $label[$i]);
}
