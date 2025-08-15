<?php

use xyhlibrary\tools\Crypt;
use xyhlibrary\tools\Data;
use xyhlibrary\tools\Http;
use think\Console;
use think\Db;
use think\db\Query;
use think\facade\Cache;


if (!function_exists('sprintfxh')) {
	/**
	 * sprintfxh 001
	 *
	 * @param string $str
	 *            
	 */
	function sprintfxh( string|int $str, $num=2) {
		return sprintf("%0".$num."s",$str);
	}
}

if (!function_exists('regularExtraction')) {
	/**
	 * 正则提取
	 *
	 * @param string $str
	 *            
	 */
	function regularExtraction($str = '', $start=':', $end='\||&') {
		//$str = 'a:1|b:2&d:645454&';
		//$start = ':'; // 开始
		//$end = '\||&'; // 结束
		preg_match_all('/'.$start.'(.*?)(?:'.$end.'|$)/', $str, $matches);
		return $matches[1]
	}

}

if (!function_exists('model')) {
	/**
	 * 实例化Model
	 *
	 * @param string $name
	 *            Model名称
	 */
	function model($table = '') {
		return new \library\Model($table);
	}
}

if (!function_exists('recurseCopy')) {
	/**
	 * 生成不重复的随机数
	 *
	 * @param unknown
	 * @return string
	 */
	function NoRand($begin = 0, $end = 20, $limit = 5) {
		$rand_array = range($begin, $end);
		shuffle($rand_array);                             //调用现成的数组随机排列函数
		$number_arr = array_slice($rand_array, 0, $limit);//截取前$limit个
		$number = '';
		foreach ($number_arr as $k => $v) {
			$number .= $v;
		}
		$number = trim($number);
		return $number;
	}
}



if (!function_exists('moneyFormat')) {
	function naturalTime($timestamp = 0) {
		$now = new DateTime('now');
		$duration = $now->diff(DateTime::createFromFormat("u", $timestamp));
		if ($duration->y > 0) {
			return $duration->y . "年前";
		} else if ($duration->m > 0) {
			return $duration->m . "月前";
		} else if ($duration->d > 0) {
			return $duration->d . "天前";
		} else if ($duration->h > 0) {
			return $duration->h . "小时前";
		} else if ($duration->i > 0) {
			return $duration->i . "分钟前";
		} else if ($duration->s > 0) {
			return $duration->s . "刚刚";
		}
	}
}

if (!function_exists('moneyFormat')) {
	//读取csv数据, 配合生成器使用
	function getCsvRow($file) {
		$handle = fopen($file, 'rb');
		if ($handle === false) {
			throw new \Exception();
		}
		
		while (feof($handle) === false) {
			yield fgetcsv($handle);
		}
		fclose($handle);
	}
}

if (!function_exists('moneyFormat')) {
	/**
	 * 通用处理金额的格式(主要用于业务)
	 * @param $money
	 */
	function moneyFormat($money) {
		$money = round(floor($money * 100) / 100, 2);
		return $money;
	}
}

if (!function_exists('recurseCopy')) {
	/**
	 * 复制拷贝
	 * @param string $src 原目录
	 * @param string $dst 复制到的目录
	 */
	function recurseCopy($src, $dst) {
		$dir = opendir($src);
		@mkdir($dst);
		while (false !== ($file = readdir($dir))) {
			if (($file != '.') && ($file != '..')) {
				if (is_dir($src . '/' . $file)) {
					recurseCopy($src . '/' . $file, $dst . '/' . $file);
				} else {
					copy($src . '/' . $file, $dst . '/' . $file);
				}
			}
		}
		closedir($dir);
	}
}

if (!function_exists('is_point_in_polygon')) {
	/**
	 * 判断一个坐标是否在一个多边形内（由多个坐标围成的）
	 * 基本思想是利用射线法，计算射线与多边形各边的交点，如果是偶数，则点在多边形外，否则
	 * 在多边形内。还会考虑一些特殊情况，如点在多边形顶点上，点在多边形边上等特殊情况。
	 * @param array $point 指定点坐标  $point=['longitude'=>121.427417,'latitude'=>31.20357];
	 * @param array $pts 多边形坐标 顺时针方向  $arr=[['longitude'=>121.23036,'latitude'=>31.218609],['longitude'=>121.233666,'latitude'=>31.210579].............];
	 */
	function is_point_in_polygon($point, $pts) {
		$N = count($pts);
		$boundOrVertex = true; //如果点位于多边形的顶点或边上，也算做点在多边形内，直接返回true
		$intersectCount = 0;   //cross points count of x
		$precision = 2e-10;    //浮点类型计算时候与0比较时候的容差
		$p1 = 0;               //neighbour bound vertices
		$p2 = 0;
		$p = $point; //测试点
		
		$p1 = $pts[0];//left vertex
		for ($i = 1; $i <= $N; ++$i) {//check all rays
			// dump($p1);
			if ($p['longitude'] == $p1['longitude'] && $p['latitude'] == $p1['latitude']) {
				return $boundOrVertex;//p is an vertex
			}
			
			$p2 = $pts[$i % $N];//right vertex
			if ($p['latitude'] < min($p1['latitude'], $p2['latitude']) || $p['latitude'] > max($p1['latitude'], $p2['latitude'])) {//ray is outside of our interests
				$p1 = $p2;
				continue;//next ray left point
			}
			
			if ($p['latitude'] > min($p1['latitude'], $p2['latitude']) && $p['latitude'] < max($p1['latitude'], $p2['latitude'])) {//ray is crossing over by the algorithm (common part of)
				if ($p['longitude'] <= max($p1['longitude'], $p2['longitude'])) {//x is before of ray
					if ($p1['latitude'] == $p2['latitude'] && $p['longitude'] >= min($p1['longitude'], $p2['longitude'])) {//overlies on a horizontal ray
						return $boundOrVertex;
					}
					
					if ($p1['longitude'] == $p2['longitude']) {//ray is vertical
						if ($p1['longitude'] == $p['longitude']) {//overlies on a vertical ray
							return $boundOrVertex;
						} else {//before ray
							++$intersectCount;
						}
					} else {                                                                                                                                           //cross point on the left side
						$xinters = ($p['latitude'] - $p1['latitude']) * ($p2['longitude'] - $p1['longitude']) / ($p2['latitude'] - $p1['latitude']) + $p1['longitude'];//cross point of lng
						if (abs($p['longitude'] - $xinters) < $precision) {//overlies on a ray
							return $boundOrVertex;
						}
						
						if ($p['longitude'] < $xinters) {//before ray
							++$intersectCount;
						}
					}
				}
			} else {//special case when ray is crossing through the vertex
				if ($p['latitude'] == $p2['latitude'] && $p['longitude'] <= $p2['longitude']) {//p crossing over p2
					$p3 = $pts[($i + 1) % $N];                                                 //next vertex
					if ($p['latitude'] >= min($p1['latitude'], $p3['latitude']) && $p['latitude'] <= max($p1['latitude'], $p3['latitude'])) { //p.latitude lies between p1.latitude & p3.latitude
						++$intersectCount;
					} else {
						$intersectCount += 2;
					}
				}
			}
			$p1 = $p2;//next ray left point
		}
		
		if ($intersectCount % 2 == 0) {//偶数在多边形外
			return false;
		} else { //奇数在多边形内
			return true;
		}
		
	}
}


if (!function_exists('getFileMap')) {
	/**
	 * 获取文件地图
	 * @param $path
	 * @param array $arr
	 * @return array
	 */
	function getFileMap($path, $arr = []) {
		if (is_dir($path)) {
			$dir = scandir($path);
			foreach ($dir as $file_path) {
				if ($file_path != '.' && $file_path != '..') {
					$temp_path = $path . '/' . $file_path;
					if (is_dir($temp_path)) {
						$arr[$temp_path] = $file_path;
						$arr = getFileMap($temp_path, $arr);
					} else {
						$arr[$temp_path] = $file_path;
					}
				}
			}
			return $arr;
		}
	}
}

if (!function_exists('getDistance')) {
	/**
	 * 计算两点之间的距离
	 * @param double $lng1 经度1
	 * @param double $lat1 纬度1
	 * @param double $lng2 经度2
	 * @param double $lat2 纬度2
	 * @param int $unit m，km
	 * @param int $decimal 位数
	 * @return float 米
	 */
	function getDistance($lng1, $lat1, $lng2, $lat2, $unit = 1, $decimal = 0) {
		$EARTH_RADIUS = 6370.996; // 地球半径系数
		$PI = 3.1415926535898;
		
		$radLat1 = $lat1 * $PI / 180.0;
		$radLat2 = $lat2 * $PI / 180.0;
		
		$radLng1 = $lng1 * $PI / 180.0;
		$radLng2 = $lng2 * $PI / 180.0;
		
		$a = $radLat1 - $radLat2;
		$b = $radLng1 - $radLng2;
		
		$distance = 2 * asin(sqrt(pow(sin($a / 2), 2) + cos($radLat1) * cos($radLat2) * pow(sin($b / 2), 2)));
		$distance = $distance * $EARTH_RADIUS * 1000;
		
		if ($unit === 2) {
			$distance /= 1000;
		}
		
		return round($distance, $decimal);
	}
}

if (!function_exists('data_batch_save')) {
    /**
     * 批量更新数据
     * @param Query|string $dbQuery 数据查询对象
     * @param array $data 需要更新的数据(二维数组)
     * @param string $key 条件主键限制
     * @param array $where 其它的where条件
     * @return boolean
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    function data_batch_save($dbQuery, $data, $key = 'id', $where = [])
    {
        return Data::batchSave($dbQuery, $data, $key, $where);
    }
}

if (!function_exists('encode')) {
    /**
     * 加密 UTF8 字符串
     * @param string $content
     * @return string
     */
    function encode($content)
    {
        return Crypt::encode($content);
    }
}

if (!function_exists('decode')) {
    /**
     * 解密 UTF8 字符串
     * @param string $content
     * @return string
     */
    function decode($content)
    {
        return Crypt::decode($content);
    }
}

if (!function_exists('http_get')) {
    /**
     * 以 get 模拟网络请求
     * @param string $url HTTP请求URL地址
     * @param array $query GET请求参数
     * @param array $options CURL参数
     * @return boolean|string
     */
    function http_get($url, $query = [], $options = [])
    {
        return Http::get($url, $query, $options);
    }
}

if (!function_exists('http_post')) {
    /**
     * 以 post 模拟网络请求
     * @param string $url HTTP请求URL地址
     * @param array $data POST请求数据
     * @param array $options CURL参数
     * @return boolean|string
     */
    function http_post($url, $data, $options = [])
    {
        return Http::post($url, $data, $options);
    }
}

if (!function_exists('buildTree')) {
	/**
	 * 构建树形结构
	 *
	 * @param array $items
	 * @param integer $parentId
	 * @return void
	 */
	function buildTree(array $items, $parentId = 0) {
		$tree = [];
		foreach ($items as $item) {
			if ($item['parent_id'] == $parentId) {
				$children = buildTree($items, $item['city_id']);
				if (!empty($children)) {
					$item['list'] = $children;
				}
				$tree[] = $item;
			}
		}
		return $tree;
	}
}


if (!function_exists('convertFormat')) {
	/**
	 * tree
	 *
	 * @param array $tree
	 * @return void
	 */
	function convertFormat(array $tree) {
		$result = [];
		foreach ($tree as $province) {
			$provinceNode = [
				'name' => $province['name'],
				'list' => []
			];
			if (isset($province['list'])) {
				foreach ($province['list'] as $city) {
					$cityNode = [
						'name' => $city['name']
					];
					if (isset($city['list'])) {
						$cityNode['list'] = array_column($city['list'], 'name');
					}
					$provinceNode['list'][] = $cityNode;
				}
			}
			$result[] = $provinceNode;
		}
		return $result;
	}
}


if (!function_exists('str_replacexh')) {
	/**
	 * 字符串或数组 替换
	 *
	 * @param array $old
	 * @param array $new
	 * @param array $arr
	 * @return void
	 */
	function str_replacexh($old = ["敏感词", "违禁词"]|"", $new = [4,5]|"***", $arr = []|"") {
		//是否数组
		if(is_array($arr)){
			$list = str_replace($old, $new, json_encode($arr, JSON_UNESCAPED_UNICODE));
			return json_decode($list, true);
		}else{
			return str_replace($old, $new, $arr);
		}
	}
}

if (!function_exists('array_kfv')) {
	/**
	 * 转换键为对应值
	 * $items = [1 => '快递',2 => '骑手',3 => '自提'];   $keys=[1,3]
	 */
	function array_kfv($items = [], $keys = []) {
		// 转换键为对应值
		$type_name = array_map(function($key) use ($items) {
			return $items[$key] ?? $key; // 如果键不存在则保留原值
		}, $keys);
		return $type_name;
	}
}

if (!function_exists('object_to_array')) {
	/**
	 * 对象转化为数组
	 * 不是对象和数组，不做处理
	 * @param object $obj
	 */
	function object_to_array($obj='{}') {
		if (is_object($obj)) {
			$obj = json_encode($obj);
			$obj = json_decode($obj, true);
		}
		if (is_array($obj)) {
			foreach ($obj as $key => $value) {
				$obj[$key] = object_to_array($value);
			}
		}
		return $obj;
	}
}

if (!function_exists('isNumericString')) {
	/**
	 * 匹配整数或浮点数
	 *
	 * @param [type] $str
	 * @return boolean
	 */
	function isNumericString($str) {
		// 匹配整数或浮点数（含正负号）
		return preg_match('/^[+-]?(\d+\.?\d*|\.\d+)$/', $str) && is_numeric($str);
	}
}

if (!function_exists('array_vstr')) {
	/*
	*  数字和字符串，类型比较 0，0.00，  1，1.00
	*  float int string
	*  array_tostr  0.00 0 '0.00' 1.00 1  23.20 23.2
	*/
	function array_vstr ($arr){
		foreach ($arr as $key => $value) {
			if(is_array($value)){
				$arr[$key] = array_vstr($value);
			}else{
				if($value===0){
					$arr[$key] = '0';
					continue;
				}
				// 数字转，字符串
				if($value=='' || $value==null || $value=='null' || preg_match('/[\x{4e00}-\x{9fa5}]/u', $value)){
					$arr[$key] = $value;
					// null 和 null 字符串
					if($value==null || strtolower($value)=='null' ){
						$arr[$key] = null;
					}
				}else{
					// 匹配整数  或  浮点数
					if(isNumericString($value) || $value=='0'){
						$arr[$key] = sprintf('%.11g', $value);
					}else{
						$arr[$key] = $value;
					}
				}
			}
		}
		return $arr;
	}
}


if (!function_exists('array_diff_assocxh')) {
	/*
	*  (关联数组) 前面一个数组减去后面一个数组，去除相同部分，保留前一个数组
	*  array_diff[1,2,3], [3,2,5]）
	*/
	function array_diff_assocxh ($arr1, $arr2){
		$arrValue = [];
		if(is_array($arr1) && is_array($arr2)){
			$arrValue = array_diff_assoc(array_vstr($arr1), array_vstr($arr2));
		}else{
			if(is_array($arr1) && !is_array($arr2)){
				$arrValue = $arr1;
			}
		}
		return $arrValue;
	}
}


if (!function_exists('array_diffxh')) {
	/*
	*  （普通数组）前面一个数组减去后面一个数组，去除相同部分，保留前一个数组
	*  array_diff[1,2,3], [3,2,5]）
	*/
	function array_diffxh ($arr1, $arr2){
		$arrValue = [];
		if(is_array($arr1) && is_array($arr2)){
			$arrValue = array_diff(array_vstr($arr1), array_vstr($arr2));
		}else{
			if(is_array($arr1) && !is_array($arr2)){
				$arrValue = $arr1;
			}
		}
		return $arrValue;
	}
}

if (!function_exists('array_countxh')) {
	/*
	*  二维关联数组，等于某个值, 个数
	*   array_countxh [['id'=>1,'name'=>'abc']], 'id'=>1 ）
	*/
	function array_countxh ($arr, $where = []){
		if(is_array($arr)){
			return array_sum(
				array_map(function($val) use($where) {
					//是否有条件
					if(empty($where)){
						return 1;
					}else{
						$isok = true;
						foreach ($where as $k => $v) {
							//只要一个条件、不满足， 统一失败
							if(isset($val[$k]) && $val[$k] != $v){
								$isok = false;
								break;
							}
						}
						if( $isok ){
							return 1;
						}else{
							return 0;
						}
					}
				}, $arr));
		}else{
			return 0;
		}
	}
}

if (!function_exists('array_columnxh')) {
	/*
	*  二维关联数组，提取某个id值
	*   array_column（[['id'=>1,'name'=>'abc']], 'id'）
	*/
	function array_columnxh ($arr, $key){
		$arrValue = [];
		if(is_array($arr)){
			$arrValue = array_column($arr, $key);
			if(!is_array($arrValue)){
				$arrValue = [];
			}
		}
		return $arrValue;
	}
}

if (!function_exists('array_sumxh')) {
	/*
	*  判断是否空，isset empty
	*/
	function array_sumxh ($arr, $key='', $where = []){
		if(empty($key) && is_array($arr)){
			return bcadd(array_sum($arr),0,2);
		}
		$array_sum = array_sum(
			array_map(function($val) use($key, $where) {
				//是否有条件
				if(empty($where)){
					if(!emptyxh($val[$key])){
						return $val[$key];
					}else{
						return 0;
					}
				}else{
					$isok = true;
					foreach ($where as $k => $v) {
						//不满足条件，失败
						if(isset($val[$k]) && $val[$k] != $v){
							$isok = false;
							break;
						}
					}
					if($isok && isset($val[$key])){
						return $val[$key];
					}else{
						return 0;
					}
				}
			}, $arr));
		return bcadd($array_sum, 0, 2);
	}
}

if (!function_exists('implodexh')) {
	/**
	 * implodexh
	 *
	 * @param string $divide
	 * @param array $arr
	 * @return void
	 */
	function implodexh ($divide="|", $arr=[]){
		if(is_array($arr)){
			return implode($divide, $arr);
		}
		$str = "";
		return $str;
	}
}

if (!function_exists('explodexh')) {
	/**
	* explodexh [',\|', '1,2,3' , 1] = 2
	*
	*/
	function explodexh ($divide="|", $str="", $num=null){
		if(empty($str)){
			return [];
		}
		$arr = preg_split('/['.$divide.']/', $str);
		// $arr = explode($divide, $str);
		if(!is_null($num) && isset($arr[$num])){
			$arr = $arr[$num];
		}
		return $arr;
	}
}

if (!function_exists('array_filterxh')) {
	/**
	*  过滤null , 空字符，条件查询   新增不能用
	*/
	function array_filterxh($array = [], $filter = [null, '']){
		return array_filter($array, function($value) use ($filter) {
			$str = true;
			foreach ($filter as $v) {
				//字符串特殊处理
				if(is_string($value) && trim($value) == ''){
					$str  = false;
					break;
				}
				if($value == $v){
					$str  = false;
					break;
				}
			}
			return $str;
			// return $value !== null && $value !== '' && trim($value) !== '';
		});
	}
}

if (!function_exists('emptyxh')) {
	/**
	*  判断是否空，
	* true:  [] , 0, 0.00, null ,' ', false
	* false '0.00', '0', 1, true
	*/
	function emptyxh($arr = []|'', $key='', $rule = [null, '', 0, [], false] ){
		if(is_array($arr)){
			// 数组，不取key
			if(empty($key)){
				if(empty($arr)){
					return true;
				}else{
					return false;
				}
			}
			if(isset($arr[$key])){
				if($arr[$key] === '0'){
					return false;
				}
				if(empty($arr[$key])){
					return true;
				}
				
				return false;
			}else{
				return true;
			}
		}else{
			if(is_string($arr)){
				$arr = trim($arr);
			}
			if($arr === '0'){
				return false;
			}
			// '' 0 '0' null false []
			if(empty($arr)){
				return true;
			}
			return false;
		}
	}
}

if (!function_exists('getVal')) {
	/**
	 * 处理、获取参数
	 * [key=>['name'=>245]]
	 * 
	 * @param array $arr
	 * @param string $key
	 * @param string $set_str
	 * @return void
	 */
	function getVal($arr=[], $key='', $set_str=''|0){
		if(isset($arr[$key])){
			if($arr[$key] == '0'){
				return 0;
			}
			if(empty($arr[$key])){
				return $set_str;
			}
			return $arr[$key];
		}else{
			return $set_str;
		}
	}
}


if (!function_exists('list_to_tree')) {
	/**
	 * 把返回的数据集转换成Tree
	*
	* @param [type] $list
	* @param string $pk
	* @param string $pid
	* @param string $child
	* @param integer $root
	* @return void
	*/
	function list_to_tree($list, $pk = 'id', $pid = 'pid', $child = '_child', $root = 0) {
		// 创建Tree
		$tree = [];
		if (!is_array($list)) :
			return false;

		endif;
		// 创建基于主键的数组引用
		$refer = [];
		foreach ($list as $key => $data) {
			$refer[$data[$pk]] = &$list[$key];
			$refer[$data[$pk]][$child] = [];
			$refer[$data[$pk]]['child_num'] = 0;
		}
		foreach ($refer as $key => $data) {
			// 判断是否存在parent
			$parentId = $data[$pid];
			if ($root == $parentId) {
				$tree[] = &$refer[$key];
				// $tree[$key] = &$refer[$key];
			} else if (isset($refer[$parentId])) {
				is_object($refer[$parentId]) && $refer[$parentId] = $refer[$parentId]->toArray();
				$parent = &$refer[$parentId];
				$parent[$child][] = &$refer[$key];
				// $parent[$child][$key] = &$refer[$key];
				$parent['child_num']++;
			}
		}
		return $tree;
	}
}


// 注册系统常用指令
if (class_exists('think\Console')) {
    Console::addDefaultCommands([
        // 注册清理无效会话
        'xyhlibrary\command\Sess',
    ]);
}
