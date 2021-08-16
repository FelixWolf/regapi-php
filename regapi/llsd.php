<?php
/*
Name: llsd.php
Purpose: Parse and serialize LLSD objects

Copyright (c) 2021 Kyler Eastridge

This software is provided 'as-is', without any express or implied
warranty. In no event will the authors be held liable for any damages
arising from the use of this software.

Permission is granted to anyone to use this software for any purpose,
including commercial applications, and to alter it and redistribute it
freely, subject to the following restrictions:

1. The origin of this software must not be misrepresented; you must not
   claim that you wrote the original software. If you use this software
   in a product, an acknowledgment in the product documentation would be
   appreciated but is not required.
2. Altered source versions must be plainly marked as such, and must not be
   misrepresented as being the original software.
3. This notice may not be removed or altered from any source distribution.
*/

class URI{
    protected $value;
    public function __construct($value = ""){
        $this->value = $value;
    }
    public function __debugInfo(){
        return array("value" => (string)$this);
    }
    public function __toString(){
        return $this->value;
    }
    public function is_empty(){
        return strlen($this->value) == 0;
    }
}

function is_uri($input){
    return $input instanceof URI;
}

class Map extends ArrayObject{
    public function __debugInfo(){
        return $this->storage;
    }
}

function is_map($input){
    return $input instanceof Map;
}

class Binary{
    protected $value;
    public function __construct($value = ""){
        $this->value = $value;
    }
    public function __debugInfo(){
        return array("length" => strlen($this->value));
    }
    public function __toString(){
        return $this->value;
    }
    public function is_empty(){
        return strlen($this->value) == 0;
    }
}

function is_binary($input){
    return $input instanceof Binary;
}

class UUID{
    protected $value = "";
    public function __construct($value = null){
        if($value == null){
            for($i = 0; $i < 16; $i++){
                $this->value .= chr(rand(0,255));
            }
        }elseif(strlen($value) == 36){
            $i = 0;
            while($i < 36){
                if($i == 8 || $i == 13 || $i == 18 || $i == 23){
                    if(substr($value, $i, 1) != "-")
                        throw new Exception("Invalid UUID!");
                    $i += 1;
                    continue;
                }
                $val = substr($value, $i, 2);
                if(strlen($val) == 2 && ctype_xdigit($val))
                    $this->value .= chr(hexdec($val));
                else
                    throw new Exception("Invalid UUID!");
                $i += 2;
            }
        }elseif(strlen($value) == 16){
            $this->value = $value;
        }elseif($value == ""){
            $this->value = "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0";
        }else{
            throw new Exception("Invalid UUID!");
        }
    }
    public function __debugInfo(){
        return array("value" => (string)$this);
    }
    public function __toString(){
        $tmp = "";
        for($i = 0; $i < 16; $i++){
            $v = ord(substr($this->value, $i, 1));
            if($v < 16) $tmp .= "0";
            $tmp .= dechex($v);
            if($i == 3 || $i == 5 || $i == 7 || $i == 9) $tmp .= "-";
        }
        return $tmp;
    }
    public function is_null(){
        return $this->value === "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0";
    }
    public function equals($other){
        if($other instanceof $this){
            return $this->value == $other->value;
        }
        return false;
    }
}

function is_uuid($input){
    return $input instanceof UUID;
}


function parseISODate($input){
    if($input[-1] == "Z"){
        $input = substr($input, 0, -1);
        $dt = explode("T", $input, 2);
        if(count($dt) != 2)
            throw new Exception("Invalid timestamp '".$input."'!");
        $ymd = explode("-", $dt[0], 3);
        if(count($ymd) != 3)
            throw new Exception("Invalid timestamp '".$input."'!");
        $hms = explode(":", $dt[1], 3);
        if(count($hms) != 3)
            throw new Exception("Invalid timestamp '".$input."'!");
        $ms = 0;
        if(strpos($hms[2], ".")){
            $ss = explode(".", $hms[2], 2);
            if(count($ss) != 2)
                throw new Exception("Invalid timestamp '".$input."'!");
            $hms[2] = $ss[0];
            $ms = $ss[1];
        }
        $dt = new DateTime();
        $dt->setTimezone(new DateTimeZone('UTC'));
        $dt->setDate(intval($ymd[0]), intval($ymd[1]), intval($ymd[2]));
        $dt->setTime(intval($hms[0]), intval($hms[1]), intval($hms[2]), intval($ms)*10000);
        return $dt;
    }else
        throw new Exception("Invalid timestamp '".$input."'!");
}

//Encoder
function llsdEncodeXml($input, $destination, $optimize = False, $encoding = "base64"){
    if(is_null($input)){
        $destination->addChild("undef");
    }elseif(is_bool($input)){
        if($input)
            $destination->addChild("boolean", "true");
        elseif($optimize)
            $destination->addChild("boolean");
        else
            $destination->addChild("boolean", "false");
    }elseif(is_int($input)){
        if($optimize && $input == 0)
            $destination->addChild("integer");
        else
            $destination->addChild("integer", strval($input));
    }elseif(is_float($input)){
        if($optimize && $input == 0.0)
            $destination->addChild("real");
        else
            $destination->addChild("real", strval($input));
    }elseif(is_uuid($input)){
        if($optimize && $input->is_null())
            $destination->addChild("uuid");
        else
            $destination->addChild("uuid", strval($input));
    }elseif(is_string($input)){
        if($optimize && $input == "")
            $destination->addChild("string");
        else
            $destination->addChild("string", $input);
    }elseif(is_binary($input)){
        if($optimize && $input->is_empty())
            $destination->addChild("binary");
        else
            $destination->addChild("binary", $input);
    }elseif($input instanceof DateTime){
        if($optimize && $input->getTimestamp() == 0)
            $destination->addChild("date");
        else{
            $dt = new DateTime();
            $dt->setTimezone(new DateTimeZone('UTC'));
            $dt->setTimestamp($input->getTimestamp());
            $destination->addChild("date", $dt->format("Y\-m\-d\TH\:i\:s\.u\Z"));
        }
    }elseif(is_uri($input)){
        if($optimize && $input->is_empty())
            $destination->addChild("uri");
        else
            $destination->addChild("uri", $input);
    }elseif(is_map($input)){
        $root = $destination->addChild("map");
        foreach($input as $key => $value){
            $root->addChild("key", strval($key));
            llsdEncodeXml($value, $root, $optimize, $encoding);
        }
    }elseif(is_array($input)){
        $root = $destination->addChild("array");
        foreach($input as $value){
            llsdEncodeXml($value, $root, $optimize, $encoding);
        }
    }
}

function llsdEncode($input, $format = "xml"){
    if($format == "xml"){
        $result = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"utf-8\" ?><llsd></llsd>");
        llsdEncodeXml($input, $result);
        return $result->asXML();
    }else{
        throw new Exception("Unknown serialization format!");
    }
}


//Decoder
function llsdDecodeXml($input){
    switch(strtolower($input->getName())){
        case "undef":
            return null;
        case "boolean":
            $value = strtolower(strval($input));
            if(in_array($value, array("1", "true")))
                return true;
            elseif(in_array($value, array("", "0", "false")))
                return false;
            throw new Exception("Unexpected value \"".$input->getName()."\" for boolean!");
        case "integer":
            return intval($input);
        case "real":
            return floatval($input);
        case "uuid":
            return new UUID(strval($input));
        case "string":
            return strval($input);
        case "binary":
            $encoding = $input->attributes()["encoding"];
            if($encoding == "base64" || $encoding == "")
                return new Binary(base64_decode($input));
            elseif($encoding == "base85")
                //throw new Exception("Base85 is not implemented!");
                return new Binary("");
            elseif($encoding == "base16")
                return new Binary(hex2bin($input));
            throw new Exception("Unknown encoding \"".$encoding."\" for binary!");
        case "date":
            if($input == ""){
                $dt = new DateTime();
                $dt->setTimezone(new DateTimeZone('UTC'));
                $dt->setTimestamp(0);
                return $dt;
            }
            return parseISODate((string)$input);
        case "uri":
            return new URI(strval($input));
        case "map":
            $children = $input->children();
            $result = new Map();
            for($i = 0, $l = $children->count(); $i < $l; $i += 2){
                if(strtolower($children[$i]->getName()) != "key")
                    throw new Exception("Unexpected \"".$input->getName()."\" in LLSD map!");
                $result[strval($children[$i])] = llsdDecodeXml($children[$i+1]);
            }
            return $result;
        case "array":
            $children = $input->children();
            $result = array();
            for($i = 0, $l = $children->count(); $i < $l; $i++)
                array_push($result, llsdDecodeXml($children[$i]));
            return $result;
        default:
            throw new Exception("Unknown element type \"".$input->getName()."\" in LLSD!");
    }
}

function llsdDecode($input, $format = null, $maxHeaderLength = 128){
    if($format == null){
        $i = 0;
        $l = strlen($input);
        while($i < $l && $i < $maxHeaderLength){
            $c = $input[$i];
            if($c == '"' || $c == "'"){
                $quoteChar = $c;
                while($i < $l && $i < $maxHeaderLength){
                    $i += 1;
                    $c = $input[$i];
                    if($c == $quoteChar)
                        break;
                    elseif($c == "\\")
                        //Assuming the file is valid, no unicode should be in the
                        //header
                        $i += 1;
                }
                $i += 1;
            }
            $i += 1;
            if($c == ">")
                break;
        }
        $header = strtolower(trim(substr($input, 2, $i - 4)));
        if($header == "llsd/notation")
            $format = "notation";
        elseif($header == "llsd/binary")
            $format = "binary";
        else{
            $tmp = substr($header, 0, 3);
            if($tmp == "xml")
                $format = "xml";
            else
                throw new Exception("Unable to detect serialization format!");
        }
    }
    if($format == "xml"){
        $input = new SimpleXMLElement($input);
        if($input->getName() != "llsd"){
            throw new Exception("Expected a LLSD xml!");
        }
        return llsdDecodeXml($input->children());
    }else{
        throw new Exception("Unknown serialization format!");
    }
}
/*
$xmltest = <<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<llsd>
  <map>
    <key>undef</key>
      <array>
        <undef />
      </array>
    <key>boolean</key>
      <array>
        <!-- true -->
        <boolean>1</boolean>
        <boolean>true</boolean>
        
        <!-- false -->
        <boolean>0</boolean>
        <boolean>false</boolean>
        <boolean />
      </array>
    <key>integer</key>
      <array>
        <integer>289343</integer>
        <integer>-3</integer>
        <integer /> <!-- zero -->
      </array>
    <key>real</key>
      <array>
        <real>-0.28334</real>
        <real>2983287453.3848387</real>
        <real /> <!-- exactly zero -->
      </array>
    <key>uuid</key>
      <array>
        <uuid>d7f4aeca-88f1-42a1-b385-b9db18abb255</uuid>
        <uuid /> <!-- null uuid '00000000-0000-0000-0000-000000000000' -->
      </array>
    <key>string</key>
      <array>
        <string>The quick brown fox jumped over the lazy dog.</string>
        <string>540943c1-7142-4fdd-996f-fc90ed5dd3fa</string>
        <string /> <!-- empty string -->
      </array>
    <key>binary</key>
      <array>
        <binary encoding="base64">cmFuZG9t</binary> <!-- base 64 encoded binary data -->
        <binary>dGhlIHF1aWNrIGJyb3duIGZveA==</binary> <!-- base 64 encoded binary data is default -->
        <binary encoding="base85">YISXJWn>_4c4cxPbZBJ</binary>
        <binary encoding="base16">6C617A7920646F67</binary>
        <binary /> <!-- empty binary blob -->
      </array>
    <key>date</key>
      <array>
        <date>2006-02-01T14:29:53.43Z</date>
        <date /> <!-- epoch -->
      </array>
    <key>uri</key>
      <array>
        <uri>http://sim956.agni.lindenlab.com:12035/runtime/agents</uri>
        <uri /> <!-- an empty link -->
      </array>
  </map>
</llsd>
EOT;
$elementtest = new Map([
    "undef" => [null],
    "boolean" => [True, False],
    "integer" => [289343, -3, 0],
    "real" => [-0.28334, 2983287453.3848387, 0.0],
    "uuid" => [
        new UUID("d7f4aeca-88f1-42a1-b385-b9db18abb255"),
        new UUID("00000000-0000-0000-0000-000000000000")
    ],
    "string" => [
        "The quick brown fox jumped over the lazy dog.",
        "540943c1-7142-4fdd-996f-fc90ed5dd3fa",
        ""
    ],
    "binary" => [
        new Binary("The quick brown fox jumped over the lazy dog.")
    ],
    "date" => [
        new Datetime()
    ],
    "uri" => [
        new URI("http://sim956.agni.lindenlab.com:12035/runtime/agents")
    ]
]);

echo llsdEncode($elementtest, "xml");
echo print_r(llsdDecode($xmltest), true);
*/