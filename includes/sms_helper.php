<?php
function send_payment_sms($phone, $amount){
    include_once 'config.php';

    $message = "You have paid RMC Rs.$amount for spot tax. Thank you.\n"
             . "તમે RMC ને સ્પોટ ટેક્સ પેટે રૂ.$amount ચૂકવ્યા છે. આભાર.";

    $url = "https://www.fast2sms.com/dev/bulkV2";
    $params = [
        "authorization" => FAST2SMS_API_KEY,
        "message" => $message,
        "language" => "unicode",
        "route" => "q",
        "numbers" => $phone
    ];

    $ch = curl_init($url . "?" . http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    return $response; // we'll actually use this return value now, see below
}
?>