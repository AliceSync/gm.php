<?php

# @link https://github.com/AliceSync/gm.php

namespace {
    date_default_timezone_set('PRC');

    /** nginx log config */
    \sys\config::set('log_dir', 'youlogdir');

    /** vps panel config */
    \sys\config::set('vps_url', 'youvpsurl');
    \sys\config::set('vps_key', 'youvpskey');
    \sys\config::set('vps_hash', 'youvpshash');
    \sys\config::set('vps_type', 0); # 0 kiwivm 1 solusvm

    /** sys config */
    \sys\config::set('web_name', '作死联萌单文件管理系统');
    \sys\config::set('login_pwd', 'youpassword');
    define('SYS_RUN_TYPE', 'fpm'); # fpm swoole cli(implementd wait...)

    /** sys init */
    \sys\status::init();
    \sys\db::init('/dev/shm/db.db'); # file in "/dev/shm/" you reboot you system this file remove # sys_get_temp_dir() . 'db.db'
    \sys\auth::init($_SERVER["HTTP_CF_CONNECTING_IP"]); # user ip header if you use cloudflare is "HTTP_CF_CONNECTING_IP", no cdn is "REMOTE_ADDR"
    \sys\data::init($_POST, $_GET); # if you use swoole "Swoole\Http\Request->post/get"
    \sys\route::init($_GET['click'] ?? null); # if you use swoole "Swoole\Http\Request->get['click']"

    // if (SYS_RUN_TYPE == 'swoole') {
    //     \sys\send::init('you Swoole\Http\Response variable');
    // }
}

namespace sys {
    class event
    {
        public static function __callStatic($name, $arguments)
        {
            \sys\html\display('_404');
        }
        public static function _shell_push()
        {
            \sys\send::send(data::msg(0, shell_exec(data::post('shell_code'))));
        }
        public static function _index()
        {
            \sys\html\display('index');
        }
        public static function _systeminfo()
        {
            \sys\html\display('systeminfo');
        }
        public static function _systeminfo_api()
        {
            $_display = [
                'mem' => \zslm\system\system_info::mem_info()
            ];
            \sys\send::send(data::msg(0, array_merge($_display, \zslm\system\system_info::cpu_net_api())));
        }
        public static function _vpsinfo()
        {
            \sys\html\display('vpsinfo');
        }
        public static function _log()
        {
            \sys\html\display('log');
        }
        public static function _log_display()
        {
            \sys\html\display('log_display');
        }
        public static function _log_info()
        {
            \sys\html\display('log_info');
        }
        public static function _log_del()
        {
            if (!empty(data::get('file')) and is_file(\sys\config::get('log_dir') . data::get('file'))) {
                unlink(\sys\config::get('log_dir') . data::get('file'));
            }
            \sys\html\display('log');
        }
        public static function _out()
        {
            \sys\status::destroy();
            \sys\html\display('out');
        }
        public static function _login()
        {
            \sys\html\display('login');
        }
        public static function _login_auth()
        {
            if (!auth::ip_qps()) {
                \sys\send::send(data::msg(0, '验证错误次数超过qbs限制阈值,已封锁2小时!'));
            }
            if (!data::isset(data::post('password')) || md5(data::post('password')) !== md5(\sys\config::get('login_pwd'))) {
                \sys\send::send(data::msg(0, '密码错误'));
            }
            auth::ip_qps_del();
            \sys\status::set_login_success();
            \sys\send::send(data::msg(1, 'ok'));
        }
    }
}

# func
namespace sys {
    function ip_in_range($ip, $range)
    {
        if (strpos($range, '/') == false) {
            $range .= '/32';
        }
        list($range, $netmask) = explode('/', $range, 2);
        $range_decimal = ip2long($range);
        $ip_decimal = ip2long($ip);
        $wildcard_decimal = pow(2, (32 - $netmask)) - 1;
        $netmask_decimal = ~$wildcard_decimal;
        return (($ip_decimal & $netmask_decimal) == ($range_decimal & $netmask_decimal));
    }
    function get_disk_total($filesize)
    {
        if ($filesize >= 1099511627776) {
            //转成TB
            $filesize = round($filesize / 1099511627776 * 100) / 100 . ' TB';
        } elseif ($filesize >= 1073741824) {
            //转成GB
            $filesize = round($filesize / 1073741824 * 100) / 100 . ' GB';
        } elseif ($filesize >= 1048576) {
            //转成MB
            $filesize = round($filesize / 1048576 * 100) / 100 . ' MB';
        } elseif ($filesize >= 1024) {
            //转成KB
            $filesize = round($filesize / 1024 * 100) / 100 . ' KB';
        } else {
            //不转换直接输出
            $filesize = $filesize . ' B';
        }
        return $filesize;
    }
    function disk_use_space($dir)
    {
        $sizeResult = 0;
        $handle = opendir($dir);
        while (false !== ($FolderOrFile = readdir($handle))) {
            if ($FolderOrFile != "." && $FolderOrFile != "..") {
                if (is_dir("$dir/$FolderOrFile")) {
                    $sizeResult += disk_use_space("$dir/$FolderOrFile");
                } else {
                    $sizeResult += filesize("$dir/$FolderOrFile");
                }
            }
        }
        closedir($handle);
        return $sizeResult;
    }
}

namespace sys {
    class route
    {
        private static $login = ['login', 'login_auth'];
        public static function init($event = 'index')
        {
            !isset($event) && $event = 'index';
            if (!\sys\status::is_login()) {
                if (!in_array($event, self::$login, true)) {
                    $event = 'login';
                }
            }
            $event = '_' . $event;
            event::$event();
        }
    }
}

namespace sys {
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
    class config
    {
        private static $table;
        public static function get($key)
        {
            return self::$table[$key] ?? null;
        }
        public static function set($key, $val)
        {
            self::$table[$key] = $val;
        }
    }
    class auth
    {
        private static $require_ip;
        public static function init($require_ip)
        {
            self::$require_ip = $require_ip;
        }
        public static function ip_qps($max_num = 10)
        {
            $require_ip = self::$require_ip;
            $times     = time();
            $arr       = db::find('ip', "ip = '$require_ip'");
            db::delete('ip', "$times - mkt > 3600");
            if (empty($arr)) {
                db::insert('ip', 'ip,num,mkt', "'$require_ip',1,$times");
                return true;
            }
            if ($arr['num'] > $max_num and $arr['ext'] < ($times + 3600)) {
                return false;
            }
            if ($arr['num'] == $max_num) {
                $times += 3600;
                db::update('ip', "ext = $times,num = num + 1", "ip = '$require_ip'");
                return false;
            }
            db::update('ip', "num = num + 1", "ip = '$require_ip'");
            return true;
        }
        public static function ip_qps_del()
        {
            $require_ip = self::$require_ip;
            db::delete('ip', "ip = '$require_ip'");
        }
    }
    class db
    {
        private static $db;
        public static function init($db_file)
        {
            try {
                self::$db = new \SQLite3($db_file);
                self::$db->enableExceptions(true);
            } catch (\Throwable $th) {
                \sys\send::send('db connect error!');
            }
            self::$db->exec('CREATE TABLE IF NOT EXISTS ip (
                ip TEXT PRIMARY KEY  NOT NULL,
                num             INT  NOT NULL,
                mkt             INT  NOT NULL,
                ext             INT
             )');
        }
        public static function insert($table, $names, $values)
        {
            self::$db->exec("INSERT INTO $table ($names) VALUES ($values)");
        }
        public static function update($table, $set, $where = '1')
        {
            self::$db->exec("UPDATE $table SET $set WHERE $where");
        }
        public static function select($table, $where = '1', $names = '*')
        {
            return self::$db->query("SELECT $names FROM $table WHERE $where");
        }
        public static function find($table, $where = '1', $names = '*')
        {
            return self::$db->querySingle("SELECT $names FROM $table WHERE $where", true);
        }
        public static function delete($table, $where = '1')
        {
            return self::$db->query("DELETE FROM $table WHERE $where;");
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
    class send
    {
        private static $swoole_Response;
        public static function init($swoole_Response)
        {
            self::$swoole_Response = $swoole_Response;
        }
        public static function send($data)
        {
            if (SYS_RUN_TYPE == 'swoole') {
                self::$swoole_Response->end($data);
            } else {
                exit($data);
            }
        }
    }
}

namespace sys\shell {
    function shell_exec()
    {
    }
}

namespace sys\html {

    class common
    {
        public static function header($title)
        {
            # https://layui.gitee.io/
            # https://www.staticfile.org/
            # https://www.jsdelivr.com/
            # https://cdnjs.com/
            # https://unpkg.com/
            $web_name = \sys\config::get('web_name');
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
            <script>
                    layui.use('element', function () {
                        window.element = layui.element;
                    });
                </script>
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
}

namespace sys\html {
    function display($event)
    {
        \sys\send::send(common::header($event)  . body::$event() . common::footer());
    }
    class body
    {
        public static function header($object_name)
        {
            $header = <<<html
            <ul class="layui-nav">
                <li class="layui-nav-item"><a href="?click=index">首页</a></li> #  
                <li class="layui-nav-item"><a href="?click=systeminfo">系统信息</a></li>
                <li class="layui-nav-item"><a href="?click=vpsinfo">vps信息</a></li>
                <li class="layui-nav-item"><a href="?click=log">日志查询</a></li>
                <li class="layui-nav-item">
                    <a href="javascript:;">系统</a>
                    <dl class="layui-nav-child">
                        <dd><a href="?click=out">退出</a></dd>
                    </dl>
                </li>
            </ul>
            html;
            return str_replace('"><a href="?click=' . $object_name, ' layui-this"><a href="?click=' . $object_name, $header);
        }
        public static function vpsinfo()
        {
            $vm   = new \zslm\vps\api(\sys\config::get('vps_url'), \sys\config::get('vps_key'), \sys\config::get('vps_hash'), \sys\config::get('vps_type'));
            $info = $vm->info();
            $disk_fee = \sys\get_disk_total($info['disk_max'] - $info['disk_use']);
            $ram_fee  = \sys\get_disk_total($info['ram_max'] - $info['ram_use']);
            $net_fee  = \sys\get_disk_total($info['net_max'] - $info['net_use']);
            $disk_progress = ceil($info['disk_use'] / $info['disk_max'] * 100);
            $ram_progress  = ceil($info['ram_use'] / $info['ram_max'] * 100);
            $net_progress  = ceil($info['net_use'] / $info['net_max'] * 100);
            $info['disk_max'] = \sys\get_disk_total($info['disk_max']);
            $info['disk_use'] = \sys\get_disk_total($info['disk_use']);
            $info['ram_max']  = \sys\get_disk_total($info['ram_max']);
            $info['ram_use']  = \sys\get_disk_total($info['ram_use']);
            $info['net_max']  = \sys\get_disk_total($info['net_max']);
            $info['net_use']  = \sys\get_disk_total($info['net_use']);
            return self::header(__FUNCTION__) . <<<html
            <div class="layui-container">
                <fieldset>
                    <legend>>>>>>>>>>>>>>>>>>></legend>
                    <div class="layui-card">
                        <div class="layui-card-header layui-font-20 layui-font-green">vps信息</div>
                        <div class="layui-card-body layui-container">
                            <blockquote class="layui-elem-quote">vps信息</blockquote>
                            <table class="layui-table">
                                <colgroup>
                                    <col>
                                    <col>
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>名称</th>
                                        <th>数值</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>接口类型</td>
                                        <td>{$info['api_type']}</td>
                                    </tr>
                                    <tr>
                                        <td>架构</td>
                                        <td>{$info['type']}</td>
                                    </tr>
                                    <tr>
                                        <td>ip</td>
                                        <td>{$info['ip']}</td>
                                    </tr>
                                    <tr>
                                        <td>系统</td>
                                        <td>{$info['os']}</td>
                                    </tr>
                                    <tr>
                                        <td>hostname</td>
                                        <td>{$info['hostname']}</td>
                                    </tr>
                                    <tr>
                                        <td>物理ip</td>
                                        <td>{$info['node_ip']}</td>
                                    </tr>
                                    <tr>
                                        <td>物理位置</td>
                                        <td>{$info['location']}</td>
                                    </tr>
                                    <tr>
                                        <td>流量信息</td>
                                        <td><p>已使用:{$info['net_use']}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;剩余:$net_fee&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;总:{$info['net_max']}</p><div class="layui-progress layui-progress-big" lay-showPercent="true"><div class="layui-progress-bar layui-bg-blue" lay-percent="$net_progress%"></div></div></td>
                                    </tr>
                                    <tr>
                                        <td>内存信息</td>
                                        <td><p>已使用:{$info['ram_use']}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;剩余:$ram_fee&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;总:{$info['ram_max']}</p><div class="layui-progress layui-progress-big" lay-showPercent="true"><div class="layui-progress-bar layui-bg-blue" lay-percent="$ram_progress%"></div></div></td>
                                    </tr>
                                    <tr>
                                        <td>硬盘信息</td>
                                        <td><p>已使用:{$info['disk_use']}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;剩余:$disk_fee&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;总:{$info['disk_max']}</p><div class="layui-progress layui-progress-big" lay-showPercent="true"><div class="layui-progress-bar layui-bg-blue" lay-percent="$disk_progress%"></div></div></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </fieldset>
            </div>
            html;
        }
        public static function log()
        {
            $arr = scandir(\sys\config::get('log_dir'));
            unset($arr[0], $arr[1]);
            $dislpay = '';
            foreach ($arr as $key => $value) {
                $dislpay .= "<tr>
                <td>$value</td>
                <td>
                    <a href='?click=log_display&file=$value' class='layui-btn layui-btn-normal layui-btn-sm'>查看</a>
                    <a href='?click=log_info&file=$value' class='layui-btn layui-btn-normal layui-btn-sm'>格式化查看</a>
                    <a href='?click=log_del&file=$value' class='layui-btn layui-btn-normal layui-btn-sm'>删除</a>
                </td>
            </tr>";
            }

            $disk_num_total  = disk_total_space('/');
            $disk_num_free   = disk_free_space(\sys\config::get('log_dir'));
            $disk_num_use    = $disk_num_total - $disk_num_free;
            $disk_s_num_use  = \sys\disk_use_space(\sys\config::get('log_dir'));
            $disk_txt_total  = \sys\get_disk_total($disk_num_total);
            $disk_txt_free   = \sys\get_disk_total($disk_num_free);
            $disk_txt_use   = \sys\get_disk_total($disk_num_use);
            $disk_s_txt_use  = \sys\get_disk_total($disk_s_num_use);
            $disk_progress   = ceil($disk_num_use / $disk_num_total * 100);
            $disk_s_progress = ceil($disk_s_num_use / $disk_num_total * 100);

            return self::header(__FUNCTION__) . <<<html
            <div class="layui-container">
                <fieldset>
                    <legend>>>>>>>>>>>>>>>>>>></legend>
                    <div class="layui-card">
                        <div class="layui-card-header layui-font-20 layui-font-green">列表模式</div>
                        <div class="layui-card-body">
                            <table class="layui-table">
                                <colgroup>
                                    <col>
                                    <col>
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>名称</th>
                                        <th>数值</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>占用总空间的比例</td>
                                        <td><p>已使用:$disk_s_txt_use&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;剩余:$disk_txt_free&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;总:$disk_txt_total</p><div class="layui-progress layui-progress-big" lay-showPercent="true"><div class="layui-progress-bar layui-bg-blue" lay-percent="$disk_s_progress%"></div></div></td>
                                    </tr>
                                    <tr>
                                        <td>总空间剩余</td>
                                        <td><p>已使用:$disk_txt_use&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;剩余:$disk_txt_free&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;总:$disk_txt_total</p><div class="layui-progress layui-progress-big" lay-showPercent="true"><div class="layui-progress-bar layui-bg-blue" lay-percent="$disk_progress%"></div></div></td>
                                    </tr>
                                </tbody>
                            </table>
                            <table class="layui-table">
                                <colgroup>
                                    <col>
                                    <col>
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>文件名</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    $dislpay
                                </tbody>
                            </table>
                        </div>
                    </div>
                </fieldset>
            </div>
            html;
        }
        public static function log_display()
        {
            $file_info = '文件不存在或无权限';
            if (is_file(\sys\config::get('log_dir') . \sys\data::get('file'))) {
                $file_info = file_get_contents(\sys\config::get('log_dir') . \sys\data::get('file')); # 注意 ../../xxx这样的攻击行为没有防范 理论上登录的都是安全的 因为可以执行shell 所以没有防范意义
            }
            $filename = \sys\data::get('file');
            return self::header('log') . <<<html
            <div class="layui-container">
                <fieldset>
                    <legend>>>>>>>>>>>>>>>>>>></legend>
                    <div class="layui-card">
                        <div class="layui-card-header layui-font-20 layui-font-green">详情模式</div>
                        <div class="layui-card-body">
                            <blockquote class="layui-elem-quote">当前文件:  $filename</blockquote>
                            <form class="layui-form layui-form-pane" action="">
                                <div class="layui-form-item layui-form-text">
                                    <label class="layui-form-label">详情模式</label>
                                    <div class="layui-input-block">
                                        <textarea disabled placeholder="详情模式" class="layui-textarea" rows="30">$file_info</textarea>
                                    </div>
                                </div>
                                <a href='?click=log' class='layui-btn layui-btn-normal'>返回列表</a>
                            </form>
                        </div>
                    </div>
                </fieldset>
            </div>
            html;
        }
        public static function log_info()
        {
            $cf_list = '173.245.48.0/20
            103.21.244.0/22
            103.22.200.0/22
            103.31.4.0/22
            141.101.64.0/18
            108.162.192.0/18
            190.93.240.0/20
            188.114.96.0/20
            197.234.240.0/22
            198.41.128.0/17
            162.158.0.0/15
            104.16.0.0/13
            104.24.0.0/14
            172.64.0.0/13
            131.0.72.0/22';
            $cfip_truelist = explode("\n", $cf_list);
            $file_info = "[\ntime:文件不存在或无权限\nfrom:文件不存在或无权限\ncf:文件不存在或无权限\nua:文件不存在或无权限\nurl:文件不存在或无权限\nfromurl:文件不存在或无权限\n]\n";
            if (is_file(\sys\config::get('log_dir') . \sys\data::get('file'))) {
                $file_info = file_get_contents(\sys\config::get('log_dir') . \sys\data::get('file')); # 注意 ../../xxx这样的攻击行为没有防范 理论上登录的都是安全的 因为可以执行shell 所以没有防范意义
            }
            $filename = \sys\data::get('file');
            $tmptable = [];
            $fileinfo = $file_info;
            $info_arr = explode(']', $fileinfo);
            unset($info_arr[count($info_arr) - 1]);
            foreach ($info_arr as $value) {
                $info = explode("\n", $value);
                if (count($info) == 8) {
                    unset($info[7], $info[0]);
                } else {
                    unset($info[8], $info[0], $info[1]);
                }
                array_unshift($info);
                $tmptable[] = [
                    'time' => mb_substr(trim($info[0]), 5),
                    'from' => mb_substr(trim($info[1]), 5),
                    'cfip' => mb_substr(trim($info[2]), 3),
                    'ua'   => mb_substr(trim($info[3]), 3),
                    'url'  => mb_substr(trim($info[4]), 4),
                    'fromurl' => mb_substr(trim($info[5]), 8)
                ];
            }
            $cf_list = [];
            $cf_ua   = [];
            foreach ($tmptable as $value) {
                if (!isset($cf_ua[$value['ua']])) {
                    $cf_ua[$value['ua']] = 1;
                } else {
                    $cf_ua[$value['ua']] += 1;
                }
                $ip = $value['cfip'] != '-' ? $value['cfip'] : $value['from'];
                $cfip = $value['cfip'] == '-' ? '-' : $value['from'];
                if (isset($cf_list[$ip])) {
                    $cf_list[$ip]['num'] += 1;
                    if (isset($cf_list[$ip]['ua'][$value['ua']])) {
                        $cf_list[$ip]['ua'][$value['ua']] += 1;
                    } else {
                        $cf_list[$ip]['ua'][$value['ua']] = 1;
                    }
                    if (isset($cf_list[$ip]['cfip'][$cfip])) {
                        $cf_list[$ip]['cfip'][$cfip] += 1;
                    } else {
                        $cf_list[$ip]['cfip'][$cfip] = 1;
                    }
                    $cf_list[$ip]['end_time'] = $value['time'];
                    if ($cfip == '-') {
                        $cf_list[$ip]['cont'] += 1;
                    } else {
                        $cf_list[$ip]['cnum'] += 1;
                    }
                    $cf_list[$ip]['cf'] = $cfip == '-' ? false : true;
                } else {
                    $cf_list[$ip] = [
                        'cf'   => $cfip == '-' ? false : true,
                        'num'  => 1,
                        'cnum' => 0,
                        'cont' => 0,
                        'cfip' => [
                            $cfip => 1
                        ],
                        'ua' => [
                            $value['ua'] => 1
                        ],
                        'start_time' => $value['time'],
                        'end_time' => $value['time']
                    ];
                    if ($cfip == '-') {
                        $cf_list[$ip]['cont'] = 1;
                    } else {
                        $cf_list[$ip]['cnum'] = 1;
                    }
                }
            }

            $ip_display = '';
            $zhi_display = '';
            $cf_display = '';
            foreach ($cf_list as $ip => $arr) {
                $t_ua_d = '<table class="layui-table"><colgroup><col><col></colgroup><thead><tr><th>ua</th><th>次数</th></tr></thead><tbody>';
                foreach ($arr['ua'] as $t_u => $t_m) {
                    $t_ua_d .= "<tr><td>$t_u</td><td>$t_m</td>";
                }
                $t_ua_d = $t_ua_d . '</tbody></table>';

                $t_cf = '<table class="layui-table"><colgroup><col><col></colgroup><thead><tr><th>ip</th><th>次数</th></tr></thead><tbody>';
                foreach ($arr['cfip'] as $t_ip => $t_m) {
                    $bool = false;
                    foreach ($cfip_truelist as $true_ips) {
                        if ($bool == true) {
                            break;
                        }
                        $bool = \sys\ip_in_range($t_ip, trim($true_ips));
                    }
                    $bool = $bool ? '真' : '假';
                    $t_cf .= "<tr><td>$t_ip($bool)</td><td>$t_m</td>";
                }
                $t_cf = $t_cf . '</tbody></table>';

                $ip_display .= "<tr><td>$ip</td><td>{$arr['num']}</td><td>{$arr['start_time']}</td><td>{$arr['end_time']}</td><td>{$t_ua_d}</td><td>{$t_cf}</td></tr>";
                if ($arr['cf']) {
                    $cf_display .= "<tr><td>$ip</td><td>{$arr['cnum']}</td><td>{$arr['start_time']}</td><td>{$arr['end_time']}</td><td>{$t_ua_d}</td><td>{$t_cf}</td></tr>";
                }
                if ($cf_list[$ip]['cont'] >= 1) {
                    $zhi_display .= "<tr><td>$ip</td><td>{$arr['cont']}</td><td>{$arr['start_time']}</td><td>{$arr['end_time']}</td><td>{$t_ua_d}</td></tr>";
                }
            }
            $ua_display = '';
            foreach ($cf_ua as $ua => $num) {
                $ua_display .= "<tr><td>$ua</td><td>$num</td></tr>";
            }
            return self::header('log') . <<<html
            <div class="layui-container">
                <fieldset>
                    <legend>>>>>>>>>>>>>>>>>>></legend>
                    <div class="layui-card">
                        <div class="layui-card-header layui-font-20 layui-font-green">格式化模式 <a href='?click=log' class='layui-btn layui-btn-normal layui-btn-sm'>返回列表</a></div>
                        <div class="layui-card-body">
                            <blockquote class="layui-elem-quote">当前文件:  $filename</blockquote>
                            <div class="layui-tab layui-tab-card">
                                <ul class="layui-tab-title">
                                    <li class="layui-this">所有列表显示</li>
                                    <li>直连计算模式</li>
                                    <li>cloudflare计算模式</li>
                                    <li>ua统计模式</li>
                                </ul>
                                <div class="layui-tab-content">
                                    <div class="layui-tab-item layui-show">
                                        <blockquote class="layui-elem-quote">所有列表显示</blockquote>
                                        <table class="layui-table">
                                            <colgroup>
                                                <col>
                                                <col>
                                                <col>
                                                <col>
                                                <col>
                                                <col>
                                            </colgroup>
                                            <thead>
                                                <tr>
                                                    <th>ip</th>
                                                    <th>次数</th>
                                                    <th>开始时间</th>
                                                    <th>最后时间</th>
                                                    <th>ua列表</th>
                                                    <th>曾用cfip</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                $ip_display
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="layui-tab-item">
                                        <blockquote class="layui-elem-quote">直连计算模式</blockquote>
                                        <table class="layui-table">
                                            <colgroup>
                                                <col>
                                                <col>
                                                <col>
                                                <col>
                                                <col>
                                            </colgroup>
                                            <thead>
                                                <tr>
                                                    <th>ip</th>
                                                    <th>次数</th>
                                                    <th>开始时间</th>
                                                    <th>最后时间</th>
                                                    <th>ua列表</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                $zhi_display
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="layui-tab-item">
                                        <blockquote class="layui-elem-quote">cloudflare计算模式</blockquote>
                                        <table class="layui-table">
                                            <colgroup>
                                                <col>
                                                <col>
                                                <col>
                                                <col>
                                                <col>
                                                <col>
                                            </colgroup>
                                            <thead>
                                                <tr>
                                                    <th>ip</th>
                                                    <th>次数</th>
                                                    <th>开始时间</th>
                                                    <th>最后时间</th>
                                                    <th>ua列表</th>
                                                    <th>曾用cfip(真/假为IP段验证结果)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                $cf_display
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="layui-tab-item">
                                        <blockquote class="layui-elem-quote">ua统计模式</blockquote>
                                        <table class="layui-table">
                                            <colgroup>
                                            <col>
                                            <col>
                                            </colgroup>
                                            <thead>
                                                <tr>
                                                    <th>ua</th>
                                                    <th>次数</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                $ua_display
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </fieldset>
            </div>
            html;
        }
        public static function systeminfo()
        {
            /** php */
            $system_ip      = $_SERVER['SERVER_ADDR'];
            $system_os      = PHP_OS . ' ' . php_uname('r') . ' ' . php_uname('v');
            $system_core    = php_uname('m');
            $system_name    = php_uname('n');
            $ext_info       = implode(',', get_loaded_extensions());
            $disk_num_total = disk_total_space('/');
            $disk_num_free  = disk_free_space('/');
            $disk_num_use   = $disk_num_total - $disk_num_free;
            $disk_txt_total = \sys\get_disk_total($disk_num_total);
            $disk_txt_free  = \sys\get_disk_total($disk_num_free);
            $disk_txt_use   = \sys\get_disk_total($disk_num_use);
            $disk_progress  = ceil($disk_num_use / $disk_num_total * 100);
            /** sys */
            $start_info = ceil(\zslm\system\system_info::starttime_info() / 3600);
            $sys_info   = \zslm\system\system_info::sys_info();
            $cpu_info   = \zslm\system\system_info::cpu_info();
            $mem_info   = \zslm\system\system_info::mem_info(true);
            $disk_info  = \zslm\system\system_info::disk_info();
            $disk_info_txt = '';
            foreach ($disk_info as $key => $value) {
                $disk_info_txt .= "<tr><td>硬盘 - {$key}</td><td><p>名称:{$value['name']}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;位置:{$value['dir']}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;已用:{$value['used']}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;可用:{$value['free']}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;总:{$value['size']}</p><div class='layui-progress layui-progress-big' lay-showPercent='true'><div class='layui-progress-bar layui-bg-blue' lay-percent='{$value['perc']}%'></div></div></td></tr>";
            }
            return self::header(__FUNCTION__) . <<<html
            <div class="layui-container">
                <fieldset>
                    <legend>>>>>>>>>>>>>>>>>>></legend>
                    <div class="layui-card">
                        <div class="layui-card-header layui-font-20 layui-font-green">系统信息</div>
                        <div class="layui-card-body layui-container">
                            <div class="layui-tab layui-tab-card">
                                <ul class="layui-tab-title">
                                    <li class="layui-this">PHP系统信息</li>
                                    <li>系统信息</li>
                                    <li>实时信息</li>
                                </ul>
                                <div class="layui-tab-content">
                                    <div class="layui-tab-item layui-show">
                                        <blockquote class="layui-elem-quote">PHP系统信息</blockquote>
                                        <table class="layui-table">
                                            <colgroup>
                                                <col>
                                                <col>
                                            </colgroup>
                                            <thead>
                                                <tr>
                                                    <th>名称</th>
                                                    <th>数值</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>服务器ip</td>
                                                    <td>$system_ip</td>
                                                </tr>
                                                <tr>
                                                    <td>操作系统</td>
                                                    <td>$system_os</td>
                                                </tr>
                                                <tr>
                                                    <td>架构</td>
                                                    <td>$system_core</td>
                                                </tr>
                                                <tr>
                                                    <td>主机名</td>
                                                    <td>$system_name</td>
                                                </tr>
                                                <tr>
                                                    <td>磁盘图形</td>
                                                    <td><p>已用:$disk_txt_use&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;可用:$disk_txt_free&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;总:$disk_txt_total</p><div class="layui-progress layui-progress-big" lay-showPercent="true"><div class="layui-progress-bar layui-bg-blue" lay-percent="$disk_progress%"></div></div></td>
                                                </tr>
                                                <tr>
                                                    <td>扩展</td>
                                                    <td><textarea disabled placeholder="扩展" class="layui-textarea" rows="3">$ext_info</textarea></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="layui-tab-item">
                                        <blockquote class="layui-elem-quote">系统信息</blockquote>
                                        <table class="layui-table">
                                            <colgroup>
                                                <col>
                                                <col>
                                            </colgroup>
                                            <thead>
                                                <tr>
                                                    <th>名称</th>
                                                    <th>数值</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <tr>
                                                <tr>
                                                    <td>开机时长</td>
                                                    <td>$start_info 小时</td>
                                                </tr>
                                                <td>服务器ip</td>
                                                    <td>{$sys_info['ip']}</td>
                                                </tr>
                                                <tr>
                                                    <td>操作系统</td>
                                                    <td>{$sys_info['os']}</td>
                                                </tr>
                                                <tr>
                                                    <td>架构</td>
                                                    <td>{$sys_info['core']}</td>
                                                </tr>
                                                <tr>
                                                    <td>主机名</td>
                                                    <td>{$sys_info['name']}</td>
                                                </tr>
                                                <tr>
                                                    <td>开机时长</td>
                                                    <td>$start_info 小时</td>
                                                </tr>
                                                <tr>
                                                    <td>cpu 型号</td>
                                                    <td>{$cpu_info['cpu_name']}</td>
                                                </tr>
                                                <tr>
                                                    <td>cpu 主频</td>
                                                    <td>{$cpu_info['cpu_mhz']} Mhz</td>
                                                </tr>
                                                <tr>
                                                    <td>cpu 缓存大小</td>
                                                    <td>{$cpu_info['cpu_cache']} M</td>
                                                </tr>
                                                <tr>
                                                    <td>cpu 核心数</td>
                                                    <td>{$cpu_info['cpu_cores']}</td>
                                                </tr>
                                                <tr>
                                                    <td>内存图形</td>
                                                    <td><p>已用:{$mem_info['mem_use']}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;缓存:{$mem_info['mem_cache']}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;可用:{$mem_info['mem_free']}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;总:{$mem_info['mem_total']}</p><div class="layui-progress layui-progress-big" lay-showPercent="true"><div class="layui-progress-bar layui-bg-blue" lay-percent="{$mem_info['mem_perc']}%"></div></div></td>
                                                </tr>
                                                $disk_info_txt
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="layui-tab-item">
                                        <blockquote class="layui-elem-quote">实时信息</blockquote>
                                        <table class="layui-table">
                                            <colgroup>
                                                <col>
                                                <col>
                                            </colgroup>
                                            <thead>
                                                <tr>
                                                    <th>名称</th>
                                                    <th>数值</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>实时状态开关</td>
                                                    <td>
                                                        <p class="status_type">未运行</p>
                                                        <button class="layui-btn layui-btn-normal layui-btn-sm status_on">开启</button>
                                                        <button class="layui-btn layui-btn-normal layui-btn-sm status_off">停止</button>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>cpu图形</td>
                                                    <td><div class="layui-progress layui-progress-big" lay-showPercent="true" lay-filter="cpu_perc"><div class="layui-progress-bar layui-bg-blue" lay-percent="0%"></div></div></td>
                                                </tr>
                                                <tr>
                                                    <td>内存图形</td>
                                                    <td><p>已用:<b class="status_mem_use">{$mem_info['mem_use']}</b>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;缓存:<b class="status_mem_cache">{$mem_info['mem_cache']}</b>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;可用:<b class="status_mem_free">{$mem_info['mem_free']}</b>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;总:<b class="status_mem_total">{$mem_info['mem_total']}</b></p><div class="layui-progress layui-progress-big" lay-showPercent="true" lay-filter="mem_perc"><div class="layui-progress-bar layui-bg-blue" lay-percent="{$mem_info['mem_perc']}%"></div></div></td>
                                                </tr>
                                                <tr>
                                                    <td>实时网络</td>
                                                    <td class="status_network">↑ 0kb/s 丨 ↓ 0kb/s</td>
                                                </tr>
                                                <tr>
                                                    <td>实时进程网速</td>
                                                    <td>
                                                        <table class="layui-table">
                                                            <colgroup>
                                                                <col>
                                                                <col>
                                                            </colgroup>
                                                            <thead>
                                                                <tr>
                                                                    <th>进程名</th>
                                                                    <th>速率</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <td>等待api添加</td>
                                                                <td>等待api添加</td>
                                                            </tbody>
                                                        </table>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>进程列表</td>
                                                    <td>
                                                        <table class="layui-table">
                                                            <colgroup>
                                                                <col>
                                                                <col>
                                                            </colgroup>
                                                            <thead>
                                                                <tr>
                                                                    <th>进程名</th>
                                                                    <th>命令</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <td>等待api添加</td>
                                                                <td>等待api添加</td>
                                                            </tbody>
                                                        </table>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </fieldset>
            </div>
            <script>
                                                    
                $('.status_on').click(function () {
                    status_run = true;
                    get_sys_status();
                });
                $('.status_off').click(function () {
                    status_run = false;
                });
                function get_sys_status() {
                    if (status_run == true) {
                        $('.status_type').html('运行中');
                        $.get("?click=systeminfo_api",
                            function (result) {
                                if (result.code == '0') {
                                    window.element.progress('cpu_perc', result.msg.cpu + '%');
                                    window.element.progress('mem_perc', result.msg.mem.mem_perc + '%');
                                    $('.status_mem_total').html((result.msg.mem.mem_total / 1024 / 1024).toFixed(2) + 'MB');
                                    $('.status_mem_free').html((result.msg.mem.mem_free / 1024 / 1024).toFixed(2) + 'MB');
                                    $('.status_mem_cache').html((result.msg.mem.mem_cache / 1024 / 1024).toFixed(2) + 'MB');
                                    $('.status_mem_use').html((result.msg.mem.mem_use / 1024 / 1024).toFixed(2) + 'MB');
                                    $('.status_network').html('↑ ' + result.msg.net.send + 'kb/s 丨 ↓ ' + result.msg.net.recv + 'kb/s');
                                    get_sys_status();
                                }else{
                                    status_run == false;
                                    $('.status_type').html('未运行');
                                }
                            }, "json")
                            .fail(function () {
                                status_run == false;
                                $('.status_type').html('未运行');
                            });
                    }else{
                        $('.status_type').html('未运行');
                    }
                }
                
            </script>
            html;
        }
        public static function index()
        {
            return self::header(__FUNCTION__) . <<<html
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
    }
}

namespace zslm\system {
    class system_info
    {
        public static function starttime_info()
        {
            $data = file_get_contents("/proc/uptime");
            return trim(mb_strcut($data, 0, mb_strpos($data, '.')));
        }
        public static function sys_info()
        {
            $ret = [
                'ip'   => shell_exec('hostname -I'),
                'os'   => file_get_contents('/proc/version'),
                'core' => shell_exec('uname -m'),
                'name' => shell_exec('uname -n')
            ];
            return $ret;
        }
        public static function cpu_info()
        {
            $info = file_to_array("/proc/cpuinfo");
            $ret  = [
                'cpu_name'  => $info['model name'],
                'cpu_mhz'   => $info['cpu MHz'],
                'cpu_cache' => trim(mb_strcut($info['cache size'], 0, mb_strpos($info['cache size'], 'K'))) / 1024,
                'cpu_cores' => $info['cpu cores']
            ];
            return $ret;
        }
        public static function cpu_rate()
        {
            $cpu_1 = self::cpu_rate_api();
            sleep(1);
            $cpu_2 = self::cpu_rate_api();
            return intval(100 * bcdiv($cpu_2[1] - $cpu_1[1], $cpu_2[0] - $cpu_1[0], 3));
        }
        public static function mem_info($is_total = false)
        {
            $info = file_to_array("/proc/meminfo");
            $ret  = [
                'mem_total' => trim(mb_strcut($info['MemTotal'], 0, mb_strpos($info['MemTotal'], 'k'))) * 1024, # 总内存
                'mem_free'  => trim(mb_strcut($info['MemAvailable'], 0, mb_strpos($info['MemAvailable'], 'k'))) * 1024, # 可用内存
                'mem_cache' => (trim(mb_strcut($info['Cached'], 0, mb_strpos($info['Cached'], 'k'))) + trim(mb_strcut($info['Buffers'], 0, mb_strpos($info['Buffers'], 'k')))) * 1024, # 缓存
            ];
            $ret['mem_use']  = $ret['mem_total'] - $ret['mem_free']; # 已用内存
            $ret['mem_perc'] = ceil($ret['mem_use'] / $ret['mem_total'] * 100);
            if ($is_total) {
                foreach ($ret as $key => $value) {
                    if ($key != 'mem_perc') {
                        $ret[$key] = \zslm\get_disk_total($value);
                    }
                }
            }
            return $ret;
        }
        public static function disk_info()
        {
            $disk     = shell_exec('df -hl');
            $disk     = explode("\n", $disk);
            $disk_arr = [];
            foreach ($disk as $disk_list) {
                if (mb_strcut($disk_list, 0, 5) == '/dev/') {
                    $tmp   = [];
                    $tmp_1 = explode(' ', $disk_list);
                    foreach ($tmp_1 as $tmp_1_1) {
                        if ($tmp_1_1 != "") {
                            $tmp[] = $tmp_1_1;
                        }
                    }
                    $disk_arr[] = [
                        'name' => $tmp[0],
                        'size' => $tmp[1],
                        'used' => $tmp[2],
                        'free' => $tmp[3],
                        'perc' => intval($tmp[4]),
                        'dir'  => $tmp[5],
                    ];
                }
            }
            return $disk_arr;
        }
        public static function task_list()
        {
            $ret = [];
            $arr = scandir('/proc/');
            foreach ($arr as $pid) {
                if (is_numeric($pid)) {
                    $info = file_to_array("/proc/$pid/status");
                    $ret[$pid] = [
                        'name'   => $info['Name'],
                        'pid'    => $info['Pid'],
                        'cpunum' => $info['Cpus_allowed'],
                        'cpu'    => 0,
                        'mem'    => 0,
                        'cmd'    => file_get_contents("/proc/$pid/cmdline"),
                    ];
                }
            }
            return $ret;
        }
        public static function network()
        {
            $net_1 = self::network_api();
            sleep(1);
            $net_2 = self::network_api();
            return ['recv' => round(($net_2[0] - $net_1[0]) / 1024, 2), 'send' => round(($net_2[8] - $net_1[8]) / 1024, 2)];
        }
        private static function network_api()
        {
            $func = function () {
                $info = file_get_contents("/proc/net/dev");
                $info = trim(mb_strcut($info, mb_strpos($info, 'eth0: ') + 6));
                $info = trim(mb_strcut($info, 0, mb_strpos($info, "\n")));
                $info = explode(' ', $info);
                $data = [];
                foreach ($info as $value) {
                    if ($value != "") {
                        $data[] = $value;
                    }
                }
                unset($info);
                return $data;
            };
            return $func();
        }
        public static function cpu_net_api()
        {
            $net_1 = self::network_api();
            $cpu_1 = self::cpu_rate_api();
            sleep(1);
            $net_2 = self::network_api();
            $cpu_2 = self::cpu_rate_api();
            return [
                'cpu' => intval(100 * bcdiv($cpu_2[1] - $cpu_1[1], $cpu_2[0] - $cpu_1[0], 3)),
                'net' => ['recv' => round(($net_2[0] - $net_1[0]) / 1024, 2), 'send' => round(($net_2[8] - $net_1[8]) / 1024, 2)]
            ];
        }
        private static function cpu_rate_api()
        {
            $func = function () {
                $info = explode(' ', file_get_contents("/proc/stat"));
                unset($info[0], $info[1]);
                $info = array_values($info);
                return [$info[0] + $info[1] + $info[2] + $info[3] + $info[4] + $info[5] + $info[6] + $info[7], $info[0] + $info[1] + $info[2] + $info[4] + $info[5] + $info[6] + $info[7]];
            };
            return $func();
        }
    }
    function file_to_array($file)
    {
        $string = file_get_contents($file);
        $string = explode("\n", $string);
        $retarr = [];
        foreach ($string as $v) {
            $v = trim($v);
            if (!empty($v)) {
                $retarr[trim(mb_strcut($v, 0, mb_strpos($v, ':')))] = trim(mb_strcut($v, mb_strpos($v, ':') + 1));
            }
        }
        return $retarr;
    }
}

namespace zslm\vps {
    function get_contents($url, $method = 'GET', $header = [
        "user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36 Edg/110.0.1587.50",
    ], $data = '', $time_out = 10)
    {
        return file_get_contents($url, false, stream_context_create([
            'http' =>
            [
                'timeout' => $time_out,
                'method'  => mb_strtoupper($method),
                'header'  => $header,
                'content' => $data, # http_build_query
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => false
            ]
        ]));
    }
    class api
    {
        private $type;
        private $url;
        private $key;
        /** type 0 kiwivm_api 1 solusvm_api */
        public function __construct($url, $key, $hash, $type = 0)
        {
            $this->type = $type;
            if ($type == 0) {
                $this->url = $url;
                $this->key = sprintf('?veid=%s&api_key=%s', $key, $hash);
            } else {
                $this->url = $this->url = sprintf('%s?key=%s&hash=%s&action=', $url, $key, $hash);
            }
        }
        private function get_contents_solusvm($call, $time_out = 10)
        {
            $data = get_contents($this->url . $call, time_out: $time_out);
            preg_match_all('/<(.*?)>([^<]+)<\/\\1>/i', $data, $match);
            $result = array();
            foreach ($match[1] as $x => $y) {
                $result[$y] = $match[2][$x];
            }
            return $result;
        }
        private function get_contents_kiwivm($call, $time_out = 10)
        {
            $data = get_contents($this->url . $call . $this->key, time_out: $time_out);
            $ret  = json_decode($data, true);
            unset($ret['available_isos']);
            return $ret;
        }
        private function get_contents($call, $time_out = 10)
        {
            return $this->type == 0 ? $this->get_contents_kiwivm($call, $time_out) : $this->get_contents_solusvm($call, $time_out);
        }
        public function info()
        {
            $info = $this->get_contents(($this->type == 0 ? 'getServiceInfo' : 'info&ipaddr=true&bw=true&mem=true&hdd=true'), 3);
            if ($this->type == 0) {
                $ret  = [];
                $ret['api_type'] = 'kiwivm'; # kiwivm solusvm
                $ret['type']     = $info['vm_type'] ?? null;
                $ret['ip']       = $info['ip_addresses'][0] ?? null;
                $ret['os']       = $info['os'] ?? null;
                $ret['hostname'] = $info['hostname'] ?? null;
                $ret['node_ip']  = $info['node_ip'] ?? null; // ip地址的物理节点
                $ret['location'] = $info['node_datacenter'] ?? null;
                $ret['disk_max'] = $info['plan_disk'] ?? null; // 磁盘大小
                $ret['ram_max']  = $info['plan_ram'] ?? null;  // 内存大小
                $ret['net_max']  = $info['plan_monthly_data'] ?? null; // 总流量
                $ret['net_use']  = $info['data_counter'] ?? null; // 已使用

                $info = $this->status();
                !isset($info['mem_available_kb']) && $info['mem_available_kb'] = 0;
                $ret['disk_use'] = $info['ve_used_disk_space_b'] ?? 0;
                $ret['ram_use']  = $info['mem_available_kb'] * 1024;
                $ret['status']   = $info['ve_status'] ?? '获取失败';
                return $ret;
            } else {
                $ret  = [];
                $ret['api_type'] = 'solusvm';
                $ret['type']     = 'solusvm模式无法获取';
                $ret['ip']       = $info['ipaddr'] ?? null;
                $ret['os']       = 'solusvm模式无法获取' ?? null;
                $ret['hostname'] = $info['hostname'];
                $ret['node_ip']  = 'solusvm模式无法获取' ?? null;
                $ret['location'] = 'solusvm模式无法获取' ?? null;
                $ret['disk_max'] = (explode(',', $info['hdd']))[0];
                $ret['ram_max']  = (explode(',', $info['mem']))[0];
                $ret['net_max']  = (explode(',', $info['bw']))[0];
                $ret['net_use']  = (explode(',', $info['bw']))[1];
                $ret['disk_use'] = (explode(',', $info['hdd']))[1];
                $ret['ram_use']  = (explode(',', $info['mem']))[1];
                $ret['status']   = $info['status'] ?? null;
                return $ret;
            }
        }
        private function status()
        {
            return $this->get_contents(($this->type == 0 ? 'getLiveServiceInfo' : 'status'), 3);
        }
        public function start()
        {
            $this->get_contents(($this->type == 0 ? 'start' : 'boot'), 3);
            return true;
        }
        public function stop()
        {
            $this->get_contents(($this->type == 0 ? 'stop' : 'shutdown'), 3);
            return true;
        }
        public function restart()
        {
            $this->get_contents(($this->type == 0 ? 'restart' : 'reboot'), 3);
            return true;
        }
        public function kill()
        {
            $this->get_contents(($this->type == 0 ? 'kill' : null), 3);
            return true;
        }
    }
}

namespace zslm {
    function get_disk_total($filesize)
    {
        if ($filesize >= 1099511627776) {
            //转成TB
            $filesize = round($filesize / 1099511627776 * 100) / 100 . ' TB';
        } elseif ($filesize >= 1073741824) {
            //转成GB
            $filesize = round($filesize / 1073741824 * 100) / 100 . ' GB';
        } elseif ($filesize >= 1048576) {
            //转成MB
            $filesize = round($filesize / 1048576 * 100) / 100 . ' MB';
        } elseif ($filesize >= 1024) {
            //转成KB
            $filesize = round($filesize / 1024 * 100) / 100 . ' KB';
        } else {
            //不转换直接输出
            $filesize = $filesize . ' B';
        }
        return $filesize;
    }
}
