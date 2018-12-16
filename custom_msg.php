<?php
error_reporting(1);
header('Content-type:text/html; Charset=utf-8');
/* 配置开始 */
$appid = '';                          //微信公众平台->开发->基本配置->AppID
$appsecret = '';        //微信公众平台->开发->基本配置->AppSecret
$openid='';                 //接收消息的用户的openid，如果留空，则默认自动获取当前用户的openid
$msgType = 'text';          //发送消息的类型 文本：text ，图片：image
$msg = '你好';                  //如果类型是text，这里填写文本消息内容。如果是image，这里填写素材id（media_id）
$imgPath = '';              //如果类型是image且素材id为空，则填写图片的绝对路径，例如：D:/www/1.jpg
/* 配置结束 */
$wx = new WxService($appid,$appsecret);
if(!$openid){
    $openid = $wx->GetOpenid();      //获取openid
    if(!$openid) exit('获取openid失败');
}
if($msgType=='text' && !$msg){
    exit('发送消息内容不能为空！');
}
if($msgType=='image' && !$msg){
    //发送图片
    if(!$msg){
        if(!$imgPath) exit('图片路径不能为空！');
        $msg = $wx->uploadMedia($imgPath);
    }
}
$result = $wx->postMsg($openid,$msg,$msgType);
if($result['errcode']==0){
    echo '<h1>消息发送成功！</h1>';
}else{
    echo '<h1>消息发送失败：'.$result['errmsg'].'</h1>';
}
class WxService
{
    protected $appid;
    protected $appsecret;
    protected $token = null;
    public function __construct($appid, $appsecret)
    {
        $this->appid = $appid;
        $this->appsecret = $appsecret;
        $this->token = $this->getToken();
    }

    function getToken($force=false) {
        if($force===false && $this->token) return $this->token;
        $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$this->appid.'&secret='.$this->appsecret;
        $res = self::curlGet($url);
        $result = json_decode($res, true);
        if($result['errmsg']){
            exit($res);
        }
        //access_token有效期是7200s
        return $result['access_token'];
    }

    /**
     * 通过跳转获取用户的openid，跳转流程如下：
     * 1、设置自己需要调回的url及其其他参数，跳转到微信服务器https://open.weixin.qq.com/connect/oauth2/authorize
     * 2、微信服务处理完成之后会跳转回用户redirect_uri地址，此时会带上一些参数，如：code
     * @return 用户的openid
     */
    public function GetOpenid()
    {
        //通过code获得openid
        if (!isset($_GET['code'])){
            //触发微信返回code码
            $baseUrl = $this->getCurrentUrl();
            $url = $this->__CreateOauthUrlForCode($baseUrl);
            Header("Location: $url");
            exit();
        } else {
            //获取code码，以获取openid
            $code = $_GET['code'];
            $openid = $this->getOpenidFromMp($code);
            return $openid;
        }
    }

    public function getCurrentUrl()
    {
        $scheme = $_SERVER['HTTPS']=='on' ? 'https://' : 'http://';
        $uri = $_SERVER['PHP_SELF'].$_SERVER['QUERY_STRING'];
        if($_SERVER['REQUEST_URI']) $uri = $_SERVER['REQUEST_URI'];
        $baseUrl = urlencode($scheme.$_SERVER['HTTP_HOST'].$uri);
        return $baseUrl;
    }
    /**
     * 通过code从工作平台获取openid机器access_token
     * @param string $code 微信跳转回来带上的code
     * @return openid
     */
    public function GetOpenidFromMp($code)
    {
        $url = $this->__CreateOauthUrlForOpenid($code);
        $res = self::curlGet($url);
        //取出openid
        $data = json_decode($res,true);
        $this->data = $data;
        $openid = $data['openid'];
        return $openid;
    }
    /**
     * 构造获取open和access_toke的url地址
     * @param string $code，微信跳转带回的code
     * @return 请求的url
     */
    private function __CreateOauthUrlForOpenid($code)
    {
        $urlObj["appid"] = $this->appid;
        $urlObj["secret"] = $this->appsecret;
        $urlObj["code"] = $code;
        $urlObj["grant_type"] = "authorization_code";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://api.weixin.qq.com/sns/oauth2/access_token?".$bizString;
    }
    /**
     * 构造获取code的url连接
     * @param string $redirectUrl 微信服务器回跳的url，需要url编码
     * @return 返回构造好的url
     */
    private function __CreateOauthUrlForCode($redirectUrl)
    {
        $urlObj["appid"] = $this->appid;
        $urlObj["redirect_uri"] = "$redirectUrl";
        $urlObj["response_type"] = "code";
        $urlObj["scope"] = "snsapi_base";
        $urlObj["state"] = "STATE"."#wechat_redirect";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://open.weixin.qq.com/connect/oauth2/authorize?".$bizString;
    }
    /**
     * 拼接签名字符串
     * @param array $urlObj
     * @return 返回已经拼接好的字符串
     */
    private function ToUrlParams($urlObj)
    {
        $buff = "";
        foreach ($urlObj as $k => $v)
        {
            if($k != "sign") $buff .= $k . "=" . $v . "&";
        }
        $buff = trim($buff, "&");
        return $buff;
    }

    function postMsg($openid,$msg,$type='text')
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token='.$this->token;
        $data['touser'] = $openid;
        $data['msgtype'] = $type;
        if($type=='text') $data['text']['content'] = $msg;
        else $data['image']['media_id'] = $msg;
        $result = self::curlPost($url,self::xjson_encode($data));
        $msg = json_decode($result,true);
        return $msg;
    }

    /**
     * 上传临时素材
     * @param $filepath   图片路径
     * @param $type   图片（image）、语音（voice）、视频（video）和缩略图（thumb）
     * @return 素材id
     */
    function uploadMedia($filepath,$type='image')
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/media/upload?access_token='.$this->getToken().'&type='.$type;
        if(version_compare(phpversion(),'5.5.0') >= 0 && class_exists('\CURLFile')){
            $data = array('media' => new \CURLFile($filepath));
        } else {
            $data = array('media'=>'@'.$filepath);
        }
        $result = self::curlPost($url,$data);
        $resultArr = json_decode($result,true);
        if($resultArr['errcode']){
            exit($result);
        }
        return $resultArr['media_id'];
    }

    public static function curlGet($url = '', $options = array())
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        if (!empty($options)) {
            curl_setopt_array($ch, $options);
        }
        //https请求 不验证证书和host
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }
    public static function curlPost($url = '', $postData = '', $options = array())
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); //设置cURL允许执行的最长秒数
        if (!empty($options)) {
            curl_setopt_array($ch, $options);
        }
        //https请求 不验证证书和host
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $data = curl_exec($ch);
        if($data === false)
        {
            echo 'Curl error: ' . curl_error($ch);exit();
        }
        curl_close($ch);
        return $data;
    }

    private function replace($matchs){
        return iconv('UCS-2BE', 'UTF-8', pack('H4', $matchs[1]));
    }

    public static function xjson_encode($data)
    {
        if(version_compare(PHP_VERSION,'5.4.0','<')){
            $str = json_encode($data);
            $str = preg_replace_callback("#\\\u([0-9a-f]{4})#i", 'self::replace',$str);
            return $str;
        }
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
