<?php
function is_weebly_app($product){
    $target_category = 'weebly-apps'; 
    // Check if the product belongs to the target category
    if (has_term($target_category, 'product_cat', $product->get_id())) {
        return true;
    }else{
        return false;
    }
}

function parseRequest($request) {
    /**
     * URL parsing
     *
     * break the Request URL into route sections and GET parameters
     */
    if(stripos($request,'?') === false) {
        // no params
        //$route = explode('/', $request);
        $params = [];
    } else {
        // params included
        list($route, $url_params) = explode('?', $request, 2);
        $route = explode('/', $route);
        $params = [];
        parse_str($url_params, $params);
    }

    return $params;
}

?>