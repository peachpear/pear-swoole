# pear-swoole
pear让你更畅快地编程。pear-swoole是以pear-api为基础，增加聊天必要服务，重整为支持聊天服务的项目。 

### 前提准备

必要服务支持：php-cli、Swoole、Mysql、Kafka、RabbitMQ、Redis

可选服务支持：Elasticsearch、Kibana、Jenkins

### 使用说明

```
cd /yourProjectParentPath

composer create-project peachpear/pear-swoole yourProjectName

cd /path/yourProjectName/console/config

ln -sf dev.php main.php
```

### 数据表准备

chat配置表：
```
CREATE TABLE `chat_config` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `app_token` varchar(11) NOT NULL DEFAULT '' COMMENT '应用token',
  `role` varchar(20) NOT NULL DEFAULT '' COMMENT '角色',
  `appid` varchar(50) NOT NULL DEFAULT '' COMMENT '应用微信appid',
  `secret` varchar(50) NOT NULL DEFAULT '' COMMENT '应用微信secret',
  `is_blocked` tinyint(1) NOT NULL COMMENT '是否禁用',
  `is_deleted` tinyint(1) NOT NULL COMMENT '是否删除',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='接入应用表';
```

客户端在线列表
```
CREATE TABLE `client_online_list` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `app_token` varchar(11) NOT NULL DEFAULT '' COMMENT '应用token',
  `role` varchar(20) NOT NULL DEFAULT '' COMMENT '角色',
  `client_token` varchar(50) NOT NULL DEFAULT '客户端token',
  `client_id` varchar(50) NOT NULL DEFAULT 'base64_encode(serverIp_serverPort_clientFd)',
  PRIMARY KEY (`id`),
  KEY `idx_client_token` (`client_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='客户端在线列表';
```

### 运行示例
```
cd /path/yourProjectName/public

// swoole服务端开始运行
php yii swoole/start

// swoole服务端停止运行
php yii swoole/stop

// proxy服务端开始运行
php yii proxy/start

// proxy服务端停止运行
php yii proxy/stop
```

#### 特别说明
proxy服务端的作用是代理服务，作为其中一个swoole服务端与其他swoole服务端信息交互的中间服务而存在。
这样设计的目的是本着一个进程一个服务做一件事，尽量为各独立服务解耦，使服务逻辑更清晰。

#### 目录结构
```
├── common
│   ├── components
│   ├── config
│   ├── dao
│   ├── exception
│   ├── lib
│   ├── misc
│   ├── models
│   └── service
├── console
│   ├── components
│   ├── config
│   └── controllers    
└── public
    └── yii   
```

#### 编码规范
```
1.PHP所有 关键字 必须 全部小写（常量 true 、false 和 null 也 必须 全部小写）
2.命名model对应的class 必须 以Model结尾
3.命名service对应的class 必须 以Service结尾
4.命名dao对应的class 必须 以Dao结尾
5.数据库查询返回接口 应该 使用model对象/对象列表
6.数据库的key必须是dbname+DB形式，e.g:dbname为test,则key为testDB
7.dao目录存放sql语句或者orm
8.model目录存放对应的数据实例对象
9.service目录存放业务逻辑处理
```

#### 客户端示例

浏览器在线处理业务逻辑：
```
// 处理收到的服务端信息
dealReceiveMsg(data, websocket) {
    switch (data.event) 
    {
        case 'CONNECT' :
            // 客户端发起在线连接后，服务端连接成功后，发送信息给客户端要求补充信息
            // 获取client_token后，发送到服务端记录个人详情
            var sendMsg = '{"event":"CONNECT","data":{"client_token":"abc123f3rf4vds43534fd"}}';
            websocket.send(sendMsg);
            break;
        case 'ONLINE' :
            // 服务器通知客户端个人详情记录成功
            // 客户端可以做在线后的事，如获取自己未读的信息
            ......
        case 'NEW_MSG' :
            // 服务器发送新信息给客户端
            // 客户端做展示处理逻辑
            ......
        default:
            break;
    }
}

// 客户端浏览器js代码
$(document).on('click',"#online", function () {
    if ("WebSocket" in window) {
        // 打开一个 web socket
        var ws = new WebSocket("ws://localhost:9605");
    
        // 通信发生错误
        ws.onerror = function() {
            alert("WebSocket连接发生错误");
        }
    
        // 成功打开连接通路
        ws.onopen = function() {
            alert("WebSocket连接成功");
        }
    
        // 收到服务端数据
        ws.onmessage = function(ReceiveEvent) {
            var receiveMsg = JSON.parse(ReceiveEvent.data);  // 收到的服务端数据
            dealReceiveMsg(receiveMsg, ws);
        }
    
        // 连接关闭时触发
        ws.onclose = function() {
            alert("连接已关闭..."); 
        }
    
        // 监听窗口关闭事件，当窗口关闭时，主动关闭websocket连接，防止连接还没断开就关闭窗口server端抛异常
        window.onbeforeunload = function () {
            ws.close();
        }
    
        // 发送心跳包，一分钟一次
        function heartBeat(){
            ws.send('{"event":"HEART_BEAT","data":[]}');
        }
        setInterval(heartBeat, 60000);
    } else {
        alert("您的浏览器不支持 WebSocket!");
    }    
});
```
