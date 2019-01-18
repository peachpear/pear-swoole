# pear-swoole
pear让你更畅快地编程。pear-swoole是以pear-api为基础，增加聊天必要服务，重整为支持聊天服务的项目。 

### 前提准备

必要服务支持：Mysql、php-cli、Swoole、Redis、Kafka、RabbitMQ

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

// log队列消费者开始运行
php yii consumer/start log

// mail队列消费者开始运行
php yii consumer/start mail

// ticket队列消费者开始运行
php yii consumer/start ticket

// ticket队列消费者停止运行
php yii consumer/stop ticket

// ticket队列消费者重启运行
php yii consumer/restart ticket

// ticket延迟队列消费者开始运行
php yii delay/start ticket

// push_socket队列消费者开始运行
php yii consumer/start pushSocket
```

#### 特别说明
其实，这个项目中最核心的就是AMQP连接RabbitMQ那一段代码，完全可以不使用框架。
之所以借用Yii2框架，就是为了方便使用日志功能，日志这一块可以注意下。

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
└── console
    ├── components
    ├── config
    └── controllers    
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