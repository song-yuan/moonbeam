<?phpinclude_once('CCollect.php');/* * ******************************************************************************* * Copyright(C),2013, Glory * FileName: NetAPorter.php * Author:  stephen * Version: v1.0 * Date:  14:07 2013-06-12 * Description:  NetAPorter采集类 * ******************************************************************************** */class Swarovski extends CCollect {    const STATION = 'SWAROVSKI';    const HOME_URL = 'http://www.swarovski.com';    const JSONResult = 'http://www.swarovski.com/is-bin/INTERSHOP.enfinity/WFS/SCO-Web_HK-Site/zh_TW/-/HKD/ViewParametricSearchBySearchIndex-GetJSONResult?SearchParameter=%26%40';    private $alternativeProductArry;    //构造函数    function __construct($trace = false, $bFindAlternativeProduct = false) {        parent::__construct($trace, $bFindAlternativeProduct);    }    //销毁函数    function __destruct() {        parent::__destruct();    }    public function init() {        $this->info['unit'] = 'HKD';        $this->station = self::STATION;        $this->citys[] = '香港';    }    private function noStock() {        $ret = $this->dom->find('.wishlist');        return empty($ret);    }    private function getAlternativeProduct($html) {        $ret = $html->find('#variation option');        if ($ret == null || empty($ret)) {            return;        }        foreach ($ret as $element) {            $id = trim($element->getAttribute('value'));            if ($id == $this->oid || $id == '0')                continue;            $this->alternativeProductArry[] = self::SIGN . trim($id);        }        if (count($this->alternativeProductArry) > 0) {            $this->info['alternative'] = implode("|", $this->alternativeProductArry);            $this->trace('关联商品:' . $this->info['alternative']);        }    }    //解析单个商品    public function productAnaly($url, $city = '') {        parent::productAnaly($url, $city);        $html = $this->getInterHtmlDom($url);        if ($html === false) {            $this->trace('无法采集网页');            return false;        }        if(!$this->getpid()){            $this->trace('无法采集商品ID');            return false;        }        if (!$this->noStock()) {            $this->setProductInfo();        }        return $this->saveInfo();    }    //采集单页面    public function collectSinglePage($url, $city = '') {        $links = null;        try {            $html = $this->getHtmlDom($url);            $links = $this->findProductLinks($html);            $this->trace("查找到商品数: " . strval(count($links)));            $html->clear();            unset($html);        } catch (Exception $e) {        }        $this->saveProductLinks($links);        unset($links);        return true;    }    public function collectPage($url, $city = '') {        $this->trace('此网点不允许翻页处理.');    }    private function getPageUrl($url) {        $links = array();        $html = getHtmlDom($url);        if ($html === false)            return $links;        //得到当前页码        $currPageNo = '';        foreach ($html->find('span[class=active]') as $element) {            $currPageNo = trim($element->plaintext);            $this->trace('当前页=' . $currPageNo);            break;        }        $param = '';        $pageSum = 0;        foreach ($html->find('ul[class=linkList] a') as $element) {            if (!isset($param{0}))                $param = $element->getAttribute('data-queryterm');            $pageSum++;        }        if (!isset($param{0}))            return $links;        for ($i = $currPageNo; $i <= $pageSum; $i++) {            $paramCode = $param . '&@Page=' . $i;            $paramCode = rawurlencode($paramCode);            $link = self::JSONResult . $paramCode . '&PageSize=12&View=M';            $json = curl_url($link);            if ($json) {                $json = stripslashes($json);                $objs = json_decode($json);                foreach ($objs->SearchResult->Products as $Product) {                    $links[] = $Product->DetailPage;                }            }        }        $this->trace('搜索到商品：' . strval(count($links)));        return $links;    }    //得到商品信息    public function getProductInfo($desc) {        if (array_key_exists($desc, $this->info))            return $this->info[$desc];        return false;    }    //搜索商品链接    private function findProductLinks($html) {        $temps = array();        $links = array();        //连接        foreach ($html->find('.bubble-handle') as $element) {            $productaddr = $element->href;            $temps[] = trim($productaddr);            $this->trace('搜索到商品链接:' . self::HOME_URL . $productaddr);        }        $links = array_unique($temps);        unset($temps);        return $links;    }    //设置商品信息    private function setProductInfo() {        $nodes = $this->dom->find('#productdetailpage');        foreach ($nodes as $html) {            $this->getBrand();            $this->getTitle($html);            $this->getPrice($html);            $this->getSwatches($html);            $this->getSizes($html);            $this->getImages($html);            $this->getDetailsAndDesigner($html);        }    }    //商品编号    private function getpid() {        $element = $this->dom->find('.article-no', 0);        if(empty($element))            return false;        $this->info['pid'] = $this->analyPrice($element->plaintext);        $this->info['id'] = self::STATION . $this->info['pid'];        $this->trace("商品PID:" . $this->info['pid']);        return true;    }    //尺寸描述    private function getSizeFitContainer($url) {        if (!$this->getProductInfo('pid'))            return;        if ($this->getProductInfo('sizes')) {            if ($this->info['sizes'] == 'one-size')                return;        }        $this->getOrgId($this->info['pid']);        $requrl = self::NET_A_PORTER_SIZECHART . $this->info['orgid'];        $reps = $this->curl_cookie_url($requrl);        if ($reps == false) {            return false;        }        $html = str_get_html($reps);        if ($html == false)            return false;        try {            $ret = $html->find('ul.content');            if (empty($ret))                return false;            foreach ($ret as $key => $info) {                $str = trim($info->outertext);                $this->info['sizeFitContainer'] = preg_replace("#<a[^>]*>(.*?)</a>#is", '', $str);                $this->trace('尺寸描述:' . $this->info['sizeFitContainer']);                return;            }        } catch (Exception $e) {                    }        unset($html);    }    //品牌    private function getBrand() {        $this->info['brandName'] = '施洛华世奇';        $this->trace('品牌:' . $this->info['brandName']);    }    //标题    private function getTitle($html) {        $element = $html->find('.product-info h1', 0);        $this->info['productTitle'] = trim($element->plaintext);        $this->trace('商品名:' . $this->info['productTitle']);    }    //原始价格    private function getOriginalRetailPrice($html) {            }    //价格    private function getPrice($html) {        $element = $html->find('.product-info .price', 0);        $this->info['price'] = $this->analyPrice($element->plaintext);        $this->trace('现价:' . $this->info['price']);    }    //图片    private function getImages($html) {        $this->info['images'] = array();        $this->info['images']['0'] = array();        foreach ($html->find('ul[class=prod-altviews clearfix] img') as $element) {            $zoom = $element->getAttribute('data-zoomifyimg');            $imgAddr = $element->getAttribute('data-simpleimg');            if (isset($zoom{0})) {                $zoom = str_replace('BestView', '600', $zoom);                $zoom = substr($zoom, 0, strlen($zoom) - 1);                $imgAddr = $zoom . "W600.jpg";            } else {                $imgAddr = self::HOME_URL . $imgAddr;            }            if ($this->downImage($imgAddr))                $this->info['images']['0'][] = $imgAddr;        }        $this->trace('图片:');        $this->trace($this->info['images']);    }    //颜色样本    private function getSwatches() {        $this->info['colors'] = array();        $colorobj = new stdclass;        $colorobj->code = '0';        $colorobj->image = null;        $colorobj->name = '图片色';        $this->info['colors'][] = $colorobj;    }    private function getOrgId($id) {        $orgid = trim($id, self::SIGN);        $this->info['orgid'] = $orgid;        return $orgid;    }    //尺寸    private function getSizes($html) {        $this->info['stock'] = 5;        $this->info['sku'] = array();        $this->info['sizes'] = array();        $sizeobj = new stdClass;        $sizeobj->name = '均码';        $sizeobj->code = '0';        $sizeobj->image = null;        $sku = new stdClass;        $sku->count = 5;        $sku->color = '图片色';        $sku->size = '均码';        $ret = $html->find('#variation option');        if (empty($ret)) {            $this->info['sizes'][] = $sizeobj;            $this->info['sku'][] = $sku;            return;        }        foreach ($ret as $element) {            $id = trim($element->getAttribute('value'));            if ($id == $this->oid) {                $sizeobj->name = trim($element->plaintext);                $sku->size = '均码';                break;            }        }        $this->info['sizes'][] = $sizeobj;        $this->info['sku'][] = $sku;        $this->trace('尺寸:');        $this->trace($this->info['sizes']);        $this->trace($this->info['sku']);    }    //说明    private function getDetailsAndDesigner($html) {        $element = $html->find('.moredetails', 0);        if (!empty($element)) {            $this->info['desc'] = trim($element->plaintext);            $this->trace('商品信息:' . $this->info['desc']);        }        $element = $html->find('.article-size', 0);        if (!empty($element)) {            $this->info['sizeFitContainer'] = trim($element->plaintext);            $this->trace('尺寸描述:' . $this->info['sizeFitContainer']);        }    }}?>