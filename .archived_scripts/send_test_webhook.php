<?php
$url = 'https://sheeralateen.fix4.in/webhooks/leajlak.php';
$data = array('id' => '1109', 'status' => 'Order Picked');
$options = array(
    'http' => array(
        'header'  => "Content-type: application/json\r\nAuthorization: sheeratoken74\r\n",
        'method'  => 'POST',
        'content' => json_encode($data)
    )
);
$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);
echo "Webhook sent! Response: " . $result;
