<?php
// -*- mode: php; coding: utf-8 -*-
//
// Copyright 2013 Andrej A Antonov <polymorphm@gmail.com>.
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU Lesser General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Lesser General Public License for more details.
//
// You should have received a copy of the GNU Lesser General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.

global $no_use_main;

if (!$no_use_main) {
    function index__error_handler ($errno, $errstr) {
        throw new ErrorException(sprintf('[%s] %s', $errno, $errstr));
    }

    if (!ini_get('display_errors')) {
        ini_set('display_errors', 1);
    }

    set_error_handler('index__error_handler');
}

require dirname(__FILE__).'/ip_whitelist_conf.php';
require dirname(__FILE__).'/ip_whitelist_tpl.php';

function ip_whitelist__HTACCESS_BEGIN_BLOCK_LINE () {
    return '# ----- BEGIN OF BLOCK: ip_whitelist -----';
}

function ip_whitelist__HTACCESS_END_BLOCK_LINE () {
    return '# ----- END OF BLOCK: ip_whitelist -----';
}

function ip_whitelist__stripslashes_if_gpc ($value) {
    if (!function_exists('get_magic_quotes_gpc') || !get_magic_quotes_gpc()) {
        return $value;
    }
    
    return stripslashes($value);
}

function ip_whitelist__get_request ($request_name) {
    if (!array_key_exists($request_name, $_REQUEST)) {
        return;
    }
    
    return ip_whitelist__stripslashes_if_gpc($_REQUEST[$request_name]);
}

function ip_whitelist__init_ctx (&$ctx) {
    $ctx['conf'] = ip_whitelist__conf__get_conf();
    
    if (!is_array($ctx['conf'])) {
        throw new Exception('!is_array($ctx[\'conf\'])');
    }
    
    if (!is_array($ctx['conf']['allow_user_list'])) {
        throw new Exception('!is_array($ctx[\'conf\'][\'allow_user_list\'])');
    }
    
    if (!is_array($ctx['conf']['protected_dir_list'])) {
        throw new Exception('!is_array($ctx[\'conf\'][\'protected_dir_list\'])');
    }
}

function ip_whitelist__show_page (&$ctx) {
    $html = ip_whitelist__tpl__get_html($ctx);
    
    header('content-type: text/html;charset=utf-8');
    header('X-Frame-Options: DENY');
    
    echo $html;
}

function ip_whitelist__check_auth (&$ctx, $assertion) {
    $curl = curl_init();
    curl_setopt_array(
            $curl,
                    array(
                            CURLOPT_URL => 'https://verifier.login.persona.org/verify',
                            CURLOPT_RETURNTRANSFER => TRUE,
                            CURLOPT_POST => TRUE,
                            CURLOPT_POSTFIELDS =>
                                    'assertion='.
                                    urlencode($assertion).
                                    '&audience='.
                                    urlencode(
                                            (array_key_exists('HTTPS', $_SERVER) && $_SERVER['HTTPS']?'https':'http').
                                            '://'.
                                            $_SERVER['HTTP_HOST']
                                            ),
                            )
            );
    $raw_verify_result = curl_exec($curl);
    curl_close($curl);
    
    $verify_result = json_decode($raw_verify_result, TRUE);
    $verify_status = array_key_exists('status', $verify_result) ? $verify_result['status'] : NULL;
    
    if ($verify_status != 'okay') {
        return NULL;
    }
    
    $email = array_key_exists('email', $verify_result) ? $verify_result['email'] : NULL;
    
    if (!$email || !in_array($email, $ctx['conf']['allow_user_list'])) {
        return NULL;
    }
    
    return $email;
}

function ip_whitelist__lock (&$ctx, $protected_dir) {
    $lock = fopen($protected_dir.'/.htaccess.lock', 'w');
    flock($lock, LOCK_EX);
    
    return $lock;
}

function ip_whitelist__unlock (&$ctx, $lock) {
    flock($lock, LOCK_UN);
    fclose($lock);
}

function ip_whitelist__atomic_write (&$ctx, $path, $data) {
    $new_path = $path.'.new-'.getmypid();
    file_put_contents($new_path, $data);
    rename($new_path, $path);
}

function ip_whitelist__get_state_path (&$ctx, $protected_dir) {
    return $protected_dir.'/.htaccess.ip_whitelist';
}

function ip_whitelist__read_state (&$ctx, $protected_dir) {
    $path = ip_whitelist__get_state_path($ctx, $protected_dir);
    
    if (!is_file($path)) {
        return array();
    }
    
    $raw_data = file_get_contents($path);
    $state = json_decode($raw_data, TRUE);
    
    return $state;
}

function ip_whitelist__write_state (&$ctx, $protected_dir, &$state) {
    $path = ip_whitelist__get_state_path($ctx, $protected_dir);
    $raw_data = json_encode($state);
    ip_whitelist__atomic_write($ctx, $path, $raw_data);
}

function ip_whitelist__filter_state (&$ctx, &$state) {
    foreach ($state as $state_email => $state_ip) {
        if (!in_array($state_email, $ctx['conf']['allow_user_list'])) {
            unset($state[$state_email]);
        }
    }
}

function ip_whitelist__create_htaccess_block (&$ctx, $protected_dir, &$state) {
    $block = '';
    
    $block .= ip_whitelist__HTACCESS_BEGIN_BLOCK_LINE()."\n";
    $block .= '# AUTO GENERATED: '.date('r')."\n";
    
    $block .= "\n";
    
    $block .= 'Order Deny,Allow'."\n";
    $block .= 'Deny from all'."\n";
    
    $block .= "\n";
    
    $state_ip_used = array();
    foreach ($state as $state_ip) {
        if (in_array($state_ip, $state_ip_used)) {
            continue;
        }
        
        $state_ip_used []= $state_ip;
        $block .= 'Allow from '.$state_ip."\n";
    }
    
    $block .= "\n";
    
    $block .= ip_whitelist__HTACCESS_END_BLOCK_LINE();
    
    return $block;
}

function ip_whitelist__rewrite_htaccess (&$ctx, $protected_dir, &$state) {
    $block = ip_whitelist__create_htaccess_block($ctx, $protected_dir, $state);
    $path = $protected_dir.'/.htaccess';
    
    if (is_file($path)) {
        $raw_data = file_get_contents($path);
    } else {
        $raw_data = '';
    }
    
    // XXX we MUST NOT using ``mb_...`` functions -- because the file in unknown encoding.
    //          we will assert that, the file is just binary.
    
    $begin_block_pos = strpos($raw_data, ip_whitelist__HTACCESS_BEGIN_BLOCK_LINE());
    $end_block_pos = strpos($raw_data, ip_whitelist__HTACCESS_END_BLOCK_LINE(), $begin_block_pos);
    
    if ($begin_block_pos !== FALSE && $end_block_pos !== FALSE) {
        $data =
                substr($raw_data, 0, $begin_block_pos).
                $block.
                substr($raw_data, $end_block_pos + strlen(ip_whitelist__HTACCESS_END_BLOCK_LINE()));
    } else {
        $data = $block."\n".$raw_data;
    }
    
    ip_whitelist__atomic_write($ctx, $path, $data);
}

function ip_whitelist__open_action (&$ctx, $email, $protected_dir) {
    $lock = ip_whitelist__lock($ctx, $protected_dir);
    $state = ip_whitelist__read_state($ctx, $protected_dir);
    ip_whitelist__filter_state($ctx, $state);
    
    $state[$email] = $_SERVER['REMOTE_ADDR'];
    
    ip_whitelist__rewrite_htaccess($ctx, $protected_dir, $state);
    ip_whitelist__write_state($ctx, $protected_dir, $state);
    ip_whitelist__unlock($ctx, $lock);
}

function ip_whitelist__close_action (&$ctx, $email, $protected_dir) {
    $lock = ip_whitelist__lock($ctx, $protected_dir);
    $state = ip_whitelist__read_state($ctx, $protected_dir);
    ip_whitelist__filter_state($ctx, $state);
    
    unset($state[$email]);
    
    ip_whitelist__rewrite_htaccess($ctx, $protected_dir, $state);
    ip_whitelist__write_state($ctx, $protected_dir, $state);
    ip_whitelist__unlock($ctx, $lock);
}

function ip_whitelist__main () {
    $ctx = array();
    ip_whitelist__init_ctx($ctx);
    
    if (!array_key_exists('HTTP_X_REQUESTED_WITH', $_SERVER) ||
            $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') {
        ip_whitelist__show_page($ctx);
        return;
    }
    
    $raw_data = ip_whitelist__get_request('data');
    $data = json_decode($raw_data, TRUE);
    
    $assertion = $data['assertion'];
    $action_name = $data['action_name'];
    
    $email = ip_whitelist__check_auth($ctx, $assertion);
    
    if (!$email) {
        header('content-type: application/json;charset=utf-8');
        echo json_encode(array(
                'error' => 'AuthFail',
                ));
        return;
    }
    
    if ($action_name == 'open') {
        foreach ($ctx['conf']['protected_dir_list'] as $protected_dir) {
            ip_whitelist__open_action($ctx, $email, $protected_dir);
        }
        
        header('content-type: application/json;charset=utf-8');
        echo json_encode(array(
                'error' => '',
                ));
        return;
    }
    
    if ($action_name == 'close') {
        foreach ($ctx['conf']['protected_dir_list'] as $protected_dir) {
            ip_whitelist__close_action($ctx, $email, $protected_dir);
        }
        
        header('content-type: application/json;charset=utf-8');
        echo json_encode(array(
                'error' => '',
                ));
        return;
    }
    
    header('content-type: application/json;charset=utf-8');
    echo json_encode(array(
            'error' => 'UnknownAction',
            ));
}

if (!$no_use_main) {
    $no_use_main = TRUE;
    
    ip_whitelist__main();
}
