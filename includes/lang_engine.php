<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. Check live session, 2. Fallback to long-lived browser cookie, 3. Default to English
if (isset($_SESSION['lang'])) {
    $current_lang = $_SESSION['lang'];
} elseif (isset($_COOKIE['user_lang'])) {
    $current_lang = $_COOKIE['user_lang'];
    $_SESSION['lang'] = $current_lang; // Keep session synced
} else {
    $current_lang = 'en';
}

$dictionary = [
    'en' => [
        // Header Nav Links
        'home' => '<i class="fa-solid fa-house" aria-hidden="true"></i> Home', 'manage' => '<i class="fa-solid fa-users-gear" aria-hidden="true"></i> Inspectors', 'history' => '<i class="fa-solid fa-clipboard-list" aria-hidden="true"></i> History', 'settings' => '<i class="fa-solid fa-gear" aria-hidden="true"></i> Profile', 'logout' => 'Log Out',
        
        // Form & Interface Labels (dashboard.php & seizure_form.php)
        'new_entry' => 'New Spot Tax Entry',
        'shop_name' => 'Shop/Stall Name',
        'address' => 'Address/Location',
        'phone' => 'Phone Number',
        'select_stall' => 'Select Stall Type:',
        'rekdi' => 'Rekdi (Daily)',
        'mandap' => 'Festival Mandap',
        'chhajli' => 'Chhajli',
        'payment_mode' => 'Payment Mode:',
        'cash' => 'Cash',
        'upi' => 'UPI / QR Code',
        'enter_size' => 'Enter Size (in sq ft):',
        'size_placeholder' => 'Size...',
        'btn_calculate' => 'Calculate & Generate Invoice',
        
        // Filter & History Labels (history.php)
        'filters_title' => 'Search & Filter Archives',
        'from' => 'From:',
        'to' => 'To:',
        'status' => 'Status:',
        'all' => 'All Records',
        'paid' => 'Paid',
        'pending' => 'Pending',
        'btn_apply' => 'Apply Search Filters',
        
        // Table Headers
        'th_inspector' => 'Inspector',
        'th_shop' => 'Shop Name',
        'th_amount' => 'Amount',
        'th_status' => 'Status',
        'th_time' => 'Timestamp',
        // English — add inside 'en' => [ ... ]
'rmc_title' => 'RMC Encroachment Removal Report',
'dept_title' => 'Central Zone Office',
'team_leader' => 'Team Leader Name',
'zone' => 'Zone',
'team_no' => 'Team Number',
'date' => 'Date',
'godown_no' => 'Godown Register No.',
'item_details' => 'Seized Item Details',
'quantity' => 'Quantity Seized',
'owner_name' => 'Owner/Merchant Name',
'location' => 'Seizure Location',
'submit' => 'Submit Report',
'success_msg' => 'Seizure report logged successfully!',
// English
'items_section_title' => 'Seized Items',
'add_item' => 'Add Another Item',
'items_saved' => 'items logged',
'no_items_error' => 'Please add at least one seized item.',
    ],
    'hi' => [
        'home' => '<i class="fa-solid fa-house" aria-hidden="true"></i> होम', 'manage' => '<i class="fa-solid fa-users-gear" aria-hidden="true"></i> प्रबंधन', 'history' => '<i class="fa-solid fa-clipboard-list" aria-hidden="true"></i> इतिहास', 'settings' => '<i class="fa-solid fa-gear" aria-hidden="true"></i> सेटिंग्स', 'logout' => 'लॉग आउट',
        
        'new_entry' => 'नई हाजिर कर प्रविष्टि',
        'shop_name' => 'दुकान / स्टॉल का नाम',
        'address' => 'पता / स्थान',
        'phone' => 'फ़ोन नंबर',
        'select_stall' => 'स्टॉल का प्रकार चुनें:',
        'rekdi' => 'रेकड़ी (दैनिक)',
        'mandap' => 'त्यौहार मंडप',
        'chhajli' => 'छाजली',
        'payment_mode' => 'भुगतान का प्रकार:',
        'cash' => 'नकद (Cash)',
        'upi' => 'यूपीआई / क्यूआर कोड',
        'enter_size' => 'आकार दर्ज करें (वर्ग फुट में):',
        'size_placeholder' => 'वर्ग फुट संख्या...',
        'btn_calculate' => 'गणना करें और इनवॉइस बनाएं',
        
        'filters_title' => 'संग्रह खोजें और फ़िल्टर करें',
        'from' => 'दिनांक से:',
        'to' => 'दिनांक तक:',
        'status' => 'स्थिति:',
        'all' => 'सभी रिकॉर्ड',
        'paid' => 'भुगतान किया गया',
        'pending' => 'लंबित',
        'btn_apply' => 'फ़िल्टर लागू करें',
        
        'th_inspector' => 'निरीक्षक',
        'th_shop' => 'दुकान का नाम',
        'th_amount' => 'कुल राशि',
        'th_status' => 'स्थिति',
        'th_time' => 'समय संकेत',
        // Hindi — add inside 'hi' => [ ... ]
'rmc_title' => 'RMC अतिक्रमण हटाओ रिपोर्ट',
'dept_title' => 'सेंट्रल ज़ोन कार्यालय',
'team_leader' => 'टीम लीडर का नाम',
'zone' => 'ज़ोन',
'team_no' => 'टीम नंबर',
'date' => 'तारीख',
'godown_no' => 'गोडाउन रजिस्टर नंबर',
'item_details' => 'जब्त किए गए सामान का विवरण',
'quantity' => 'जब्त की गई मात्रा',
'owner_name' => 'मालिक/व्यापारी का नाम',
'location' => 'जब्ती का स्थान',
'submit' => 'रिपोर्ट सबमिट करें',
'success_msg' => 'जब्ती रिपोर्ट सफलतापूर्वक दर्ज हुई!',
// Hindi
'items_section_title' => 'जब्त किए गए सामान',
'add_item' => 'एक और वस्तु जोड़ें',
'items_saved' => 'वस्तुएं दर्ज की गईं',
'no_items_error' => 'कृपया कम से कम एक जब्त वस्तु जोड़ें।',

    ],
    'gu' => [
        'home' => '<i class="fa-solid fa-house" aria-hidden="true"></i> મુખ્ય પૃષ્ઠ', 'manage' => '<i class="fa-solid fa-users-gear" aria-hidden="true"></i> મેનેજ કરો', 'history' => '<i class="fa-solid fa-clipboard-list" aria-hidden="true"></i> ઇતિહાસ', 'settings' => '<i class="fa-solid fa-gear" aria-hidden="true"></i> સેટિંગ્સ', 'logout' => 'લોગઆઉટ',
        
        'new_entry' => 'નવી સ્પોટ ટેક્સ એન્ટ્રી',
        'shop_name' => 'દુકાન / ગલ્લાનું નામ',
        'address' => 'સરનામું / લોકેશન',
        'phone' => 'મોબાઇલ નંબર',
        'select_stall' => 'સ્ટોલનો પ્રકાર પસંદ કરો:',
        'rekdi' => 'રેકડી (દૈનિક)',
        'mandap' => 'ફેસ્ટિવલ મંડપ',
        'chhajli' => 'છાજલી',
        'payment_mode' => 'ચુકવણીનો પ્રકાર:',
        'cash' => 'રોકડા (Cash)',
        'upi' => 'UPI / QR કોડ',
        'enter_size' => 'સાઇઝ દાખલ કરો (ચોરસ ફૂટમાં):',
        'size_placeholder' => 'માપ ચો.ફૂટ...',
        'btn_calculate' => 'ગણતરી કરો અને ઇન્વોઇસ બનાવો',
        
        'filters_title' => 'આર્કાઇવ્સ ફિલ્ટર અને સર્ચ',
        'from' => 'આ તારીખથી:',
        'to' => 'આ તારીખ સુધી:',
        'status' => 'સ્ટેટસ:',
        'all' => 'બધા રેકોર્ડ્સ',
        'paid' => 'ચૂકવેલ',
        'pending' => 'બાકી',
        'btn_apply' => 'ફિલ્ટર્સ લાગુ કરો',
        
        'th_inspector' => 'ઇન્સ્પેક્ટર',
        'th_shop' => 'દુકાનનું નામ',
        'th_amount' => 'કુલ રકમ',
        'th_status' => 'સ્ટેટસ',
        'th_time' => 'તારીખ અને સમય',
        // Gujarati — add inside 'gu' => [ ... ] (matches the real form's own wording)
'rmc_title' => 'RMC દબાણ હટાવ અહેવાલ',
'dept_title' => 'મધ્યઝોન કચેરી',
'team_leader' => 'ટીમ લીડરનું નામ',
'zone' => 'ઝોન',
'team_no' => 'ટીમ નં.',
'date' => 'તારીખ',
'godown_no' => 'ગોડાઉન રજીસ્ટર ક્રમાંક',
'item_details' => 'જપ્ત કરેલ માલસામાનની વિગત',
'quantity' => 'જપ્ત કરેલ સંખ્યા',
'owner_name' => 'માલસામાન ધારકનું નામ',
'location' => 'જપ્ત કરેલ સ્થળ',
'submit' => 'રિપોર્ટ સબમિટ કરો',
'success_msg' => 'જપ્તીનો અહેવાલ સફળતાપૂર્વક નોંધાયો!',
// Gujarati
'items_section_title' => 'જપ્ત કરેલ વસ્તુઓ',
'add_item' => 'બીજી વસ્તુ ઉમેરો',
'items_saved' => 'વસ્તુઓ નોંધાઈ',
'no_items_error' => 'કૃપા કરીને ઓછામાં ઓછી એક જપ્ત વસ્તુ ઉમેરો.',
    ]
];

function __($key) {
    global $dictionary, $current_lang;
    return $dictionary[$current_lang][$key] ?? $dictionary['en'][$key] ?? $key;
}
?>
