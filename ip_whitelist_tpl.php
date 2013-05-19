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

function ip_whitelist__tpl__get_html (&$ctx) {
    ob_start();
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8" />
        <title>ip_whitelist</title>
        
        <style media="screen">
            /*<![CDATA[*/
                body {
                    font-family: sans-serif;
                    background: white;
                    color: black;
                }
                
                a {
                    color: blue;
                }
                
                .page {
                    margin: auto;
                    max-width: 1000px;
                }
            /*]]>*/
        </style>
        
        <script src="//login.persona.org/include.js" ></script>
        <script>
            //<![CDATA[
                (function (global) {
                    'use strict'
                    
                    var app = global.app = {
                            async_alert: function (text) {
                                setTimeout(function () {
                                    alert(text)
                                }, 0)
                            },
                            
                            async_prompt: function (text, value, callback) {
                                setTimeout(function () {
                                    var result
                                    
                                    try {
                                        result = prompt(text, value)
                                    } catch (e) {
                                        callback(e)
                                        return
                                    }
                                    
                                    callback(null, result)
                                }, 0)
                            },
                            
                            create_elem: function (elem_name, inner_html) {
                                var elem = document.createElement(elem_name)
                                elem.innerHTML = inner_html
                                return elem
                            },
                            
                            create_link_button: function (text) {
                                var link_button = document.createElement('a')
                                link_button.href = '#'
                                link_button.textContent = text
                                return link_button
                            },
                            
                            stub_replace: function (subject_elem, stub_sel, new_elem) {
                                var stub_elem = subject_elem.querySelector(stub_sel)
                                
                                if (!stub_elem) {
                                    return
                                }
                                
                                stub_elem.parentNode.replaceChild(new_elem, stub_elem)
                            },
                            
                            stub_replace_by_text: function (subject_elem, stub_sel, text) {
                                var new_elem = document.createTextNode(text)
                                app.stub_replace(subject_elem, stub_sel, new_elem)
                            },
                            
                            stub_replace_by_multiline_text: function (subject_elem, stub_sel, text) {
                                var text_elem = document.createElement('span')
                                var text_lines = text.split('\n')
                                
                                for (var text_line_i = 0;
                                        text_line_i < text_lines.length; ++text_line_i) {
                                    var text_line = text_lines[text_line_i]
                                    
                                    text_elem.appendChild(document.createTextNode(text_line))
                                    text_elem.appendChild(document.createElement('br'))
                                }
                                
                                app.stub_replace(subject_elem, stub_sel, text_elem)
                            },
                            
                            ajax: function (path, data, callback) {
                                var json_data
                                
                                try {
                                    json_data = JSON.stringify(data)
                                } catch (e) {
                                    callback(e)
                                    return
                                }
                                
                                var form_data = new FormData()
                                
                                form_data.append('data', json_data)
                                
                                var req = new XMLHttpRequest()
                                
                                req.addEventListener('error', function (evt) {
                                    callback(true)
                                })
                                
                                req.addEventListener('load', function (evt) {
                                    if (req.status && req.status != 200) {
                                        callback(true)
                                        return
                                    }
                                    
                                    var result_data
                                    
                                    try {
                                        result_data = JSON.parse(req.responseText)
                                    } catch (e) {
                                        callback(e)
                                        return
                                    }
                                    
                                    callback(null, result_data)
                                })
                                
                                req.open('post', path)
                                req.setRequestHeader('X-Requested-With', 'XMLHttpRequest')
                                req.send(form_data)
                            },
                            
                            do_action: function (action_name) {
                                navigator.id.get(function (assertion) {
                                    if (!assertion) {
                                        return
                                    }
                                    
                                    var data = {
                                            assertion: assertion,
                                            action_name: action_name,
                                            }
                                    
                                    app.ajax('', data, function (err, result_data) {
                                        if (err) {
                                            app.async_alert('Unexpected error!')
                                            return
                                        }
                                        
                                        if (result_data.error) {
                                            if (result_data.error == 'AuthFail') {
                                                app.async_alert('Authorization fail!')
                                                return
                                            }
                                            app.async_alert('Error while action!')
                                            return
                                        }
                                        
                                        app.async_alert('Action successful! (' + action_name + ')')
                                    })
                                })
                            },
                            
                            main: function () {
                                var page_elem = document.querySelector('.page')
                                
                                if (!page_elem) {
                                    return
                                }
                                
                                page_elem.innerHTML = '\<h1\>Ip_whitelist\</h1\>' +
                                        '\<hr /\>' +
                                        '\<h2\>Actions:\</h2\>'
                                
                                var open_button = app.create_link_button('Open Access From My IP')
                                var close_button = app.create_link_button('Close Access From My IP')
                                
                                open_button.addEventListener('click', function (evt) {
                                    evt.preventDefault()
                                    
                                    app.do_action('open')
                                })
                                
                                close_button.addEventListener('click', function (evt) {
                                    evt.preventDefault()
                                    
                                    app.do_action('close')
                                })
                                
                                page_elem.appendChild(open_button)
                                page_elem.appendChild(document.createElement('br'))
                                page_elem.appendChild(close_button)
                            },
                            }
                    
                    document.addEventListener('DOMContentLoaded', function (evt) {
                        app.main()
                    })
                })(this)
            //]]>
        </script>
    </head>
    <body>
        <div class="page">...</div>
    </body>
</html>
<?
    return ob_get_clean();
}
