<?php
$ch = curl_init('https://www.googleapis.com/oauth2/v3/certs');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$r = curl_exec($ch);

if ($r === false) {
    echo curl_error($ch);
} else {
    echo "SSL OK";
}
