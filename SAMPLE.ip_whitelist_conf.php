<?php
// -*- mode: php; coding: utf-8 -*-

function ip_whitelist__conf__get_conf () {
    return array(
            'allowed_user_list' => array(
                    // examples:
                    //
                    //'user-1@example.org',
                    //'user-2@example.com',
                    ),
            
            'protected_dir_list' => array(
                    // examples:
                    //
                    //dirname(__FILE__).'/../administrator',
                    //dirname(__FILE__).'/../wp-admin',
                    ),
            );
}
