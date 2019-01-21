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

### 运行示例
```
cd /path/yourProjectName/public

// swoole服务端开始运行
php yii swoole/start

// swoole服务端停止运行
php yii swoole/stop

// proxy服务端开始运行
php yii proxy/start
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