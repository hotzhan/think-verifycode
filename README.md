## hotzhan/think-verifycode
- ThinkPHP验证码发送库(邮件+短信 两种方式可用)
- 短信发送依赖 easy-sms
- 邮件发送依赖 phpmailer （需安装php-imap扩展，如果是宝塔面板在对应php版本设置里可直接安装）

## ThinkPHP适用版本
- ThinkPHP 6 / 8

## 安装本库
```shell
composer require hotzhan/think-verifycode
```
## 配置文件
- config目录里的sms.php和mail.php文件会自动复制到thinkphp的config目录里
- 如果没有自动复制，请自己手动复制一下
- 具体配置参考配置文件里有详细注释


## 如何使用
### 发送短信验证码示例

```php
        use hotzhan\verifycode\sms\SmsVerifyCode;
        
        //注册验证码
        public function regSms(SmsVerifyCode $sms)
        {
            //前端获取到手机号码
            $param = request()->param();
            $phoneNumber = $param['mobile'];
            //发送注册验证码
            $sms->sendRegisterSms($phoneNumber);
            //返回验证码发送结果，结果里包含token，需要返回给前端，验证时前端需要提交这个token
            return $sms->getResultData();
        }
        
        //验证码校验
        public function checkSms(SmsVerifyCode $sms)
        {
            $param = request()->param();
            //$param里的字段根据自己前端的设置
            $phoneNumber = $param['mobile'];
            $code = $param['code'];
            $token = $param['smstoken'];
            //校验验证码和对应的手机号
            $res = $sms->checkSmsVerifyCode($token, $code, $phoneNumber);
            if(!$res)//验证码校验不通过
                return $sms->getResultData();
        }
        

```
### 发送邮件验证码示例

```php
        use hotzhan\verifycode\mail\MailVerifyCode;
        
        //注册验证码
        public function regMail(MailVerifyCode $mail)
        {
            //前端获取到手机号码
            $param = request()->param();
            $mailAddress = $param['address']; //12345678@qq.com
            //给对应邮箱发送验证码
            $mail->sendVerifyCodeMail($mailAddress)
            //返回验证码发送结果，结果里包含token，需要返回给前端，验证时前端需要提交这个token
            return $mail->getResultData();
        }
        
        //验证码校验
        public function checkMail(MailVerifyCode $mail)
        {
            $param = request()->param();
            //$param里的字段根据自己前端的设置
            $mailAddress = $param['address'];
            $code = $param['code'];
            $token = $param['mailtoken'];
            //校验验证码和对应的邮箱
            $res = $mail->checkMailVerifyCode($token, $code, $mailAddress);
            if(!$res)//验证码校验不通过
                return $sms->getResultData();
        }
```


