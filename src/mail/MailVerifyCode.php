<?php

namespace hotzhan\verifycode\mail;

use hotzhan\verifycode\VerifyCode;

class MailVerifyCode
{
    protected static $instance;

    protected  $config=[];
    protected $mail;
    protected $verify;
    protected $resultData;

    protected $cacheKey;

    public function __construct()
    {
        if($mailConfig = config('mail'))
            $this->config = array_merge($this->config, $mailConfig) ;
        $this->mail = new Mail();
        $this->verify = new VerifyCode();

        $this->cacheKey = 'mail_send_ip_' . request()->ip();//客户端信息缓存key，根据ip设置
    }
    public static function instance($options = []):MailVerifyCode
    {
        if(is_null(self::$instance))
            self::$instance = new static($options);
        return self::$instance;
    }

    public function sendMail(string $mailAddress, string $subject , string $body): bool
    {
        $codenum = intval($this->config['verifycode']['codenum']);
        $expire = intval($this->config['verifycode']['expire']) ;
        $limitTime = intval($this->config['verifycode']['limittime']);
        //校验短信是否频繁发送超限制
        if(!$this->checkSendLimt())
        {
            return false;
        }
        //生成验证码
        $mailCode = $this->verify->generateNumericCode($codenum);
        $expireStr = formatTimestamp($expire);
        $body = str_replace(['${code}', '${expire}'], [$mailCode, $expireStr], $body);

        //发送验证码短信
        try {
            $sendRes = $this->mail->send([
                'to'=>[['address'=>$mailAddress]],
                'subject' => $subject,
                'body' => $body,
            ]);
        }
        catch (\Exception $exception)
        {
            //可能短信网关配置失败等导致异常
            //halt($exception);
            $this->setResultData(500, '邮件发送失败，服务端异常', ['token'=>'']);
            return false;
        }
        if($sendRes)
        {
            //该验证码保存到缓存，过期时间为sms里配置的过期时间
            $token = $this->verify->setVerifyCodeCache($mailCode, $mailAddress, $expire);
            //将客户端对应的ip信息保存到缓存，用于后续频繁发送限制发送校验
            $this->verify->setClientInfoCache($this->cacheKey, $limitTime);
            $this->setResultData(200, '邮件发送成功', ['token'=>$token]);
        }
        else
        {
            $this->setResultData(500, '邮件发送失败', ['token'=>'']);
        }
        return $sendRes;
    }

    public function sendVerifyCodeMail(string $mailAddress): bool
    {

        $subject = 'HotAdmin验证码';
        $body = '您的HotAdmin证码是：<b>${code}</b>，请在${expire}内使用，如非本人操作，请忽略本邮件！';
        return $this->sendMail($mailAddress, $subject, $body);
    }
    public function sendActiveMail(string $mailAddress):bool
    {
        $subject = 'HotAdmin激活验证码';
        $body = '您的HotAdmin激活证码是：<b>${code}</b>，请在${expire}内使用，如非本人操作，请忽略本邮件！';
        return $this->sendMail($mailAddress, $subject, $body);
    }

    public function checkMailVerifyCode(string $token, string $code, string $account):bool
    {
        $res = $this->verify->checkVerifyCode($token, $code, $account);
        if($res)
        {
            $this->setResultData(200, '验证码校验通过');
            return true;
        }
        else
        {
            $this->setResultData(500, '验证码错误');
            return false;
        }
    }

    public function checkSendLimt():bool
    {
        $limitCount = intval( $this->config['verifycode']['limitcount'] ?? 0 ) ;
        $limitTime = intval( $this->config['verifycode']['limittime'] ?? 0 );

        $limitRes = $this->verify->checkClientLimt($this->cacheKey, $limitCount);
        if(!$limitRes)
        {
            $this->setResultData(500, '频繁发送邮件，请在'. formatTimestamp($limitTime). '后再试！');
        }
        return $limitRes;
    }

    protected function setResultData(int $code, string $msg='', array $data=[]):array
    {
        $this->resultData = [
            'code' => $code,
            'msg'=> $msg,
            'data'=> $data,
        ];
    }
    public function getResultData():array
    {
        return $this->resultData;
    }
}