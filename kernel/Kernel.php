<?php
namespace Kernel;
use Service\Exception;
use Whoops\Run;
use Whoops\Handler\PrettyPageHandler;
use Itxiao6\Route\Route;
use Service\Http;
use Itxiao6\Route\Resources;
use DebugBar\DebugBar;
use DebugBar\DataCollector\ExceptionsCollector;
use DebugBar\DataCollector\MessagesCollector;
use DebugBar\DataCollector\PhpInfoCollector;
/**
* 框架核心类
*/
class Kernel
{
    protected static $class = [];
    /**
     * 类的映射
     */
    public static function auto_load($class){
        if(count(self::$class)==0){
            # 加载配置文件
            self::$class = Config::get('class');
        }
        # 判断类是否存在
        if(isset(self::$class[$class])){
            # 获取类文件名
            $class_name = str_replace('\\','_',CLASS_PATH.self::$class[$class].'.php');
            # 判断缓存文件是否存在
            if(!file_exists($class_name)){
                # 写入文件
                file_put_contents($class_name,'<?php class '.$class.' extends '.self::$class[$class].'{ }');
            }
            # 引入映射类
            require($class_name);
        }
    }
    /**
     * 加载环境变量
     * */
    public static function load_env()
    {
        # 判断环境变量配置文件是否存在
        if(file_exists(ROOT_PATH.'.env')){
            # 自定义配置
            $env = parse_ini_file(ROOT_PATH . '.env', true);
        }else{
            # 惯例配置
            $env = parse_ini_file(ROOT_PATH . '.env.example', true);
        }
        foreach ($env as $key => $val) {
            $name = strtolower($key);
            if (is_array($val)) {
                foreach ($val as $k => $v) {
                    $item = $name . '_' . strtolower($k);
                    putenv("$item=$v");
                }
            } else {
                putenv("$name=$val");
            }
        }
    }
    /**
     * 启动框架
     */
    public static function start()
    {
        # 加载环境变量
        self::load_env();
        # 关闭文件
        fclose($f);

        # 设置协议头
        header("Content-Type:text/html;charset=utf-8");

        # 判断是否下载了composer包
        if ( file_exists(ROOT_PATH.'vendor'.DIRECTORY_SEPARATOR.'autoload.php') ) {

            # 引用Composer自动加载规则
            require(ROOT_PATH.'vendor'.DIRECTORY_SEPARATOR.'autoload.php');
        }else{

            # 退出程序并提示
            exit('请在项目根目录执行:composer install');
        }
        # 判断是否为调试模式
        if( DE_BUG === TRUE ){
            # 屏蔽所有notice 和 warning 级别的错误
            error_reporting(E_ALL^E_NOTICE^E_WARNING);
            $whoops = new Run;
            # 回调处理
//            $whoops -> pushHandler(new \Whoops\Handler\CallbackHandler(function($ErrorException,$Inspector,$Run){
//                dd(func_get_args());
//            }));
            $PrettyPageHandler =  new PrettyPageHandler();
            # 设置错误页面标题
            $PrettyPageHandler -> setPageTitle('Minkernel-哎呀-出错了');
            # 输入报错的页面
            $whoops -> pushHandler($PrettyPageHandler);
            # 判断是否为ajax
            if (\Whoops\Util\Misc::isAjaxRequest()) {
                $whoops->pushHandler(new \Whoops\Handler\JsonResponseHandler);
            }
            $whoops->register();
            # 禁止所有页面缓存
            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . 'GMT');
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
        }else{
            # 屏蔽所有错误
            error_reporting(0);
        }

        # 设置时区
        date_default_timezone_set(Config::get('sys','default_timezone'));

        # 判断是否开启了debugbar
        if(Config::get('sys','debugbar')) {
            # 定义全局变量
            global $debugbar;
            global $debugbarRenderer;
            global $database;

            # 启动DEBUGBAR
            $debugbar = new DebugBar();
            $debugbar->addCollector(new PhpInfoCollector());
            $debugbar->addCollector(new MessagesCollector('Time'));
            $debugbar->addCollector(new MessagesCollector('Request'));
            $debugbar->addCollector(new MessagesCollector('Session'));
            $debugbar->addCollector(new MessagesCollector('Database'));
            $debugbar->addCollector(new MessagesCollector('Application'));
            $debugbar->addCollector(new MessagesCollector('View'));
            $debugbar->addCollector(new ExceptionsCollector());

            $debugbarRenderer = $debugbar->getJavascriptRenderer();
        }
        # 注册类映射方法
        spl_autoload_register('Kernel\Kernel::auto_load');

        # 定义请求常量
        define('REQUEST_METHOD',Http::REQUEST_METHOD());
        # 是否为GET 请求
        define('IS_GET',Http::IS_GET());
        # 是否为POST 请求
        define('IS_POST',Http::IS_POST());
        # 是否为PUT 请求
        define('IS_PUT',Http::IS_PUT());
        # 是否为SSL(Https) 请求
        define('IS_SSL',Http::IS_SSL());
        # 是否为DELETE 请求
        define('IS_DELETE',Http::IS_DELETE());
        # 是否为WECHAT 请求
        define('IS_WECHAT',Http::IS_WECHAT());
        # 是否为AJAX 请求
        define('IS_AJAX', \Whoops\Util\Misc::isAjaxRequest());
        # 是否为Model 请求
        define('IS_MOBILE',Http::IS_MOBILE());
        # 是否为CCG 请求
        define('IS_CGI',Http::IS_CGI());
        # 是否为SLI 环境
        define('IS_CLI',Http::IS_CLI());
        # 判断缓存主目录是否存在
        if(!is_dir(ROOT_PATH.'runtime'.DIRECTORY_SEPARATOR)){
            # 递归创建目录
            mkdir(ROOT_PATH.'runtime'.DIRECTORY_SEPARATOR,0777,true);
        }
        # 数据缓存目录
        define('CACHE_DATA',ROOT_PATH.'runtime'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR);
        # 检查目录是否存在
        if(!is_dir(CACHE_DATA)){
            # 递归创建目录
            mkdir(CACHE_DATA,0777,true);
        }
        # 类映射缓存目录
        define('CLASS_PATH',ROOT_PATH.'runtime'.DIRECTORY_SEPARATOR.'class'.DIRECTORY_SEPARATOR);
        # 检查目录是否存在
        if(!is_dir(CLASS_PATH)){
            # 递归创建目录
            mkdir(CLASS_PATH,0777,true);
        }
        # 日志文件缓存路径
        define('CACHE_LOG',ROOT_PATH.'runtime'.DIRECTORY_SEPARATOR.'log'.DIRECTORY_SEPARATOR);
        # 检查目录是否存在
        if(!is_dir(CACHE_LOG)){
            # 递归创建目录
            mkdir(CACHE_LOG,0777,true);
        }
        # 会话文件缓存路径
        define('CACHE_SESSION',ROOT_PATH.'runtime'.DIRECTORY_SEPARATOR.'session'.DIRECTORY_SEPARATOR);
        # 检查目录是否存在
        if(!is_dir(CACHE_SESSION)){
            # 递归创建目录
            mkdir(CACHE_SESSION,0777,true);
        }
        # 上传文件临时目录
        define('UPLOAD_TMP_DIR',ROOT_PATH.'runtime'.DIRECTORY_SEPARATOR.'upload'.DIRECTORY_SEPARATOR);
        # 检查目录是否存在
        if(!is_dir(UPLOAD_TMP_DIR)){
            # 递归创建目录
            mkdir(UPLOAD_TMP_DIR,0777,true);
        }
        # 模板编译缓存目录
        define('CACHE_VIEW',ROOT_PATH.'runtime'.DIRECTORY_SEPARATOR.'view'.DIRECTORY_SEPARATOR);
        # 检查目录是否存在
        if(!is_dir(CACHE_VIEW)){
            # 递归创建目录
            mkdir(CACHE_VIEW,0777,true);
        }
        # 是否为WEN 环境
        define('IS_WIN',strstr(PHP_OS, 'WIN') ? 1 : 0 );

        # 设置SessionCookie名称
        session_name('MiniKernelSession');

        # 修改session文件的储存位置
        session_save_path(CACHE_SESSION);
        
        # 设置图片上传临时目录
        ini_set('upload_tmp_dir', UPLOAD_TMP_DIR);

        # 修改session存储设置
        session_set_cookie_params(
            Config::get('sys','session_lifetime'),
            Config::get('sys','session_cookie_path'),
            Config::get('sys','session_range')
        );

        # 判断session存储方式
        if(env('session_save') == 'redis'){
            ini_set("session.save_handler", "redis");
            ini_set("session.save_path",
                "tcp://".Config::get('redis','host').":".Config::get('redis','port'));
        }
        # 启动session
        session_start();
        # 获取API模式传入的参数
        $param_arr = getopt('U:');
        # 判断是否为API模式
        if($param_arr['U']){
            $_SERVER['REDIRECT_URL'] = $param_arr['U'];
            $_SERVER['PHP_SELF'] = $param_arr['U'];
            $_SERVER['QUERY_STRING'] = $param_arr['U'];
        }
        # 设置资源路由
        Route::set_resources_driver(
            Route::get_resources_driver() -> set_folder(Config::get('abstract')) -> set_file_type([
                '.js'=>'application/javascript',
                '.css'=>'text/css',
                '.jpg'=>'image/jpg',
                '.jpeg'=>'image/jpeg',
                '.svg'=>'image/svg+xml',
            ])
        );
        # 设置url 分隔符
        Route::set_key_word(Config::get('sys','url_split'));
        try{
            # 加载路由
            Route::init(function($app,$controller,$action){
                $view_path = Config::get('sys','view_path');
                $view_path[] = ROOT_PATH.'app'.DIRECTORY_SEPARATOR.$app.DIRECTORY_SEPARATOR.'View';
                Config::set('sys',
                    $view_path
                    ,'view_path');
                # 应用名
                define('APP_NAME',$app);
                # 控制器名
                define('CONTROLLER_NAME',$controller);
                # 操作名
                define('ACTION_NAME',$action);
            });
        }catch (\Exception $exception){
            # 页面找不到
            Http::send_http_status(404);
        }

    }
}