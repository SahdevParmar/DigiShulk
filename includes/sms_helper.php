<?php
function send_payment_sms($phone, $amount){
    include_once 'config.php';

    $message = "You have paid RMC Rs.$amount for spot tax. Thank you.\n"
             . "તમે RMC ને સ્પોટ ટેક્સ પેટે રૂ.$amount ચૂકવ્યા છે. આભાર.";

    // Using Meta Cloud API (Official WhatsApp Business API) as a placeholder.
    // If you are using a different provider (e.g. UltraMsg, Twilio), you can update the URL and payload here.
    
    // Make sure phone number has country code. Assuming India (+91)
    if(strlen($phone) == 10) {
        $phone = "91" . $phone;
    }

    if(!defined('WHATSAPP_API_URL') || !defined('WHATSAPP_TOKEN')) {
        return "Error: WhatsApp API not configured.";
    }

    $url = WHATSAPP_API_URL;
    $token = WHATSAPP_TOKEN;

    $payload = [
        "messaging_product" => "whatsapp",
        "to" => $phone,
        "type" => "text",
        "text" => [
            "body" => $message
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $token,
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}
?>