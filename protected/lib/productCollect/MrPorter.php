<?phpinclude_once('CCollect.php');/* * ******************************************************************************* * Copyright(C),2013, Glory * FileName: MrPorter.php * Author:  stephen * Version: v1.0 * Date:  14:07 2013-06-12 * Description:  MrPorter采集类 * ******************************************************************************** */class MrPorter extends CCollect {    const STATION = 'MRPORTER';    const HOME_URL = 'http://www.mrporter.com';    const COOKIE_URL = 'http://www.mrporter.com/intl/changecountry.mrp';    private $cookie_file = '/cookie/MrPorter.txt';    private $colorname;    private $alternative;    //构造函数    function __construct($trace = false, $bFindAlternativeProduct = false) {        $this->cookie_file = dirname(Yii::app()->basePath)  . $this->cookie_file;        parent::__construct($trace, $bFindAlternativeProduct);    }    //销毁函数    function __destruct() {        parent::__destruct();    }    public function init() {        $this->info['unit'] = 'GBP';        $this->station = self::STATION;        $this->citys[] = '英国';        $this->curl_cookie(self::HOME_URL, $this->cookie_file);        $param = 'selCountry=HK,intl,gbp,false&confirmSelection.y=11&confirmSelection.x=71';        $this->curl_post_cookie(self::COOKIE_URL, $this->cookie_file, $param);    }    private function getAlternativeProduct($city) {        if(is_array($this->alternative)){            $this->info['alternative'] = implode(';', $this->alternative);        }                 if($this->bFindAlternativeProduct && is_array($this->alternative)){             foreach ($this->alternative as $pid) {                $productaddr = self::HOME_URL . '/product/' .$pid;                 $product = new MrPorter();                 $pid = $product->productAnaly($productaddr, $city);                 unset($product);            }        }    }    private function noStock($html) {        $ret = $html->getElementById('#pp-add-to-bag');        return empty($ret);    }    //解析单个商品    public function productAnaly($url, $city = '') {        parent::productAnaly($url, $city);        if (!$this->getpid($url)) {            $this->trace('url 格式错误，无法解析.');            return false;        }        if (!$this->getInterHtmlDom($url, $this->cookie_file)) {            $this->trace('采集失败，无法获取页面信息');            return false;        }        if (!$this->noStock($this->dom)) {            $this->setProductInfo();        }                 $this->getAlternativeProduct($city);        return $this->saveInfo();    }    //采集单页面    public function collectSinglePage($url, $city = '') {        $ret = false;        try {            $html = $this->getHtmlDom($url, $this->cookie_file);            $links = $this->findProductLinks($html);            $this->trace("查找到商品数: " . strval(count($links)));            $html->clear();            unset($html);        } catch (Exception $e) {                    }        $this->saveProductLinks($links);        unset($links);        return true;    }    public function collectPage($url, $city = '') {        $this->trace('此网点不允许翻页处理.');    }    //得到页数 <span class="page-numbers">第3/10页</span>    public function getPageCount($url) {        $pageCount = 0;        $html = getHtmlDomAtCookie($url, $this->cookie_file);        if ($html == false)            return $pageCount;        $ret = $html->find('span.page-on');        if ($ret == null)            return $pageCount;        foreach ($ret as $key => $info) {            $reg = '/(\d{1,99}(\.\d+)?)/is';            preg_match_all($reg, $info->plaintext, $result);            if (is_array($result) && !empty($result) && !empty($result[1]) && !empty($result[1][1])) {                $pageCount = $result[1][1];                $this->trace('搜索到页数: ' . (string) $pageCount);                return $pageCount;            }            return $pageCount;        }    }    //得到当前页码    private function getcurrentPageNum($url) {        $array_query = parse_url($url);        $page = 1;        try {            if (isset($array_query['query'])) {                $item = explode('=', $array_query['query']);                $page = intval($item[1]);            }        } catch (Exception $e) {                    }        return $page;    }    //得到商品信息    public function getProductInfo($desc) {        if (array_key_exists($desc, $this->info))            return $this->info[$desc];        return false;    }    //搜索商品链接    private function findProductLinks($html) {        $temps = array();        $links = array();        //连接        foreach ($html->find('div[class=tall-product-image] a') as $key=>$element) {            if($key == self::MAX_P)  break;            $productaddr = self::HOME_URL . $element->href;            $temps[] = trim($productaddr);            $this->trace('搜索到商品链接:' . $productaddr);        }        $links = array_unique($temps);        unset($temps);        return $links;    }    //设置商品信息    private function setProductInfo() {        $nodes = $this->dom->find('#content');        foreach ($nodes as $html) {            $this->getBrand($html);            $this->getTitle($html);            $this->getPrice($html);            $this->getSwatches($html);            $this->getSizes($html);            $this->getImages($html);            $this->getDetailsAndDesigner($html);        }    }    //商品编号    private function getpid($url) {        $reg = '/(\d{1,99}(\.\d+)?)/is';        preg_match_all($reg, $url, $result);        if (is_array($result) && !empty($result) && !empty($result[1]) && !empty($result[1][0])) {            $this->info['pid'] = $result[1][0];            $this->info['id'] = self::STATION . $this->info['pid'];            $this->trace("商品ID:" . $this->info['pid']);            return true;        }        return false;    }    //尺寸描述    private function getSizeFitContainer($url) {        if (!$this->getProductInfo('pid'))            return;        if ($this->getProductInfo('sizes')) {            if ($this->info['sizes'] == 'one-size')                return;        }        $this->getOrgId($this->info['pid']);        $requrl = self::NET_A_PORTER_SIZECHART . $this->info['orgid'];        $reps = $this->curl_cookie_url($requrl);        if ($reps == false) {            return false;        }        $html = str_get_html($reps);        if ($html == false)            return false;        try {            $ret = $html->find('ul.content');            if (empty($ret))                return false;            foreach ($ret as $key => $info) {                $str = trim($info->outertext);                $this->info['sizeFitContainer'] = preg_replace("#<a[^>]*>(.*?)</a>#is", '', $str);                $this->trace('尺寸描述:' . $this->info['sizeFitContainer']);                return;            }        } catch (Exception $e) {                    }        unset($html);    }    //品牌    private function getBrand($html) {        $info = $html->find('#product-details h1', 0);        $this->info['brandName'] = trim($info->plaintext);        $this->trace('品牌:' . $this->info['brandName']);    }    //标题    private function getTitle($html) {        $info = $html->find('#product-details h4', 0);        $this->info['productTitle'] = trim($info->plaintext);        $this->trace('商品名:' . $this->info['productTitle']);    }    //价格    private function getPrice($html) {        $info = $html->find('span[class=price-value]', 0);        $stPrice = trim($info->plaintext);        $this->info['price'] = $this->analyPrice($stPrice);        $this->trace('现价:' . $this->info['price']);    }    //图片    private function getImages($html) {        $this->info['images'] = array();        $this->info['images']['0'] = array();        $find = $html->find('span[class=colour]');        foreach ($html->find('div[id=product-carousel] img') as $el) {            $imgAddr = $el->getAttribute('src');            $imgAddr = 'http:' . str_replace('xs', 'xl', $imgAddr);            if ($this->downImage($imgAddr)) {                $this->info['images']['0'][] = $imgAddr;                //               resizeJpg("product/" . $imgName, 336, 350);            }        }        $this->trace('图片:');        $this->trace($this->info['images']);    }    //颜色样本    private function getSwatches($html) {        $this->alternative = array();        $this->info['colors'] = array();        $info = $html->find('span[class=colour]', 0);        if (empty($info)) {            foreach ($html->find('#select-colour option') as $key=> $el) {                if($key== 0){                    $this->colorname = trim($el->plaintext);                    $colorobj = new stdclass;                    $colorobj->code = '0';                    $colorobj->image = null;                    $colorobj->name = $this->colorname;                    $this->info['colors'][] = $colorobj;                }else{                    $this->alternative[] = $el->getAttribute('value');                }            }        }else{            $this->colorname = trim($info->plaintext);            $colorobj = new stdclass;            $colorobj->code = '0';            $colorobj->image = null;            $colorobj->name = $this->colorname;            $this->info['colors'][] = $colorobj;        }    }    private function filterBracket($stText) {        $pos = strpos($stText, '(');        if ($pos != false)            $stText = substr($stText, 0, $pos);        $pos = strpos($stText, '-');        if ($pos != false)            $stText = substr($stText, 0, $pos);        return trim($stText);    }    private function filterStock($stClass) {        if (strcmp("greyed", $stClass) === 0)            return 0;        $stock = str_replace("max", '', $stClass);        return intval($stock);    }    //尺寸    private function getSizes($html) {        $this->info['stock'] = 0;        $this->info['sku'] = array();        $this->info['sizes'] = array();        foreach ($html->find('select[id=select-size] option') as $element) {            if (!isset($element->class{0}))                continue;            $sku = new stdClass;            $sku->color = $this->colorname;            $sku->size = $this->filterBracket($element->plaintext);            $sku->count = $this->filterStock($element->class);            $sizeobj = new stdClass;            $sizeobj->name = $sku->size;            $sizeobj->code = $sku->size;            $sizeobj->image = null;            $this->info['sizes'][] = $sizeobj;            $this->info['stock'] += $sku->count;            $this->info['sku'][] = $sku;        }        $this->trace('货存查询：' . $this->info['stock']);    }    //说明    private function getDetailsAndDesigner($html) {        $ret = $html->find('div[class=productContentPiece]');        if (empty($ret))            return;        foreach ($ret as $key => $info) {            if ($key == 0) {                $this->info['details'] = trim($info->innertext);                $this->trace('商品信息:' . $this->info['details']);            } else if ($key == 1) {                $this->info['sizeFitContainer'] = trim($info->innertext);                $this->trace('尺寸描述:' . $this->info['sizeFitContainer']);            } else if ($key == 2) {                $this->info['desc'] = trim($info->innertext);                $this->trace('产品信息:' . $this->info['desc']);                return;            }        }    }}?>