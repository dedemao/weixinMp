<?php
header('Content-type:text/html; Charset=utf-8');
$appid='xxxxx';
$appsecret='xxxxx';
$wx = new WxService($appid,$appsecret);

$data[0]['name'] = array('菜单1','#');
$data[1]['name'] = array('菜单2','#');
$data[2]['name'] = array('菜单3','#');

$data[0]['sub_button'][0] = array('菜单1-1','http://www.baidu.com');
$data[0]['sub_button'][1] = array('菜单1-2','http://www.baidu.com');

$data[1]['sub_button'][0] = array('菜单2-1','http://www.baidu.com');
$data[1]['sub_button'][1] = array('菜单2-2','http://www.baidu.com');

$data[2]['sub_button'][0] = array('菜单3-1','http://www.baidu.com');
$data[2]['sub_button'][1] = array('菜单3-2','http://www.baidu.com');

$result = $wx->menuCreate($data);

if($result['errcode']==0){
    echo '<h1>创建菜单成功！</h1>';
}else{
    echo '<h1>创建菜单失败：'.$result['errmsg'].'</h1>';
}

class WxService
{
    protected $appid;
    protected $appsecret;
    protected $templateId;
    protected $token = null;
    public $data = null;
    public function __construct($appid, $appsecret)
    {
        $this->appid = $appid;
        $this->appsecret = $appsecret;
        $this->token = $this->getToken();
    }

    public function menuCreate($data)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/menu/create?access_token='.$this->getToken();
        $menu = array();
        $i=0;
        foreach ($data as $item){
            $menu['button'][$i]['name'] = $item['name'][0];
            if($item['sub_button']){
                $j=0;
                foreach ($item['sub_button'] as $sub){
                    $menu['button'][$i]['sub_button'][$j]['type'] = 'view';
                    $menu['button'][$i]['sub_button'][$j]['name'] = $sub[0];
                    $menu['button'][$i]['sub_button'][$j]['url'] = $sub[1];
                    $j++;
                }
            }else{
                $menu['button'][$i]['type'] = 'view';
                $menu['button'][$i]['url'] = $item['name'][1];
            }
            $i++;
        }

        $data = self::xjson_encode($menu);
        $data = str_replace('\/','/',$data);
        $result = self::curlPost($url,$data);
        return json_decode($result,true);
    }


    function getToken() {
        if($this->token) return $this->token;
        $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$this->appid.'&secret='.$this->appsecret;
        $res = self::curlGet($url);
        $result = json_decode($res, true);
        if($result['errmsg']){
            echo $res;exit();
        }
        return $result['access_token'];
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

    public static function xjson_encode($data)
    {
        if(version_compare(PHP_VERSION,'5.4.0','<')){
            $str = json_encode($data);
            $str = preg_replace_callback("#\\\u([0-9a-f]{4})#i",function($matchs){
                return iconv('UCS-2BE', 'UTF-8', pack('H4', $matchs[1]));
            },$str);
            return $str;
        }
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
?>