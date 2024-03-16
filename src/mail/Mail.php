<?php
/**
 * 邮件发送类
 *
 */

namespace hotzhan\verifycode\mail;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use think\facade\Log;

Class Mail
{
    protected $config = [];

    protected $mail;
    protected $resultData;

    public function __construct()
    {
        $this->mail = new PHPMailer();
        if($config = config('mail'))
            $this->config = array_merge($this->config, $config);
    }

    /**
     * 初始化邮件服务器配置
     * @param array $server
     * @return void
     */
    public function mailInit(array $server)
    {
        //Server settings 服务器设置
        // 是否启用smtp的debug进行调试 会打印邮件发送过程的一些信息
        // 开发环境建议开启 生产环境注释掉即可 默认关闭debug调试模式 有1-4级调试
        //$this->mail->SMTPDebug = SMTP::DEBUG_SERVER;            //Enable verbose debug output
        $this->mail->SMTPDebug = $this->config['debug'] ?? 0;            //Enable verbose debug output
        //使用smtp方式发送邮件
        //$mail->isSMTP();                                      //Send using SMTP
        $this->mail->Mailer = $server['mailer'];
        // 邮件服务器地址
        $this->mail->Host       = $server['host'];              //Set the SMTP server to send through
        // 是否需要鉴权
        $this->mail->SMTPAuth   = $server['auth'];              //Enable SMTP authentication
        //邮件服务器账号
        $this->mail->Username   = $server['username'];          //SMTP username
        //邮件服务器密码
        $this->mail->Password   = $server['password'];          //SMTP password
        // 邮箱登录鉴权加密方式
        //$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;      //Enable implicit TLS encryption
        $this->mail->SMTPSecure = $server['encryption'];        //Enable implicit TLS encryption
        // 设置ssl连接smtp服务器的远程服务器端口号
        $this->mail->Port       = $server['port'];              //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`
        // 设置发送的邮件的编码
        if(isset($this->config['charset']))
            $this->mail->CharSet = $this->config['charset'];
        // 邮件文本是否支持html
        if(isset($this->config['ishtml']))
            $this->mail->isHTML($this->config['ishtml']);       //Set email format to HTML

        // 设置发件地址和发件人昵称
        $this->mail->setFrom( $server['username'], $server['name']);
    }

    public function send(array $sends)
    {
        $data = [];
        $servers = $this->config['servers'];
        $sendRes = false;
        //多服务器，按顺序依次发送，直到成功为止
        foreach ($servers as $key=>$value)
        {
            $data[$key] = [];
            $data[$key]['status'] = 'failure';
            $result = $this->singleSend($value, $sends);
            $resultData = $this->getResultData();
            $data[$key] = array_merge($data[$key], $resultData) ;
            if($result)
            {
                $sendRes = true;
                $data[$key]['status'] = 'success';
                break;
            }
            else
            {
                Log::info("send mail error msg: {$resultData['msg']}, mail server {$key} ");
            }
        }
        if($sendRes)
            $this->setResultData(200, '邮件发送成功', $data);
        else
            $this->setResultData(500, '邮件发送失败', $data);
        //halt($this->getResultData());
        return $sendRes;
    }

    /**
     * @param array $server
     * @param array $sends
     * @return bool
     */
    /**
     * 参数 $sends 示例
     *
    $sends = [
        'from' => [
            'address'=>'',
            'name'=> '',
        ],
        'to' => [
            ['address'=>'','name'=>''],
            ['address'=>'','name'=>''],
        ],
        'replay' => [
            ['address'=>'','name'=>''],
            ['address'=>'','name'=>''],
        ],
        'cc'=>[],
        'bcc'=>[],
        'attachment' => [
            ['filepath'=>'','name'=>''],
            ['filepath'=>'','name'=>''],
        ],
        'subject' => '',
        'body'=>'',
        'altbody'=>'',
    ];
     */
    public function singleSend(array $server, array $sends):bool
    {
        $this->mailInit($server);

        $check = $this->validateSends($sends);
        if(!$check)
            return $check;

        try
        {
            if(isset($sends['from']['address']) && $sends['from']['address'] != '')
            {
                $name = $sends['from']['name'] ?? '';
                $this->mail->setFrom($sends['from']['address'], $name);
            }
            // 设置收件人邮箱地址
            foreach ($sends['to'] as $to)
            {
                $name = $to['name'] ?? '';
                // 添加多个收件人 则多次调用方法即可
                $this->mail->addAddress($to['address'], $name);     //Add a recipient
            }

            //邮件回复
            if(isset($sends['replay']))
            {
                foreach ($sends['replay'] as $replay)
                {
                    $name = $replay['name'] ?? '';
                    $this->mail->addReplyTo($replay['address'], $name);     //Add a recipient
                }
            }
            //抄送
            if(isset($sends['cc']))
            {
                foreach ($sends['cc'] as $cc)
                {
                    $name = $cc['name'] ?? '';
                    $this->mail->addCC($cc['address'], $name);     //Add a recipient
                }
            }
            //暗抄送
            if(isset($sends['bcc']))
            {
                foreach ($sends['bcc'] as $bcc)
                {
                    $name = $bcc['name'] ?? '';
                    $this->mail->addBCC($bcc['address'], $name);     //Add a recipient
                }
            }

            //Attachments
            // 为该邮件添加附件
            if(isset($sends['attachment']))
            {
                foreach ($sends['attachment'] as $attachment)
                {
                    $name = $attachment['name'] ?? '';
                    $this->mail->addAttachment($attachment['filepath'], $name); //Add attachments , Optional name
                }
            }

            //邮件内容

            // 添加该邮件的主题/标题
            $this->mail->Subject = $sends['subject'];

            // 添加邮件正文
            if(isset($sends['body']))
            {
                $this->mail->Body = $sends['body'];
            }
            if(isset($sends['altbody']))
            {
                $this->mail->AltBody = $sends['altbody'];
            }

            //发送
            $sendRes = $this->mail->send();
            if($sendRes)
                $this->setResultData(200, '邮件发送成功');
            else
                $this->setResultData(500, '邮件发送失败');
            return $sendRes;

        } catch (Exception $e) {
            Log::write('send mail error' . $this->mail->ErrorInfo, 'error');
            $this->setResultData(500, $this->mail->ErrorInfo);
            return false;
        }
    }

    public function validateSends(array $sends):bool
    {
        /* 直接服务器配置里
        if(!isset($sends['from']['address']) || $sends['from']['address'] == '')
        {
            $this->setResultData(500, '请设置发件人邮箱');
            return false;
        }
        */
        if(!isset($sends['to']) || count($sends['to']) < 1)
        {
            $this->setResultData(500, '请设置收件人邮箱');
            return false;
        }

        if(!isset($sends['subject']) || $sends['subject'] == '')
        {
            $this->setResultData(500, '请设置邮件标题');
            return false;
        }

        return true;
    }

    protected function setResultData(int $code, string $msg='', array $data=[])
    {
        $this->resultData = [
            'code' => $code,
            'msg'=> $msg,
            'data'=> $data,
        ];
    }
    public function getResultData()
    {
        return $this->resultData;
    }

    /**
     * 邮件发送测试，这里以qq邮箱为例
     * @return void
     */
    public function test_qq()
    {
        //参考文章：https://www.cnblogs.com/woider/p/6980456.html
        //如果是宝塔面板，php安装一下扩展imap即可

        // 实例化PHPMailer核心类
        $mail = new PHPMailer(true);

        try {
            //Server settings 服务器设置
            // 是否启用smtp的debug进行调试 会打印邮件发送过程的一些信息 开发环境建议开启 生产环境注释掉即可 默认关闭debug调试模式
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;                      //Enable verbose debug output
            //使用smtp方式发送邮件
            $mail->isSMTP();                                            //Send using SMTP
            // 邮件服务器地址
            $mail->Host       = 'smtp.qq.com';                          //Set the SMTP server to send through
            // smtp需要鉴权 这个必须是true
            $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
            //qq邮箱账号
            $mail->Username   = '770524521@qq.com';                     //SMTP username
            //qq邮箱是使用授权码方式，非qq密码
            $mail->Password   = 'zkxxxxxxxxxxxxxxxx';                               //SMTP password
            // qq邮箱是设置使用ssl加密方式登录鉴权
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //Enable implicit TLS encryption
            // 设置ssl连接smtp服务器的远程服务器端口号
            $mail->Port       = 465;                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`
            // 设置发送的邮件的编码
            $mail->CharSet = 'UTF-8';

            //Recipients
            // 设置发件地址和发件人昵称
            $mail->setFrom('770524521@qq.com', 'Mailer');
            // 设置收件人邮箱地址
            $mail->addAddress('123123@qq.com', 'Joe User');     //Add a recipient
            // 添加多个收件人 则多次调用方法即可
            $mail->addAddress('123123@qq.com');               //Name is optional
            //$mail->addReplyTo('info@example.com', 'Information');
            //$mail->addCC('cc@example.com');
            //$mail->addBCC('bcc@example.com');

            //Attachments
            // 为该邮件添加附件
            //$mail->addAttachment('/var/tmp/file.tar.gz');         //Add attachments
            //$mail->addAttachment('/tmp/image.jpg', 'new.jpg');    //Optional name

            //Content
            // 邮件文本是否支持html
            $mail->isHTML(true);                                  //Set email format to HTML
            // 添加该邮件的主题
            $mail->Subject = 'Here is the subject';
            // 添加邮件正文
            $mail->Body    = 'This is the HTML message body <b>in bold!</b>';
            $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

            $mail->send();
            echo 'Message has been sent';
        } catch (Exception $e) {
            echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }
    }

    public function test_163()
    {
        //参考文章：https://www.cnblogs.com/woider/p/6980456.html
        //如果是宝塔面板，php安装一下扩展imap即可

        // 实例化PHPMailer核心类
        $mail = new PHPMailer(true);

        try {
            //Server settings 服务器设置
            // 是否启用smtp的debug进行调试 会打印邮件发送过程的一些信息 开发环境建议开启 生产环境注释掉即可 默认关闭debug调试模式
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;                      //Enable verbose debug output
            //使用smtp方式发送邮件
            $mail->isSMTP();                                            //Send using SMTP
            // 邮件服务器地址
            $mail->Host       = 'smtp.163.com';                          //Set the SMTP server to send through
            // smtp需要鉴权 这个必须是true
            $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
            //qq邮箱账号
            $mail->Username   = 'zhanxxxxxxx@163.com';                     //SMTP username
            //qq邮箱是使用授权码方式，非qq密码
            $mail->Password   = 'VWxxxxxxxxxxxxxx';                               //SMTP password
            // qq邮箱是设置使用ssl加密方式登录鉴权
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //Enable implicit TLS encryption
            // 设置ssl连接smtp服务器的远程服务器端口号
            $mail->Port       = 465;                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`
            // 设置发送的邮件的编码
            $mail->CharSet = 'UTF-8';

            //Recipients
            // 设置发件地址和发件人昵称
            $mail->setFrom('123123@163.com', 'HotAdmin');
            // 设置收件人邮箱地址
            $mail->addAddress('123123@qq.com', 'Joe User');     //Add a recipient
            // 添加多个收件人 则多次调用方法即可
            $mail->addAddress('123123@qq.com');               //Name is optional
            //$mail->addReplyTo('info@example.com', 'Information');
            //$mail->addCC('cc@example.com');
            //$mail->addBCC('bcc@example.com');

            //Attachments
            // 为该邮件添加附件
            //$mail->addAttachment('/var/tmp/file.tar.gz');         //Add attachments
            //$mail->addAttachment('/tmp/image.jpg', 'new.jpg');    //Optional name

            //Content
            // 邮件文本是否支持html
            $mail->isHTML(true);                                  //Set email format to HTML
            // 添加该邮件的主题
            $mail->Subject = 'Here is the subject';
            // 添加邮件正文
            $mail->Body    = 'This is the HTML message body 验证码：<b style="color:red;">1234</b>';
            $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

            $mail->send();
            echo 'Message has been sent';
        } catch (Exception $e) {
            echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }
    }
}
