# Laravel-WebSocket  
为`Laravel`项目量身开WebSocke服务驱动器，可以让您快速部署websocket服务程序，该程序具有以下特点，

（1）开箱即用，配置简单

（2）丰富的api。

（3）强大的SocketManager进程管理控制

（4）全局通信组件，使WebSocket服务与Http服务完美结合

## 一、安装与配置： 

###（一）安装

```composer
    在命令行执行：composer require kitty/websocket
```

### （二）配置

​	修改/config/queue.php文件 在connections字段中添加一个元素（自定义队列驱动）如下：

```php
'connections'=>[
  	//....
    //添加以下代码
  	 'websocket' => [
            'driver' => 'websocket',
            'connection' => 'default',
            'queue' => 'default',
            'address'=>'192.168.1.114', //监听地址
            'port'=>'2000',        		//监听端口
            'console'=>true,			//是否开启控制台信息
        ],
]
```

###（三）添加服务提供者

​	修改``/config/app.php``文件下的'providers'数组内如下：

```php
'providers' => [
  		//...
         Kitty\WebSocket\Providers\WebSocketServiceProvider::class,
 ]
```



## 二、开启服务

```php
      'paths' => [
            resource_path('views'),
            base_path()
        ],
```


## 4、提取配置文件：

```composer
    在命令行执行：php artisan vendor:publish
```


## 5、修改默认配置（可选）

   * 打开项目根目录下的config目录下的slice.php。
   * 修改core配置项下的name与path,其中name是项目组目录名，你未来所创建的所有目录都将放在这里，默认是Core;path是项目组所在路径默认放在根目录的app下。
   * 注意如果修改了默认的路径，并且不再是/app的时候，你需要修改composer.json,如下
   ```json
    "autoload": {
        "classmap": [
            "database"
        ],
        "psr-4": {
            "App\\": "app/",
            "Core\\": "Core/"
        }
    }
   ```


## 6、创建应用

```php
1) 命令行运行：php artisan make:app
2) 按提示输入应用名称
3）等待几秒钟应用就被创建完成
```


## 7、使用说明

假如我们采用默认配置，创建一个叫home的应用，slice就会在/app/Core/下创建一个为Home的应用，slice已经为你默认创建的Controllers和Views，他们是存放控制器和视图文件的，并且slice默认创建了一个demo的控制器和视图。在Home/下还已经建立好了route.php用来书写路由，他们被分配在home分组下。
你可以使用app_view()方法来像view()一样渲染视图,只不过他会动态查找本应用下的视图文件，而不是resources目录下