<?php 
	/*
		http://maps.googleapis.com/maps/api/geocode/output?parameters
		其中，output 可以是以下值之一：
			json（建议）表示以 JavaScript 对象表示法 (JSON) 的形式输出
			xml 表示以 XML 的形式输出
		有些参数是必需的，有些是可选的。根据网址的标准，所有参数均使用字符 & (&) 分隔。下面枚举了这些参数及其可能的值。

		Google Geocoding API 使用以下网址参数定义地址解析请求：

			address（必需）- 您要进行地址解析的地址。*
		    或者
			latlng（必需）- 您希望获取的、距离最近的、可人工读取地址的纬度/经度文本值。*

			bounds（可选）- 要在其中更显著地偏移地址解析结果的可视区域的边框。（有关详细信息，请参见下文的可视区域偏向。）
			region（可选）- 区域代码，指定为 ccTLD（“顶级域”）双字符值。（有关详细信息，请参见下文的区域偏向。）
			language（可选）- 传回结果时所使用的语言。请参见支持的区域语言列表。请注意，我们会经常更新支持的语言，因此该列表可能并不详尽。如果未提供language，地址解析器将尝试尽可能使用发送请求的区域的本地语言。
			sensor（必需）- 指示地址解析请求是否来自装有位置传感器的设备。该值必须为 true 或 false。
		* 请注意：您可以传递 address 或 latlng 进行查找。（如果传递 latlng，则地址解析器执行反向地址解析。有关详细信息，请参阅反向地址解析。）
			bounds 和 region 参数只会影响地址解析器返回的结果，但不能对其进行完全限制。
	*/


	//数据库配置
	$db_config = array('host'=>'172.93.37.18','username'=>'root','password'=>'zhaofei','database'=>'lf_hotel');

	// 连接数据库
	$db =  mysqli_connect($db_config['host'],$db_config['username'],$db_config['password'],$db_config['database']) or exit('连接错误: '.mysqli_connect_error());

	// $query = "SELECT * FROM hotel where hierarchy='' and longitude!='' and latitude!='' and hotelid='1054'";
	$query = "SELECT * FROM hotel where longitude!='' and latitude!='' and hotelid='1054'";

	$result_data = mysqli_query($db, $query);
	$result_num = mysqli_num_rows($result_data);

	while($row = mysqli_fetch_assoc($result_data)){
		$result = google_map($row['latitude'],$row['longitude']);
		$sql = "update hotel set hierarchy='{$result}' where hotelid='".$row['hotelid']."'";
		$res = mysqli_query($db,$sql);
	}

    mysqli_free_result($result_data);  //释放结果集
	mysqli_close($db);



	// 谷歌 接口
	function google_map($latitude,$longitude){
		// 经纬度 错误
		if($latitude==''||$longitude==''){
			return 'lat,lng error';
		}

		// $result = doCurlGetRequest('http://maps.google.com/maps/api/geocode/json?latlng='.$latitude.','.$longitude.'&sensor=false&language=EN&',15);
		// $result = doCurlGetRequest('https://maps.google.com/maps/api/geocode/json?latlng=-20.269997,-70.129483&sensor=false&language=EN&key=AIzaSyDuCjkISaNhk4e8B_mkU17Qy0fCOe9W_8E',15);
		$result = doFileGetContent('https://maps.googleapis.com/maps/api/geocode/json?latlng='.$latitude.','.$longitude.'&key=AIzaSyCWrDZ432LmIZOwwbHnb2tOsLNmZPCDeDQ&language=EN&sensor=false');

		// url 错误
		if(!$result){
			return 'url error';
		}
		// 结果集 错误
		if($result['status']!='OK'){
			return 'url data error';
		}

		//处理数据
	 	$data = $result['results'];
	 	$address_com = $data['0']['address_components'];
	 	$political_id = array();
	 	$city_I = 0;

	 	// 把所有的place_id都取出来
	 	foreach($data as $i=>$index){
	 		$political_id[$index['types']['0']] = $index['place_id'];
	 	}

	 	// 找到最小的下标
	 	foreach($address_com as $i=>$index){
	 		if((in_array('locality',$index['types'])||in_array('neighborhood',$index['types']))&&in_array('political',$index['types'])){
	 			$city_I = $i;
	 		}
	 	}

	 	// 组层级数组
	 	foreach($address_com as $i=>$index){
			if($i>=$city_I&&in_array('political',$index['types'])){
				$CityMap[$i]['long_name'] = $index['long_name'];
				$CityMap[$i]['short_name'] = $index['short_name'];
				$CityMap[$i]['hierarchy'] = ($index['types']['0']=='political')?@$index['types']['1']:$index['types']['0'];
				$CityMap[$i]['place_id'] = (isset($political_id[$index['types']['0']]))?$political_id[$index['types']['0']]:'';
			}
	 	}

	 	return json_encode($CityMap);
	}	

	// file_get_contents
	function doFileGetContent($url){
		if($url == ""){
	 		return false;
	 	}

	 	$results = json_decode(file_get_contents($url),true);

	 	return $results;
	}

	// curl 方法
	function doCurlGetRequest($url,$timeout = 5){
	 	if($url == "" || $timeout <= 0){
	 		return false;
	 	}

 		$headers = array(
		    'User-Agent:Mozilla/5.0 (Windows; U; Windows NT 5.1; zh-CN; rv:1.9) Gecko/2008052906 Firefox/3.0',
		    'accept:text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
		    'accept-encoding:gzip, deflate, sdch',
		    'cache-control:max-age=0',
		    'upgrade-insecure-requests:1',
		    'x-client-data:CKG1yQEIhrbJAQiktskBCMS2yQEIqZ3KAQ==', 
		    'CLIENT-IP:202.103.229.40',
		    'X-FORWARDED-FOR:202.103.229.40',
		);
		
		// $url = 'http://www.baidu.com';
	  	$con = curl_init();
    	// 设置参数
    	curl_setopt($con, CURLOPT_URL, $url);
	 	curl_setopt($con, CURLOPT_HEADER, false);
	 	curl_setopt($con, CURLOPT_RETURNTRANSFER,true);
		curl_setopt($con, CURLOPT_SSL_VERIFYPEER, false);
	 	// curl_setopt($con, CURLOPT_TIMEOUT, (int)$timeout);
		curl_setopt($con, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($con, CURLOPT_FOLLOWLOCATION,1);
	
	 	$result = curl_exec($con);

		curl_close($con);

		return $result;
	}