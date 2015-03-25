<?phpinclude_once('CCollect.php');/* * ******************************************************************************* * Copyright(C),2013, Glory * FileName: NetAPorter.php * Author:  stephen * Version: v1.0 * Date:  14:07 2014-03-12 * Description:  Mytheresa采集类 * ******************************************************************************** */class Mytheresa extends CCollect {    const STATION = 'MYTHERESA';    const HOME_URL = 'http://www.mytheresa.com/';    const COOKIE_URL = 'http://www.mytheresa.com/';    const AJAX_URL = 'http://www.mytheresa.com/en-hk/bundleorder/index/index/';    private $cookie_file = '/cookie/Mytheresa.txt';    private $skuId;    //构造函数    function __construct($trace = false, $bFindAlternativeProduct = false) {        $this->cookie_file = dirname(Yii::app()->basePath)  . $this->cookie_file;        parent::__construct($trace, $bFindAlternativeProduct);    }        //销毁函数    function __destruct() {        parent::__destruct();    }    public function init() {        $this->info['unit'] = 'HKD';        $this->station = self::STATION;        $this->citys[] = '德国';        $this->curl_cookie(self::HOME_URL, $this->cookie_file);    }    //内部编号    private function getInId($html) {        foreach ($html->find('input[name=product]') as $info) {            $this->inId = $info->getAttribute('value');            $this->trace("IID:" . $this->inId);            return true;        }        return false;    }    private function getAlternativeProduct($html) {        foreach ($html->find('#alternative-colors a') as $element) {            $productid = str_replace("/product/", '', $element->href);            $this->alternativeProductArry[] = self::SIGN . $productid;        }        if (count($this->alternativeProductArry) > 0) {            $this->info['alternative'] = implode("|", $this->alternativeProductArry);            $this->trace('关联商品:' . $this->info['alternative']);        }    }    private function postAjax() {        $curlPost = 'sku=' . urlencode($this->info['pid']);        $reps = $this->curl_post_cookie(self::AJAX_URL, $this->cookie_file, $curlPost);        if ($reps !== false) {            @$html = json_decode($reps);            //$this->trace(@$html);            $this->getDomFromContent($html->content);            return str_get_html($html->content);        }        return false;    }    private function ajaxAnaly() {        $html = $this->postAjax();        if ($html === false) {            $this->trace('ajaxAnaly失败');            return 0;        }        foreach ($html->find('#prod-' . $this->skuId, 0)->children() as $children) {            if (strcmp($children->getAttribute('class'), 'designer-name') == 0) {                $this->getBrand($children);            } elseif (strcmp($children->getAttribute('class'), 'product-name') == 0) {                $this->getTitle($children);            } elseif (strcmp($children->getAttribute('class'), 'price-box') == 0) {                $this->getPrice($children);            } elseif (strpos($children->getAttribute('class'), 'add-to-box') !== false) {                $this->getSizes($children);                break;            }            continue;        }        $this->trace('货存：' . $this->info['stock']);        $this->trace('尺寸：');        $this->trace($this->info['sizes']);        return $this->info['stock'];    }    //解析单个商品    public function productAnaly($url, $city = '') {        parent::productAnaly($url, $city);        if (!$this->getInterHtmlDom($url, $this->cookie_file)) {            $this->trace('采集失败，无法获取页面信息');            return false;        }        $this->getpid($this->dom);        if ($this->ajaxAnaly() != 0) {            $this->setProductInfo();        }        return $this->saveInfo();    }    //采集单页面    public function collectSinglePage($url, $city = '') {        try {            $html = $this->getHtmlDom($url, $this->cookie_file);            $links = $this->findProductLinks($html);            $this->trace("查找到商品数: " . strval(count($links)));            $html->clear();            unset($html);        } catch (Exception $e) {        }                 $this->saveProductLinks($links);        unset($links);        return true;    }    public function collectPage($url, $city = '') {        $this->trace('此网点不允许翻页处理.');    }    //得到页数 <span class="page-numbers">第3/10页</span>    public function getPageCount($url) {        $pageCount = 0;        $html = getHtmlDomAtCookie($url, $this->cookie_file);        if ($html == false)            return $pageCount;        $ret = $html->find('span.page-on');        if ($ret == null)            return $pageCount;        foreach ($ret as $info) {            $result = NULL;            $reg = '/(\d{1,99}(\.\d+)?)/is';            preg_match_all($reg, $info->plaintext, $result);            if (is_array($result) && !empty($result) && !empty($result[1]) && !empty($result[1][1])) {                $pageCount = $result[1][1];                $this->trace('搜索到页数: ' . (string) $pageCount);                return $pageCount;            }            return $pageCount;        }    }    //得到当前页码    private function getcurrentPageNum($url) {        $array_query = parse_url($url);        $page = 1;        try {            if (isset($array_query['query'])) {                $item = explode('=', $array_query['query']);                $page = intval($item[1]);            }        } catch (Exception $e) {            print $e->getMessage();        }        return $page;    }    //得到商品信息    public function getProductInfo($desc) {        if (array_key_exists($desc, $this->info))            return $this->info[$desc];        return false;    }    //记录商品连接    private function recordProductLink($url_array, $id) {        $this->trace('商品链接共:' . (string) count($url_array));        foreach ($url_array as $url) {            $transaction = Yii::app()->db->beginTransaction();            try {                $productLink = ProductLink::model()->findByPk($url);                if ($productLink == NULL) {                    $productLink = new ProductLink;                    $productLink->user_id = Yii::app()->user->getId();                    $productLink->updateTime = NULL;                    $productLink->product_tpye_id = $id;                    $productLink->url = $url;                    if ($productLink->save())                        $transaction->commit();                }            } catch (Exception $e) {                $transaction->rollback();                print $e->getMessage();            }        }        unset($url_array);        return true;    }    //搜索商品链接    private function findProductLinks($html) {        $temps = array();        //连接        foreach ($html->find('.product-image') as $key=>$element) {            if($key == self::MAX_P)  break;            $productaddr = trim( $element->getAttribute('href'));            $temps[] =$productaddr;            $this->trace('搜索到商品链接:' . self::HOME_URL . $productaddr);        }        $links = array_unique($temps);        unset($temps);        return $links;    }    //设置商品信息    private function setProductInfo() {        $this->getSwatches();        $this->getImages($this->dom);        $this->getDetailsAndDesigner($this->dom);    }    //商品编号    private function getpid($html) {        $info = $html->find('input[name=product]', 0);        $this->skuId = $info->getAttribute('value');        $info = $html->find('h3[class=sku-number]', 0);        $this->info['pid'] = trim($info->plaintext);        $this->info['id'] = self::STATION . $this->info['pid'];                $this->trace('商品内部编号:' . $this->skuId);        $this->trace('商品编号:' . $this->info['pid']);    }    //品牌    private function getBrand($html) {        $this->info['brandName'] = trim($html->plaintext);        $this->trace('品牌:' . $this->info['brandName']);    }    //标题    private function getTitle($html) {        $info = $html->first_child();        $this->info['productTitle'] = trim($info->plaintext);        $this->trace('商品名:' . $this->info['productTitle']);    }    //商品名    private function getProductTitle($html) {        $ret = $html->getElementById('#productTitle');        if (empty($ret))            return;        $this->trace('商品名:' . trim($ret->plaintext));        $this->info['productTitle'] = trim($ret->plaintext);    }    //打折    private function getPercentOff() {            }    //原始价格    private function getOriginalRetailPrice($html) {        $info = $html->getElementById('#old-price-'.$this->skuId);        if (!empty($info)) {            $stPrice = 0;            $stPrice = trim($info->plaintext);            $this->info['originalRetailPrice'] = $this->analyPrice($stPrice);            $this->trace('原价:' . $this->info['originalRetailPrice']);        }    }    //价格    private function getPrice($html) {        $this->getOriginalRetailPrice($html);        $stPrice = 0;        if ($this->getInfo('originalRetailPrice')) {            $info = $html->getElementById('product-price-'.$this->skuId);            if (!empty($info)) {                $stPrice = trim($info->plaintext);            }        } else {            $info = $html->find('.price', 0);            if (!empty($info)) {                $stPrice = trim($info->plaintext);            }        }        $this->info['price'] = $this->analyPrice($stPrice);        $this->trace('现价:' . $this->info['price']);    }    //modify: stephen 2013-06-07    private function getProductPrice($price) {        return strval(ceil(floatval($price) * 0.82 * 1.1 + 60));    }    //图片    private function getImages($html) {        $this->info['images'] = array();        $this->info['images']['0'] = array();        foreach ($html->find('div[class=items noscroller] a') as $el) {            $imgAddr = $el->getAttribute('rev');            if ($this->downImage($imgAddr))                $this->info['images']['0'][] = $imgAddr;        }                $this->trace('图片:');        $this->trace($this->info['images']);    }    //颜色样本    private function getSwatches() {        $this->info['colors'] = array();        $colorobj = new stdclass;        $colorobj->code = '0';        $colorobj->image = null;        $colorobj->name = '图片色';        $this->info['colors'][] = $colorobj;        $this->trace('颜色:');        $this->trace($this->info['colors']);    }        //尺寸    private function getSizes($html) {        $this->info['stock'] = 0;        $this->info['sku'] = array();        $this->info['sizes'] = array();        foreach ($html->find('.sizes a') as $info) {            $sku = new stdClass;            $sku->color = '图片色';            $class = $info->getAttribute('class');            if ($class == "addtocart-trigger") {                $sku->size = trim($info->plaintext);                $sku->count = 5;            } elseif ($class == "addtowaitlist") {                $sku->size = trim($info->find('span.lighter', 0)->plaintext);                $sku->count = 0;            }            $sizeobj = new stdClass;            $sizeobj->name = $sku->size;            $sizeobj->code = '0';            $sizeobj->image = null;            $this->info['sizes'][] = $sizeobj;            $this->info['stock'] += $sku->count;            $this->info['sku'][] = $sku;        }        if (count($this->info['sizes']) == 0) {            $cart = $html->getElementById("add-to-cart-button_" . $this->skuId);            if (!empty($cart)) {                $this->info['stock'] = 5;                $sizeobj = new stdClass;                $sizeobj->name = '均码';                $sizeobj->code = '0';                $sizeobj->image = null;                $this->info['sizes'][] = $sizeobj;                                $sku = new stdClass;                $sku->color = '图片色';                $sku->size = '均码';                $sku->count = 5;                $this->info['sku'][] = $sku;            }        }    }    //说明    private function getDetailsAndDesigner($html) {        $ret = $html->find('ul[class=disc featurepoints]');        if (empty($ret))            return;        foreach ($ret as $key => $info) {            if ($key == 0) {                $this->info['desc'] = trim($info->outertext);                $this->trace('商品信息:' . $this->info['desc']);            } else if ($key == 1) {                $this->info['sizeFitContainer'] = trim($info->outertext);                $this->trace('尺寸描述:' . $this->info['sizeFitContainer']);            } else {                return;            }        }    }}?>