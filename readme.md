# Laravel-WebSocket  
为`Laravel`项目量身开WebSocke服务驱动器，可以让您快速部署websocket服务程序，该程序具有以下特点，

（1）开箱即用，配置简单

（2）丰富的api。

（3）强大的SocketManager进程管理控制

（4）全局通信组件，使WebSocket服务与Http服务完美结合

​	

## 一、安装与配置： 

#### （一）安装

```composer
    在命令行执行：composer require kitty/websocket
```

#### （二）配置

​	在命令行输入`php artisan vendor:publish` 就会将配置文件提取至/config/websocket.php,如下：

```php
 return  [
        'address' => env('SOCKET_ADDRESS', '127.0.0.1'), //监听地址
        'port' => env('SOCKET_PORT', 2000),        		//监听端口
   		'max_coon' => 50,				   //最大连接数
        'console' => true,			//是否开启控制台信息
 ];
```

​	可以看到配置中的`adress`与`port`，选项默认指向了env，所以你还可以通过在env 文件中 SOCKET_ADDRESS与SOCKET_PORT来配置服务地址与端口。

#### （三）添加服务提供者

​	修改``/config/app.php``文件下的'providers'数组内如下：

```php
'providers' => [
  		//...
         Kitty\WebSocket\Providers\WebSocketServiceProvider::class,
 ]
```

​	

## 二、开启服务

```php
     php artisan socket:run
```

​	上述命令会开启一个常驻内存的socket进程 ，并监听上一步骤配置项的地址与端口，来处理websocket请求。

​	测试：kitty/websocket 提供了一个简单的demo 开启服务成功后可以在浏览器访问localhost/kitty/websocket/demo 进行测试

​	

## 三、创建事件响应器

​	1、一旦服务启动，每个socket请求都将会被kitty/websocket响应到`\App\Job\WebSocketJob`类中，所以你可以在这个类中，按照你的业务逻辑来处理这些请求。

​	2、你可以通过命令`php artisan socket:make job` 来快速的在app/Jobs目录下创建事件处理类`WebSocketJob` ,`kitty/websocket`已经为你解决了继承关系，并生成了示例代码。

​	

## 四、事件响应

​	如上所述，`kitty/websocket`的事件响应代码都放置在了app/Jobs/WebSocketJob.php中。共包括五种事件类型：

​	1、用户建立连接时，响应  login() 事件；

​	2、用户断开连接时，响应logout()事件；

​	3、用户发送消息时，响应massage()事件；

​	4、接收到管理进程(SocketManager)指令时，响应manager()事件；

​	5、timer()事件，用来响应定时任务

​	

## 五、进程管理（SoketManager）

​	进程管理是kitty/websocket的特色功能，提供了强大进程管理、进程监控的能力，除此之外，借助SoketManager还可以在项目任意一处代码中实现WebServer与SocketServer的通信。

```composer
    进入进程管理控制台：php artisan socket:manager
```

​	控制台是webSocket开发调试利器，可以监控和干预服务进程，输入上述命令，进入控制台，即进入指令输入模式，

​	指令是SocketManager 与服务进程通信的命令文本，格式如下

​	send  你好    --id    12    //向id为12的客户端发送‘你好’

​	{主指令}   {主指令参数1}  {主指令参数2}  {主指令参数...}  {副指令(以--开头)}  {副指令参数}  {选项1(以-开头)}  {选项...} 

​	进程管理控制台常用指令：

```
show user						   //以列表形式查看当前所有连接客户端
send 发送内容 --id 12 				//向id为12的用户发送 
send 发送内容 --ip 192.168.1.173	 //向ip为192.168.1.173所有连接用户发送信息
send 发送内容 --uuid xxx    		 //向uuid为xxx的用户发送消息
broadcast 发送内容					//向当前所有用户发送消息
close 12						   //关闭id为12的用户连接
close --ip 192.168.1.173			//关闭ip为192.168.1.173的所有用户
```

​	

## 六、API

#### （一）Socket API (只能在事件响应器（\App\Job\WebSocketJob）中使用)

​	1、where(  $key,$ $value1$,  $[value2]$ )  //筛选指定条件的用户放置于用户栈；通常配合send(),close()使用方法与Laravel Collection的where一致

​	2、send( $message$ )  //向用户栈所有客户端发送信息 在where之后调用

​	3、close( ) 		     //关闭用户栈所有客户端，在where之后调用

​	上述几条用法示例 ：

```  php
	// \App\Job\WebSocketJob
	
	$this->where('ip','192.168.1.173')->send('连接将被关闭')->close();
```

​	4、sendById( $id$, $message$ ) 				// 向指定id 发送信息

```php
	// \App\Job\WebSocketJob

	public function login()
    {
      $this->sendById($this->user->id,'欢迎进入！');
    }
```

​	5、getAllUser( )     						//获取当前所有连接用户

​	6、getCcurrentUsers( ) 					//获取当前用户栈中所有用户

​	7、user( $ id$ )								//返回给定id的用户

​	8、addAttributeToUser( $id$, (array)  $attr$ )       //向给定id的用户属性表中添加属性（键值对）

​	9、broadcast( $message$ )  					//向所有连接用户发送信息

​	10、默认用户属性 :

​		id  		//(int)用户ID

​		uuid	//(string)用户UUID

​		socket     //(resourse)用户连接套字

​		hand   	//(bool)是否握手

​		ip		//(string)用户ip

​		port	//(int)用户连接端口

​		type	//(string)用户类型 

#### （二）Globle API（在全文使用，由SocketManager提供）

​	上文的第五条中介绍了SocketManager指令发送，除了在命令行中输入指令，还可以调用`Kitty\WebSocket\SocketManager` 中的send()方法向服务进程发送一条指令，实现在任何场景下与服务进程通信，send只接受一个字符串参数作为指令码，指令码的写法上文中已经介绍，这里不再赘述。

​	示例：

```php
	//示例场景说明：当有新的文章被发布的时候，向订阅该栏目的用户推送一条提示消息。
	public function addArticle()
    {
      	$res = Article::Ceate(['title' => '这是一篇测试文章','category_id' => 10]);
      	$users = $res->User::has('category',function($q){
       	 	$q->where('id',10);
      	});
     	if($res) {
        	$ma = new SocketManager;
        	$ma->connect();
        	foreach($users as $user) $ma->send('send 您订阅的栏目有新文章发布 --id '.$user->id);
      	}
    }
```



​	

​	

​		