<?php
error_reporting(1);
header('Content-type:text/html; Charset=utf-8');
/* 配置开始 */
$appid = '';  //微信公众平台->开发->基本配置->AppID
$appKey = '';   //微信公众平台->开发->基本配置->AppSecret
$title = '微信分享测试';						//微信分享显示的标题
$desc = '微信分享测试，这里显示的是描述文字';	//微信分享显示的文字描述
$img = 'https://www.dedemao.com/uploads/allimg/1709/03/2-1FZ3144P7-m.jpg';	//微信分享显示的缩略图
/* 配置结束 */
$action = isset($_GET['action']) ? $_GET['action'] : '';
if($action == 'getConfig'){
	$wxService = new WxService($appid,$appKey);
	$url = $_GET['url'];
	$config = $wxService->getShareConfig($url);
	echo json_encode($config);exit();
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<title>微信分享DEMO</title>
	<meta name="viewport" content="width=device-width,initial-scale=1,minimum-scale=1,maximum-scale=1,user-scalable=no" />
</head>
<body>
<h1>微信分享DEMO</h1>
<script src='https://cdn.bootcss.com/jquery/1.11.3/jquery.min.js'></script>
<script src="https://res.wx.qq.com/open/js/jweixin-1.2.0.js"></script>
	<script>
   timestamp = '';
   noncestr = '';
   signature = '';
   $(function(){
      $.ajaxSettings.async = false;  //重要：开启同步
      var url = window.location.href.split('#')[0];
      //请求第二步添加的php文件获取timestamp、noncestr、signature等信息
      $.getJSON("<?=$_SERVER['QUERY_STRING']?$_SERVER['REQUEST_URI'].'&':$_SERVER['REQUEST_URI'].'?'?>action=getConfig",{url:url},function(data){
		 if (data.errmsg){
            alert('微信分享配置错误：'+data.errmsg);
         }
         timestamp = data.timestamp;
         noncestr = data.noncestr;
         signature = data.signature;
      });
      wx.config({
      debug: false,
      appId: '<?=$appid?>',   //填写你的appid
      timestamp: timestamp,
      nonceStr: noncestr,
      signature: signature,
      jsApiList: [
         'checkJsApi',
         'onMenuShareTimeline',
         'onMenuShareAppMessage',
         'onMenuShareQQ',
         'onMenuShareWeibo',
         'onMenuShareQZone'
      ]
   });
   wx.ready(function (){
      //获取“分享给朋友”按钮点击状态及自定义分享内容接口
	  url = window.location.href.split('#')[0];
      wx.onMenuShareAppMessage({
         title: '<?=$title?>',
         desc: "<?=$desc?>",
         link: url,
         imgUrl: '<?=$img?>',
         trigger: function (res) {
         },
         success: function (res) {
         },
         cancel: function (res) {
         },
         fail: function (res) {
         }
      });
      //获取“分享到朋友圈”按钮点击状态及自定义分享内容接口
      wx.onMenuShareTimeline({
         title: '<?=$title?>',
         desc: "<?=$desc?>",
         link: url,
         imgUrl: '<?=$img?>',
         success: function () {
            // 用户确认分享后执行的回调函数
         },
         cancel: function () {
            // 用户取消分享后执行的回调函数
         }
      });
   });
   });
   
</script>
</body>
</html>
<?php
class WxService
{
    protected $appid;
    protected $appKey;
    public $token = null;
    public function __construct($appid, $appKey)
    {
        $this->appid = $appid; 
        $this->appKey = $appKey;
		$this->token = $this->wx_get_token();
    }
   
	//获取微信公从号access_token
	public function wx_get_token() {		
		$url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$this->appid.'&secret='.$this->appKey;
		$res = self::curlGet($url);
		$res = json_decode($res, true);
		if($res['errmsg']){
			echo json_encode($res);exit();
		}
		//这里应该把access_token缓存起来，有效期是7200s
		return $res['access_token'];
	}
	//获取微信公从号ticket
	public function wx_get_jsapi_ticket() {
		$url = sprintf("https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token=%s&type=jsapi", $this->token);
		$res = self::curlGet($url);
		$res = json_decode($res, true);
		//这里应该把ticket缓存起来，有效期是7200s
		return $res['ticket'];
	}

	public function getShareConfig($url) {
		$wx = array();
		//生成签名的时间戳
		$wx['timestamp'] = time();
		//生成签名的随机串
		$wx['noncestr'] = uniqid();
		//jsapi_ticket是公众号用于调用微信JS接口的临时票据。正常情况下，jsapi_ticket的有效期为7200秒，通过access_token来获取。
		$wx['jsapi_ticket'] = $this->wx_get_jsapi_ticket();
		//分享的地址
		$wx['url'] = $url;
		$string = sprintf("jsapi_ticket=%s&noncestr=%s&timestamp=%s&url=%s", $wx['jsapi_ticket'], $wx['noncestr'], $wx['timestamp'], $wx['url']);
		//生成签名
		$wx['signature'] = sha1($string);
		return $wx;
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
}
?>