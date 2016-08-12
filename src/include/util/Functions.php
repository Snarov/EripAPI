<?php

function get_http_response_code($theURL) {
    stream_context_set_default( array ( "ssl"=>array(
        "verify_peer"=>false,
        "verify_peer_name"=>false,
    ),
    ));
    
    $headers = get_headers($theURL);
    return substr($headers[0], 9, 3);
}
