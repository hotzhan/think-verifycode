<?php
/**
 *  验证码类
 */

namespace hotzhan\verifycode;

use hotzhan\verifycode\Random;
use think\facade\Cache;

class VerifyCode
{
    protected $cacheKey;

    public function __construct()
    {
        $this->cacheKey = 'sms_send_ip_' . request()->ip();//客户端信息缓存key，根据ip设置
    }

    public function generateNumericCode(int $count)
    {
        //生成对应位数的随机数字验证码
        return Random::numeric($count);
    }

    /**
     * 保存验证码信息到缓存
     * @param string $code 验证码
     * @param string $account 验证码对应的手机号或者账号信息都保存
     * @param $expire 过期时间
     * @return string
     */
    public function setVerifyCodeCache(string $code, string $account, int $expire)
    {
        //验证码和对应的手机号(或者邮箱账号等信息)保存到缓存，过期时间为sms里配置的过期时间
        $token = md5(Random::uuid());
        $key = $token . '_' . $code;
        $value = [$code, $account];
        Cache::set($key, $value, $expire);

        return $token;
    }

    public function setClientInfoCache(string $cacheKey, int $expire)
    {
        //'xxx_ip_' . request()->ip();//网页客户端信息缓存key，一般根据ip设置
        //将客户端信息保存到缓存，可用于后续检验是否同一客户端频繁操作
        if(Cache::has($cacheKey))
            Cache::inc($cacheKey);
        else
            Cache::set($cacheKey, 1, $expire);
    }

    public function getClientInfoCache(string $cacheKey)
    {
        return Cache::get($cacheKey);
    }
    public function checkClientLimt(string $cacheKey, int $limitCount)
    {
        //判断是否频繁操作
        if($limitCount > 0 && Cache::has($cacheKey))
        {
            $count = Cache::get($cacheKey);
            if($count >= $limitCount)
            {
                return false;
            }
        }
        return true;
    }

    /**
     * 校验验证码是否正确
     * @param string $token 短信发送时生成的一个token返回给前端，在提交时验证这个token
     * @param string $code 验证码
     * @param string $account 验证码对应的手机号或者邮箱等
     * @return bool
     */
    public function checkVerifyCode(string $token, string $code, string $account):bool
    {
        $key = $token . '_' . $code;
        if(Cache::has($key))
        {
            $value= Cache::get($key);
            //dump($value);
            if(isset($value[1]) && $value[1] == $account)
                return true;
        }
        return false;
    }
}