<?phpinclude('WebScrap.php');/* * ******************************************************************************* * Copyright(C),2013, Glory * FileName: Farfetch.php * Author:  stephen * Version: v2.0 * Date:  14:07 2015-03-05 * Description:  Farfetch采集类 * ******************************************************************************** */class Farfetch extends WebScrap {    const SHOPHEAD = 'http://www.Farfetch.com';    const STATION = 'FARFETCH';    private $cityIds;    private $citysizeurls;    private $cityurltags;    private $storeId;    private $host = 'http://www.farfetch.com';    private $tagprice;    //构造函数    function __construct($bFindAlternativeProduct = false) {        parent::__construct($bFindAlternativeProduct);    }    //销毁函数    function __destruct() {        parent::__destruct();        unset($this->citys);        unset($this->cityIds);        unset($this->citysizeurls);        unset($this->cityurltags);    }    public function init() {        $this->area = '欧洲';                $this->getCookieFile();        $this->citys = array('香港' => 'USD', '美国' => 'USD', '中国' => 'USD');        $this->cityIds = array('香港' => '93', '美国' => '216', '中国' => '42');        $this->citysizeurls = array('香港' => 'http://www.farfetch.com/hk/product/ProductDetailsAsync', '美国' => 'http://www.farfetch.com/product/ProductDetailsAsync', '中国' => 'http://www.farfetch.com/cn/product/ProductDetailsAsync');        $this->cityurltags = array('香港' => 'http://www.farfetch.com/hk/shoping/', '美国' => 'http://www.farfetch.com/shoping/', '中国' => 'http://www.farfetch.com/cn/shoping/');        $this->station = self::STATION;    }    public function parseurl() {        $urlparam = parse_url($this->url);        //$this->trace($urlparam);        $header = $this->cityurltags[$this->city];        if (strpos($this->url, $header) === false) {            $path = $urlparam['path'];            $pathpos = strpos($path, '/shopping/');            $path = substr($path, $pathpos + strlen('/shopping/'));            $this->url = $header . $path . '?' . $urlparam['query'];            if (isset($urlparam['fragment']))                $this->url = $this->url . '' . $urlparam['fragment'];            $this->trace('url修改:' . $this->url);        }    }    public function cookie() {        //get_cookie($this->host, $this->cookie_file);        $cookieurl = $this->host . 'ChangeCountry/' . $this->cityIds[$this->city];        $ret = curl_get_302($cookieurl, $this->cookie_file);        if ($this->host != $ret) {            $this->trace('curl_get_302=' . $ret);        }        /*          $cookieurl = $this->host . 'ChangeCountry/' . $this->cityIds[$this->city];          $this->trace($cookieurl);          $ret = curl_get_302($cookieurl, $this->cookie_file);          if($this->host != $ret){          $this->trace('curl_get_302 1='.$ret);          $this->host = $ret;          } */        //curl_get_cookie($cookieurl, $this->cookie_file,$this->host);    }    //采集单页面    public function collectSinglePage($url, $city = '') {        $this->trace('抓取整页: ' . $url);        $links = null;        try {            if ($this->htmlParser->loadURL($url) === false) {                $this->trace('无法取得网站数据');                return false;            }            $links = $this->findProductLinks();            $this->trace('查找到商品数: ' . strval(count($links)));        } catch (Exception $e) {                    }        $this->saveProductLinks($links);        unset($links);        return true;    }    //得到页数    private function getPageCount($url) {        $html = getHtmlDom($url);        $ret = $html->getElementById('#searchResultCount');        if (empty($ret))            return;        $count = intval($ret->plaintext) / 40;        return intval(ceil($count));    }    //得到当前页码    private function getcurrentPageNum($url) {        $array_query = parse_url($url);        $page = 0;        try {            if (isset($array_query['query'])) {                $item = explode('=', $array_query['query']);                $page = intval($item[1] / 40);            }        } catch (Exception $e) {                    }        return $page;    }    //翻页采集    public function collectPage($url, $city = '') {            }    private function decodeProductDetailLink($content) {        if (!isset($content{0}))            return false;;        $json = findSubString($content, "bop.config.filters", ";");        $json = trim($json);        $json = trim($json, "=");        $json = trim($json);        $json = trim($json, ";");        $json = '[' . $json . ']';        $json = str_replace('\"', '', $json);        $json = stripslashes($json);        $objs = json_decode($json);        $objs = json_decode($json);        if ($objs === NULL) {            $this->trace('json解析失败');            return false;        }        return $objs;    }    //搜索商品链接    private function findProductLinks() {        $links = array();        $productcontainer = $this->htmlParser->getElementById('product-container');        if ($productcontainer === false) {            $this->trace('$productcontainer->textContent');            return $links;        }        $this->trace($productcontainer->textContent);        $items = $this->htmlParser->getElementsByClassFromParent($productcontainer, 'a', 'photo');        //连接        foreach ($items as $info) {            $links[] = self::SHOPHEAD . $info->getAttribute('href');            $this->trace('搜索到商品链接:' . self::SHOPHEAD . $info->getAttribute('href'));        }        return $links;    }    private function getTags() {        $this->tags = array();                $breadcrumb = $this->htmlParser->getElementByClass('breadcrumbs-regular');        $childNodeList = $breadcrumb->getElementsByTagName('a');        for ($i = 0; $i < $childNodeList->length; $i++) {            $temp = $childNodeList->item($i);            if(stripos($temp->textContent, 'home')===false){                if(stripos($temp->textContent, 'women')!==false){                    $this->tags[] = '女士';                }elseif(stripos($temp->textContent, 'men')!==false){                    $this->tags[] = '男士';                }else{                    $this->tags[] = $temp->textContent;                }            }        }        $this->trace('标签：');        $this->trace($this->tags);    }    //设置商品信息    private function setProductInfo() {        $this->getTags();                $this->getSizeFitContainer();                $this->getDetails();                $heads = $this->htmlParser->getTagNodes('head');        foreach ($heads as $head) {            $price = $this->htmlParser->getElementByAttrFromParent($head, 'meta', 'name', 'twitter:data1');            $this->tagprice = analyPrice($price->getAttribute('content'));            $this->sellingPrice = $this->tagprice;            $this->trace('tagprice:'.$this->tagprice);            $this->trace('图片:');            $this->images['0'] = array();            $imgs = $this->htmlParser->getElementsByAttrFromParent($head, 'meta', 'property', 'og:image');            foreach ($imgs as $img) {                $this->images['0'][] = trim($img->getAttribute('content'));            }            $this->trace($this->images);        }        $param = 'id=' . $this->pid . '&storeId=' . $this->storeId . '&sizeId=';        $objs = postJosn($this->citysizeurls[$this->city], $this->cookie_file, $param);        if ($objs === false) {            $this->trace('获取size信息失败!');            return false;        }        //colors        $this->colors = array();        $colorobj = new stdClass;        $colorobj->code = '0';        $colorobj->image = null;        $colorobj->name = '图片色';        $this->colors[] = $colorobj;        $this->trace('颜色:');        $this->trace($this->colors);        $this->sizes = array();        $this->htmlParser->loadFormHtml($objs->Sizes);        $detailSizeDropdown = $this->htmlParser->getElementById('detailSizeDropdown');        if ($detailSizeDropdown !== false) {            $links = $this->htmlParser->getElementsByClassFromParent($detailSizeDropdown, 'a', 'float-left');            foreach ($links as $link) {                $span = $this->htmlParser->getElementByClassFromParent($link, 'span', 'productDetailModule-dropdown-numberItems');                if ($span !== false) {                    $sizeid = trim($span->textContent);                    $sizeobj = null;                    if (!isset($this->sizes[$sizeid])) {                        $sizeobj = new stdClass;                    } else {                        $sizeobj = $this->sizes[$sizeid];                    }                    $sizeobj->name = $sizeid;                    $sizeobj->code = $sizeid;                    $sizeobj->image = null;                    $spancount = $this->htmlParser->getElementByClassFromParent($link, 'span', 'productDetailModule-dropdown-leftInStock');                    if ($spancount !== false) {                        if (stripos($spancount->getAttribute('class'), 'color-medium-grey') !== false) {                            $sizeobj->count = 3;                        } else {                            $sizeobj->count = 1;                        }                    }                    $this->sizes[$sizeid] = $sizeobj;                }                if ($link->hasAttribute('data-sizeid') && $link->hasAttribute('href')) {                    $sizeid = $link->getAttribute('data-sizeid');                    $this->trace($sizeid);                    $listingprice = $this->htmlParser->getElementByClassFromParent($link, 'span', 'listing-price');                    if ($listingprice !== false) {                        $sizeobj = null;                        if (!isset($this->sizes[$sizeid])) {                            $sizeobj = new stdClass;                        } else {                            $sizeobj = $this->sizes[$sizeid];                        }                        $sizeobj->price = analyPrice($listingprice->textContent);                        $this->sizes[$sizeid] = $sizeobj;                        if ($this->tagprice < $sizeobj->price) {                            $this->listPrice = $sizeobj->price;                        } else {                            $this->sellingPrice = $sizeobj->price;                            $this->listPrice = $this->tagprice;                        }                        $this->trace('Price:'.$sizeobj->price);                    }                }            }            $this->trace('尺寸:');            $this->trace($this->sizes);            //sku            $this->sku = array();            foreach ($this->sizes as $size) {                $sku = new stdClass;                $this->stock += $size->count;                $sku->count = $size->count;                $sku->color = '图片色';                $sku->size = $size->name;                if (isset($size->price))                    $sku->price = $size->price;                $this->sku[] = $sku;            }            $this->trace('sku:');            $this->trace($this->sku);                        $this->trace('原价:' . $this->listPrice);            $this->trace('销售价:' . $this->sellingPrice);        }    }    private function decodeProductDetailScript($reps) {        if (!isset($reps{0}))            return false;;        $json = findSubString($reps, "window.universal_variable", ";");        if ($json === false)            return false;        $json = trim($json);        $json = trim($json, "=");        $json = trim($json);        $json = trim($json, ";");        $json = '[' . $json . ']';        $json = str_replace('\"', '', $json);                $json = stripslashes($json);                $objs = json_decode($json);        if ($objs === NULL) {            return false;        }        return $objs;    }    //得到JSON数据    private function getJsonData() {        $items = $this->htmlParser->getTagNodes('script');        foreach ($items as $item) {            $objs = $this->decodeProductDetailScript($item->textContent);            if ($objs !== false) {                return $objs;            }        }        return false;    }    //解析JSON数据    private function analyJsonData() {        $objs = $this->getJsonData();        if ($objs === false)            return false;        foreach ($objs as $obj) {            //storeId            $this->storeId = trim($obj->product->storeId);            $this->trace('storeId:' . $this->storeId);            //id            $this->pid = trim($obj->product->id);            $this->id = self::STATION . $this->pid;            $this->trace('编号:' . $this->pid);            //brandName            $this->brandName = trim($obj->product->designerName);            $this->brandName = stripcslashes($this->brandName);            $this->trace('品牌:' . $this->brandName);            //title            $this->productTitle = trim($obj->product->name);            $this->productTitle = str_replace('u0027','\'', $this->productTitle);            $this->trace('商品名:' . $this->productTitle);            //hasStock            $this->isInStock = $obj->product->hasStock;            $this->trace('是否存货:' . $this->isInStock);        }        return true;    }    //解析单个商品    public function productAnaly($url, $city = '') {        if (parent::productAnaly($url, $city) === false) {            $this->trace('不支持该城市采集!');            return false;        }        $this->parseurl();        $this->cookie();        if ($this->htmlParser->loadURL($this->url, $this->cookie_file) === false) {            $this->trace('无法取得网站数据');            return false;        }        if (!$this->analyJsonData()) {            $this->trace('找不到商品JSON数据!');            return false;        }        if ($this->isInStock) {            $this->setProductInfo();            $this->saveImage();        }        return $this->save();    }    //商品编号    private function getId($reps) {        $ret = findSubString($reps, 'productPage.productCode', ';');        if ($ret !== false) {            $ret = trim($ret);            $ret = trim($ret, "=");            $ret = trim($ret);            $ret = trim($ret, ";");            $ret = trim($ret, "'");            $this->pid = trim($ret);            $this->id = self::STATION . $this->pid;            $this->trace('编号:' . $this->pid);            return true;        }        return false;    }    //尺寸描述    private function getSizeFitContainer() {        $btnSizeHelp = $this->htmlParser->getElementById('btnSizeHelp');        if ($btnSizeHelp !== NULL) {            $url = $this->host . $btnSizeHelp->getAttribute('href');            $content = curl_get_cookie($url, $this->cookie_file);            if($content !== false){                $this->sizeFitContainer = trimButton($content);                $this->trace('尺寸描述:' . $this->sizeFitContainer);            }        }    }    private function saveImage() {        if ($this->colors) {            foreach ($this->colors as $color) {                if (isset($color->image)) {                    $this->downImage($color->image);                }            }        }        if ($this->images) {            foreach ($this->images as $images) {                foreach ($images as $image) {                    $this->downImage($image);                }            }        }    }    //计算库存    public function countStock($swatchesCode, $sizesCode) {        $url = self::SHOPSTOCK . 'product=' . $this->pid . '&size=' . $sizesCode . '&color=' . $swatchesCode;        $this->trace('货存实时查询：' . $url);        $stock = 0;        $respObject = getJosn($url);        if (!$respObject)            return $stock;        if ($respObject->result == 'success') {            if (isset($respObject->responseData->available) &&                    ($respObject->responseData->available != null || $respObject->responseData->available != 'null')) {                $stock = intval($respObject->responseData->available);            } else {                $stock = 5;            }        } else {            $stock = 0;        }        return $stock;    }    //说明    private function getDetails() {        $accordion = $this->htmlParser->getElementByClass('accordion-xl');        if($accordion !== false){            $accordioncontents = $this->htmlParser->getElementsByClassFromParent($accordion, 'div', 'accordion-content');            foreach ($accordioncontents as $content) {                $tstid = $content->getAttribute('data-tstid');                if($tstid == 'Content_Description'){                    $this->desc = trim($content->textContent);                    $this->trace('描述:' . $this->desc);                }elseif ($tstid == 'Content_Composition&Care') {                    $this->details = trim($content->textContent);                    $this->trace('详细:' . $this->details);                }elseif ($tstid == 'Content_Designer') {                    $this->designer = trim($content->textContent);                    $this->trace('设计师:' . $this->designer);                }            }        }    }}