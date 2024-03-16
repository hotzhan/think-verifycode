<?php
/*
 * 短信发送配置
 * 作者：hotAdmin
 */

return [
    //-------------------easy-sms的设置-------------------start
    'easysms' => [
        'config'=>[
            // HTTP 请求的超时时间（秒）
            'timeout' => 5.0,

            // 默认发送配置
            'default' => [
                // 网关调用策略，默认：顺序调用
                'strategy' => \Overtrue\EasySms\Strategies\OrderStrategy::class,

                // 默认可用的发送网关
                'gateways' => [
                    'aliyun','yunpian',
                ],
            ],
            // 可用的网关配置
            'gateways' => [
                'errorlog' => [
                    'file' => app()->getRuntimePath() . 'sms' . DIRECTORY_SEPARATOR. 'easy-sms.log',//日志目录
                ],
                'aliyun' => [
                    'access_key_id' => 'Lxxxxxxxxxxxxxx',//阿里云的 AccessKey ID
                    'access_key_secret' => 'oxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',//阿里云的 AccessKey Secret
                    'sign_name' => '阿里云短信测试',//短信签名
                ],
                'yunpian' => [
                    'api_key' => '824f0ff2f71cab52936axxxxxxxxxx',//云片的api key
                ],
                //...
            ],
        ],
    ],
    //-------------------easy-sms的设置-------------------end

    //其它设置

    //短信发送验证码设置
    'verifycode'=>[
        'codenum' => 6,//验证码位数
        'expire' => 300,//验证码过期时间秒
        'limitcount' => 5,//0为不限制 ，如果频繁发送超过5条，需要等limittime时间后才能再发送
        'limittime' => 43200,//频繁发送，限制时间秒 12小时
    ],
];