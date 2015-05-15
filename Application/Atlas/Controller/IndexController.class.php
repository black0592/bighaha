<?php
// +----------------------------------------------------------------------
// | OneThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013 http://www.onethink.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: 麦当苗儿 <zuojiazi@vip.qq.com> <http://www.zjzit.cn>
// +----------------------------------------------------------------------
namespace Atlas\Controller;
use Think\Controller;
use Think\Log;
use Think\Hook;
use Atlas\Api\AtlasApi;
use Think\Exception;
use Common\Exception\ApiException;

/**
 * 前台首页控制器
 * 主要获取首页聚合数据
 */
class IndexController extends AtlasController {
	private $atlasModel;
	private $atlasApi;
	
	public function _initialize(){
		$this->atlasModel = D('Atlas');
		
		$this->atlasApi = new AtlasApi();
	}
	
	public function index($page = 1) {
	    //获取图集列表
		$map['status'] = 1;
		$page = intval($page);
		$atlas_list = $this->atlasModel->page($page, 10)->order('id desc')->select();
		$totalCount = $this->atlasModel->where($map)->count();
		$list_ids = getSubByKey($atlas_list, 'id');
		$atlas_list = $this->getAtlasByIds($list_ids);
		$this->assign('atlas_list', $atlas_list);
		$this->assign('totalCount', $totalCount);
		$this->display ();
	}
	
	/**
	 * 图集详情
	 * @param unknown $id
	 */
	function detail($id){
		$detail = $this->atlasApi->getAtlas($id);
		$this->assign('detail', $detail);
		$this->display();
	}
	
	/**
	 * 喜欢还是不喜欢
	 * 
	 */
	function dolike($id,$type){
	    $likeCountArray = $this->atlasApi->dolike($id,$type);
	    //返回成功结果
	    $this->ajaxReturn(apiToAjax($likeCountArray));
	}
	
	/**
	 * 测试2
	 */
	function test2(){
		$this->display();
	}
	
	/**
	 * 测试采集
	 * 
	 */
	function test(){
		
		$url = 'http://detail.tmall.com/item.htm?spm=a230r.1.14.21.H2Zthj&id=43553395005&cm_id=140105335569ed55e27b&abbucket=19';
		
		Vendor('Curl.Curl');
		require_once('ThinkPHP/Library/Vendor/Snoopy/Snoopy.class.php');
		require_once('ThinkPHP/Library/Vendor/simplehtmldom/simple_html_dom.php');
		
		//开始下载
		$curl = new \Curl\Curl();
		$snoopy = new \Snoopy;
		$snoopy->fetch($url); //获取所有内容
		$results = $snoopy->results;
		
		$html = str_get_html($results);
		//tb-detail-hd
		$detailHtml = $html->find('.tb-detail-hd',0);
		$priceHtml = $html->find('.tm-price');
		$imgHtml = $html->find("img#J_ImgBooth");
		
		$imgDivHtml = $html->find("#J_UlThumb",0);
		
		$h1 = $detailHtml->find("h1",0);
		
		$path = "taobao/";
		
		foreach ($imgDivHtml->find("li") as $l){
			$imgUrl = $l->find('img',0)->src;
			$imgUrl = str_replace('_60x60q90.jpg',' ',$imgUrl);
			$pathName = basename($imgUrl);
			$curl_down = $curl->download($imgUrl, $path.$pathName);
		}
		
		//preg_match_all("/TShop.Setup(?:\()(.*)(?:\))/i",$html, $result);	//取出括号里面的东西 字符串json
		preg_match_all("/TShop\.Setup\((.*?)\);/i",$html, $result);	//取出括号里面的东西 字符串json
		//preg_match_all('/TShop.Setup\(.\*?\);$/',$html,$result);
		
		//转成 数组
		$str = $result[1][0];
		$str = iconv('GB2312', 'UTF-8', $str);	//转编码格式
		$str = preg_replace('/,\s*([\]}])/m', '$1', $str);	//去掉多余逗号
		$detailJson = json_decode($str);
		
		//二次采集 //$url = 'http://dsc.taobaocdn.com/i3/430/530/43553395005/TB1TyZuHXXXXXcpXVXX8qtpFXXX.desc%7Cvar%5Edesc%3Bsign%5E574d73d0e390a342f1f03a8c7af5870b%3Blang%5Egbk%3Bt%5E1430489521';
		$descUrl = $detailJson->api->descUrl;
		$snoopy->fetch($descUrl); //获取所有内容
		$_itemResultsHtml = $snoopy->results;	//取出商品详情图片
		$itemResultsHtml = str_get_html($_itemResultsHtml);
		//图片下载
		foreach ($itemResultsHtml->find('img') as $i){
			$imgUrl = $i->src;
			//$imgUrl = str_replace('_60x60q90.jpg',' ',$imgUrl);
			$pathName = basename($imgUrl);
			$curl_down = $curl->download($imgUrl, $path.$pathName);
		}
		
		//print_r($imgHtml->src);
		//print_r($h1->outertext);
		/* print_r($imgHtml->src);
		echo"<br>";
		print_r($priceHtml->html); */
		print_r($results);
		die();
		/* $url = 'http://www.budejie.com/';
		$page_suffix = '{page}';
		$page_Count = 50;	//页码
		
		//保存Model
		
		
		//Vendor('Snoopy.Snoopy');
		require_once('ThinkPHP/Library/Vendor/Snoopy/Snoopy.class.php');
		require_once('ThinkPHP/Library/Vendor/simplehtmldom/simple_html_dom.php');
		
		$snoopy = new \Snoopy;
		$url = $url.$page_suffix;
		//循环读取页面
		for ($i = 1; $i<$page_Count; $i++){
			$siteUrl = str_replace('{page}',$i,$url);
			
			$snoopy->fetch($siteUrl); //获取所有内容
			$results = $snoopy->results;
			$html = str_get_html($results);
			foreach ($html->find('.web_left') as $webLeft){
				foreach ($webLeft->find('.post-body') as $postbody){
					$img = $postbody->find('img',0);
					$src = $img->src;
					$alt = $img->alt;
					$id = str_replace('pic-',' ',$img->id);
					//保存到数据库
					
					print_r($id);
					die();
				}
			}
			$html->clear();	//清理
		}*/
	}
	
	function getVarInjs($str,$patten,$withType = true)
	{
		$patten_js  = $withType?"'<\s*script[^>]*[^/]>(.*?)<\s*/\s*script\s*>'is":"'<\s*script\s*>(.*?)<\s*/\s*script\s*>'is";
		preg_match_all($patten_js, $str, $matches);
	
		foreach($matches[1] as $m)
		{
			//过滤取值
			preg_match($patten,$m,$result);
	
			if(!empty($result[1]))
				return $result[1];
		}
		return false;
	}
	
}