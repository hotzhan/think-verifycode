<?php

namespace hotzhan\verifycode\sms;

use hotzhan\verifycode\VerifyCode;
use Overtrue\EasySms\EasySms;

Class SmsVerifyCode
{
    protected static $instance;

    protected  $config=[];
    protected $easySms;
    protected $verify;
    protected $resultData;

    protected $cacheKey;

    public function __construct()
    {
        if($smsConfig = config('sms'))
            $this->config = array_merge($this->config, $smsConfig) ;
        $this->easySms = new EasySms($this->config['easysms']['config']);
        $this->verify = new VerifyCode();

        $this->cacheKey = 'sms_send_ip_' . request()->ip();//客户端信息缓存key，根据ip设置
    }

    public static function instance($options = []):SmsVerifyCode
    {
        if(is_null(self::$instance))
            self::$instance = new static($options);
        return self::$instance;
    }

    public function sendSms(string $phoneNumber, string $content , string $template):bool
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
        $smsCode = $this->verify->generateNumericCode($codenum);
        //发送验证码短信
        try {
            $sendRes = $this->easySms->send($phoneNumber, [
                'content'=> $content,
                'template'=> $template,
                'data'=> [
                    'code' => $smsCode,
                ],
            ]);
        }
        catch (\Exception $exception)
        {
            //可能短信网关配置失败等导致异常
            //halt($exception);
            $this->setResultData(500, '短信发送失败，服务端异常', ['token'=>'']);
            return false;
        }


        //这里可能多网关
        if($this->getSendStatus($sendRes) == 'success')
        {
            //该验证码保存到缓存，过期时间为sms里配置的过期时间
            $token = $this->verify->setVerifyCodeCache($smsCode, $phoneNumber, $expire);
            //将客户端对应的ip信息保存到缓存，用于后续频繁发送限制发送校验
            $this->verify->setClientInfoCache($this->cacheKey, $limitTime);
            $this->setResultData(200, '短信发送成功', ['token'=>$token]);
            return true;
        }
        else
        {
            $this->setResultData(500, '短信发送失败', ['token'=>'']);
            return false;
        }
    }

    /**
     * 根据发送返回的结果获取对应状态，有可能会有多网关的情况需要判断
     * @param array $result
     * @return mixed|string
     */
    public function getSendStatus(array $result):string
    {
        $gateways = $this->easySms->getConfig()->get('default.gateways');
        $resultCount = count($result);
        $gateway = $gateways[$resultCount - 1];//可以判断多网关的情况
        return $result[$gateway]['status'];
    }

    public function sendRegisterSms(string $phoneNumber):bool
    {
        $content = '注册验证码是：${code}，请在5分钟内使用，如非本人操作，请忽略本短信！';
        $template = 'SMS_154950909';
        return $this->sendSms($phoneNumber, $content, $template);
    }
    public function sendLoginSms(string $phoneNumber):bool
    {
        $content = '登录证码是：${code}，请在5分钟内使用，如非本人操作，请忽略本短信！';
        $template = 'SMS_154950909';
        return $this->sendSms($phoneNumber, $content, $template);
    }

    public function checkSmsVerifyCode(string $token, string $code, string $account):bool
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
            $this->setResultData(500, '频繁发送短信验证码，请在'. formatTimestamp($limitTime). '后再试！');
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