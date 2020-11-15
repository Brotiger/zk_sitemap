<?php

    /*
        Название программы: Генератор sitemap
        Описание: Генерация sitemap 2 методами, с помощью запроса содержимого страницы через file_get_content, а так же с помощью запросов к бд 1-С Битрикс через API
        Версия: 1.0.0
        Автор: Берестнев Дмитрий Дмитриевич
        Контактная информация: https://vk.com/brotiger63
    */

    set_time_limit(0);

    class SiteMap
    {
        private $host = "shop.zolotoykod.ru"; 
        private $cType = "https://";
        private $page = "https://shop.zolotoykod.ru";
        private $siteMapPath = "./";
        private $siteMapIndex = 0;
        private $nofollow = [];

        private $rn="\r\n";
        private $rnt="\r\n\t";

        function __construct(bool $isDev = false, array $nofollow = []){

            $this->nofollow = $nofollow;

            if(!$isDev){
                $this->rn="";
                $this->rnt="";
            }

            if(!file_exists($this->siteMapPath . "sitemap")){
                mkdir($this->siteMapPath . "sitemap", 0755,true);
            }

            $this->Generate();

            print("Sitemap created\n");
        }

        private function Generate()
        {
            $reg = [];

            $page = $this->page;

            $content = file_get_contents($page);

            if(!$content){
                return;
            }

            preg_match_all("~<[Aa][\s]{1}[^>]*[Hh][Rr][Ee][Ff][^=]*=[ '\"\s]*([^ \"'>\s#]+)[^>]*>~", $content, $tmp_home);
            
            foreach($tmp_home[0] as $key => $value){
                
                if(!preg_match('~<.*[Rr][Ee][Ll]=.?("|\'|).*[Nn][Oo][Ff][Oo][Ll][Ll][Oo][Ww].*?("|\'|).*~', $value)){
                    $links[] = $tmp_home[1][$key];
                }
            }

            foreach($links as $key => $value){
                if(!strstr($value, $this->cType)){
                    $links[$key] = $this->page.$value;
                }
                $url_info = parse_url($links[$key]);

                if($url_info['host'] != $this->host || strstr($links[$key], "@")){
                    continue;
                }

                $links[$key] = rtrim($links[$key], "/");
                $links[$key] = preg_replace("~/#.*~", '', $links[$key]);
                $urls[] = $links[$key];
            }

            #Добавляем sitemap для ajax ссылок
            $urls = array_merge($urls, $this->getProductList());

            $urls = array_unique($urls);

            #Формирование масива с регионами
            $reg = $this->getCityList();

            #Удляем старый sitemap
            $this->removeSitemap();

            #Формируем и записываем sitemap для каждого региона
            foreach($reg as $r_key => $r_value){

                $linksArrayObject = new ArrayObject($urls);
                $reg_urls = $linksArrayObject->getArrayCopy();

                foreach($reg_urls as $u_key => $u_value){
                    $reg_urls[$u_key] = preg_replace("~(".$this->page.")(/catalog/.*)~", "$1"."/regions/".$r_value."$2", $u_value);
                }
                $this->createSiteMap($reg_urls);

            }

            #Записываем siteMap для главной страницы
            $this->createSiteMap($links);

            #Генерация главного siteMap в который вложены остальные
            $this->createMainSiteMap();
        }

        #Запись корневого siteMap
        private function createMainSiteMap(){
            $rn = $this->rn;
            $rnt = $this->rnt;

            $date = date("Y-m-d\TH:i:sP");

            $sitemapXML = '<?xml version="1.0" encoding="UTF-8"?>' . $rn . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

            $siteMapIndex = $this->siteMapIndex - 1;

            $index = 0;

            while($index <= $siteMapIndex){
                $location = $this->page . "/sitemap/sitemap" .$index.".xml";
                $sitemapXML .= "{$rn}<sitemap>{$rnt}<loc>{$location}</loc>{$rnt}<lastmod>{$date}</lastmod>{$rn}</sitemap>";
                $index++;
            }

            $sitemapXML .= "{$rn}</sitemapindex>";

            $this->writeFile($sitemapXML, "sitemap");
        }

        #Запись дочерних siteMap
        private function createSiteMap($links){
            $rn = $this->rn;
            $rnt = $this->rnt;
            $nofollow = $this->nofollow;

            $priority = 1;

            $sitemapXML = '<?xml version="1.0" encoding="UTF-8"?>'. $rn .'<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xhtml="http://www.w3.org/1999/xhtml" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">';

            foreach($links as $key => $value){

                foreach($nofollow as $n_key => $n_value){
                    if(preg_match("~{$n_value}~", $value)){
                        continue 2;
                    }
                }

                if(preg_match("~/catalog/section/~", $value)){
                    $priority = 0.9;
                }else if (preg_match("~/catalog/~", $value)){
                    $priority = 0.8;
                }
                
                $date = date("Y-m-d\TH:i:sP");
                $sitemapXML.="{$rn}<url>{$rnt}<loc>{$value}</loc>{$rnt}<lastmod>{$date}</lastmod>{$rnt}<changefreq>hourly</changefreq>{$rnt}<priority>{$priority}</priority>{$rn}</url>";
            }

            $sitemapXML.="{$rn}</urlset>";

            $this->writeFile($sitemapXML, "sitemap/sitemap".$this->siteMapIndex);

            $this->siteMapIndex++;
            unset($sitemapXMLp);
        }

        private function writeFile($file, $name){
            $fp = fopen($this->siteMapPath.$name.'.xml','w+');

            if(!fwrite($fp,$file)){
                print('Write error');
            }

            fclose($fp);
        }

        function removeSitemap(){
            if(file_exists($this->siteMapPath . "sitemap.xml")){
                unlink($this->siteMapPath . "sitemap.xml");
            }

            $includes = glob($this->siteMapPath.'sitemap/*');
        
            foreach ($includes as $include){
                unlink($include);
            }
        }

        private function getProductList(){
            $count = json_decode(file_get_contents('https://api.dev.zolotoykod.ru/v1/shop/Catalog/count?filter={"ACTIVE":"Y"}'));
            $count = $count->count;
            $once = 200;
            $numberOfRequests = ceil($count / $once);
            $products = [];
            for($i = 1; $i <= $numberOfRequests; $i++){
                $query = json_decode(file_get_contents('https://api.dev.zolotoykod.ru/v1/shop/Catalog/?filter={"ACTIVE":"Y"}&navParams={"iNumPage":' . $i . ',"nPageSize":' . $once . '}'));
                foreach($query as $value){
                    $products[] = $this->page . "/catalog/" . $value->CODE;
                }
                unset($value, $query);
            }
            return $products;
        }

        private function getCityList(){
            $sity = json_decode(file_get_contents('https://api.dev.zolotoykod.ru/v1/shop/Cities/'));
            $reg = [];

            foreach($sity as $key => $value){
                $reg[] = $value->CODE;
            }
            return $reg;
        }
    }

    if(is_null($argv[1]) || $argv[1] == "false"){
        $isDev = false;
    }else{
        $isDev = true;
    }

    $nofollow = is_null($argv[2])? [] : explode(",", $argv[2]);

    $SiteMap = new SiteMap($isDev, $nofollow);
?>