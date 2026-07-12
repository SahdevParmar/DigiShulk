<?php
$ch = curl_init('https://api.razorpay.com/v1/orders');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
if ($response === false) {
    echo "cURL failed: " . curl_error($ch);
} else {
    echo "cURL works! Response code: " . curl_getinfo($ch, CURLINFO_HTTP_CODE);
}
curl_close($ch);
?>