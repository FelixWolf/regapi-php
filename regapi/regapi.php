<?php
/*
Name: regapi.php
Purpose: Implements a simple file cache

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

require_once("llsd.php");

function generateUniqueName($prefix = "Resident"){
    $dt = new Datetime();
    $dt->setTimezone("UTC");
    return $prefix.$dt->format("YmdHis");
}

function parseUsername($input){
    if(is_array($input)){
        if(count($input) == 1){
            array_push($input, "resident");
        }elseif(count($input) == 2){
            return $input;
        }else
            return null;
    }elseif(is_string($input)){
        if(strpos($input, ".")){
            if(strpos($input, " "))
                return null;
            $input = explode(".", $input, 2);
            if(count($input) == 1){
                array_push($input, "resident");
                return $input;
            }elseif(count($input) == 2)
                return $input;
            else
                return null;
        }elseif(strpos($input, " ")){
            if(strpos($input, "."))
                return null;
            $input = explode(" ", $input, 2);
            if(count($input) == 1){
                array_push($input, "resident");
                return $input;
            }elseif(count($input) == 2)
                return $input;
            else
                return null;
        }else{
            return array($input, "resident");
        }
    }
    return null;
}

class HTTPResponse{
    function __construct($status, $headers, $body){
        $this->status = $status;
        $this->headers = $headers;
        $this->body = $body;
    }
    public function __toString(){
        return $this->body;
    }
}

function HTTPRequest($method, $path, $headers = null, $body = null){
    $ch = curl_init($path);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if($body !== null)
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    if($headers !== null)
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    $headers = [];
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$headers){
        $len = strlen($header);
        $header = explode(':', $header, 2);
        if (count($header) < 2) // ignore invalid headers
        return $len;

        $headers[strtolower(trim($header[0]))][] = trim($header[1]);

        return $len;
    });
    $data = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return new HTTPResponse($status, $headers, $data);
}

class RegAPIError extends Exception{
    //General purpose RegAPI error
    public function __construct($expression, $message, $code = -1){
        /*Expression is the "error message", message is the "description",
            code is the error code given by the RegAPI. If it is -1, it is
            generated internally.*/
        $this->expression = $expression;
        $this->message = $message;
        $this->code = $code;
    }
    public function __toString(){
        return "[".$this->code."] ".$this->message;
    }
}

class RegAPI{
    static public $LASTNAME_RESIDENT = 10327;
    static public $MATURITY_GENERAL = "General";
    static public $MATURITY_MODERATE = "Moderate";
    static public $MATURITY_ADULT = "Adult";
    static public $baseHeaders = array(
        "user-agent" => "RegAPI Library (PHP Edition) By Kyler Eastridge"
    );
    public function __construct($capabilities = null, $cache = null){
        if($capabilities == null)
            $capabilities = array();
        $this->capabilities = $capabilities;
        $this->cache = $cache;
    }
    
    public function getCapabilities($username, $password, $IKnowWhatIAmDoing = False){
        if(!$IKnowWhatIAmDoing)
            trigger_error(<<<EOT
!!! WARNING:
Storing account information in a script is dangerous. If you are running this
in a production environment, this can result in your account credentials being
leaked if the server becomes misconfiguration or hacked!
EOT
, E_USER_WARNING);
        $username = parseUsername($username);
        $result = HTTPRequest("POST", "https://cap.secondlife.com/get_reg_capabilities",
            array_merge(
                self::$baseHeaders,
                array(
                    "content-type: application/x-www-form-urlencoded"
                )
            ),
            http_build_query(array(
                "first_name" => $username[0],
                "last_name" => $username[1],
                "password" => $password
            ))
        );
        $this->capabilities = llsdDecode($result->body);
        return $this->capabilities;
    }
    
    public function getCapability($cap){
        /* Helper function - Get the capability by name, otherwise throw an
            error if we don't have it. */
        if(array_key_exists($cap, $this->capabilities))
            return $this->capabilities[$cap];
        throw new Exception("No capability '".$cap."'!");
    }
    
    public function getErrorCodes(){
        //Returns a list of error codes in [[code, name, desc],...] format.
        $cap = $this->getCapability("get_error_codes");
        $result = null;
        if($this->cache)
            $result = $this->cache->get("get_error_codes");
        
        if(!$result){
            $result = HTTPRequest("GET", $cap,
                array_merge(
                    self::$baseHeaders,
                )
            );
            $result = llsdDecode($result->body);
            if($this->cache)
                $this->cache->set("get_error_codes", $result);
        }
        return $result;
    }
    public function getError($errCode){
        /*Helper function. Resolve a error code by ID.*/ 
        foreach($this->getErrorCodes() as $code){
            if($code[0] == $errCode)
                return new RegAPIError($code[1], $code[2], $code = $code[0]);
        }
        return new RegAPIError("Unknown Error", "Couldn't resolve the error message!");
    }
    public function getLastNames(){
        /*Returns a list of available usernames in {id: "name", ...} format*/
        $cap = $this->getCapability("get_last_names");
        $result = null;
        if($this->cache)
            $result = $this->cache->get("get_last_names");
        
        if(!$result){
            $result = HTTPRequest("GET", $cap,
                array_merge(
                    self::$baseHeaders,
                )
            );
            $tmp = llsdDecode($result->body);
            $result = array();
            foreach($tmp as $key => $value)
                $result[intval($key)] = $value;
            
            if($this->cache)
                $this->cache->set("get_last_names", $result);
        }
        return $result;
    }
    public function getExperiences(){
        /*Returns a list of experiences the capability has access to in
            {id: "name"} format*/
        $cap = $this->getCapability("get_experiences");
        $result = null;
        if($this->cache)
            $result = $this->cache->get("get_experiences");
        
        if(!$result){
            $result = HTTPRequest("GET", $cap,
                array_merge(
                    self::$baseHeaders,
                )
            );
            $result = llsdDecode($result->body);
            
            if($this->cache)
                $this->cache->set("get_experiences", $result);
        }
        return $result;
    }
    public function getAvatars(){
        /*Returns a list of available starting avatars in {id: "name"} format*/
        $cap = $this->getCapability("get_avatars");
        $result = null;
        if($this->cache)
            $result = $this->cache->get("get_avatars");
        
        if(!$result){
            $result = HTTPRequest("GET", $cap,
                array_merge(
                    self::$baseHeaders
                )
            );
            
            $result = llsdDecode($result->body);
            if($this->cache)
                $this->cache->set("get_avatars", $result);
        }
        return $result;
    }
    public function checkName($username, $lastNameId = null){
        /*Returns a list of available starting avatars in {id: "name"} format*/
        $cap = $this->getCapability("check_name");
        $result = HTTPRequest("POST", $cap,
            array_merge(
                self::$baseHeaders,
                array(
                    "content-type: application/llsd+xml"
                )
            ),
            llsdEncode(new Map([
                "username" => $username,
                "last_name_id" => $lastNameId === null?self::$LASTNAME_RESIDENT:$lastNameId
            ]))
        );
        
        $result = llsdDecode($result->body);
        
        if(is_array($result))
            throw $this->getError($result[0]);
        
        return $result;
    }
    public function createUser($username, $lastNameId = null,
                    $estate = null, $region = null, $location = null,
                    $lookAt = null, $marketing = null, $successUrl = null,
                    $errorUrl = null, $maturity = null){
        /*Creates a user with the provided username. Automatically assumes
            resident is the lastname if not specified.
            If estate is specified, region, location and lookAt can also be
                specified.
            location and lookAt must be a tuple of floats representing X, Y, Z.
            marketing will enable Marketing emails from Linden Lab.
            successUrl is where the user will be redirected to after registration.
            errorUrl is where the user will be redirected to if there is an error.
            maturity must be "G", "M", "A", "General", "Mature", "Adult", or
                one of the RegAPI.MATURITY_* values.
        */
        $cap = $this->getCapability("create_user");
        $result = null;
        $data = array(
            "username" => $username,
            "last_name_id" => $lastNameId === null?self::$LASTNAME_RESIDENT:$lastNameId
        );
        if($estate)
            $data["limited_to_estate"] = $estate;
        
        if($region)
            $data["start_region_name"] = $region;
        
        if($location){
            $data["start_local_x"] = floatval($location[0]);
            $data["start_local_y"] = floatval($location[1]);
            $data["start_local_z"] = floatval($location[2]);
        }
        
        if($lookAt){
            $data["start_look_at_x"] = floatval($lookAt[0]);
            $data["start_look_at_y"] = floatval($lookAt[1]);
            $data["start_look_at_z"] = floatval($lookAt[2]);
        }
        
        if($marketing)
            $data["marketing_emails"] = $marketing;
        
        if($successUrl)
            $data["success_url"] = new URI($successUrl);
        
        if($errorUrl)
            $data["error_url"] = new URI($errorUrl);
        
        if($maturity)
            $data["maximum_maturity"] = $maturity;
        
        $result = HTTPRequest("POST", $cap,
            array_merge(
                self::$baseHeaders,
                array(
                    "content-type: application/llsd+xml"
                )
            ),
            llsdEncode(new Map($data))
        );
        $result = llsdDecode($result->body);
        
        if(is_array($result))
            throw $this->getError($result[0]);
        
        return $result;
    }
    public function regenerateUserNonce($agentId){
        /*Regenerate a registration URL. Useful if you have a internal database
            and a user re-requests to create their authorized account.
            AgentID must be a UUID.*/
        $cap = $this->getCapability("regenerate_user_nonce");
        $result = HTTPRequest("POST", $cap,
            array_merge(
                self::$baseHeaders,
                array(
                    "content-type: application/llsd+xml"
                )
            ),
            llsdEncode(new Map([
                "agent_id" => $agentId
            ]))
        );
        
        $result = llsdDecode($result->body);
        
        if(is_array($result))
            throw $this->getError($result[0]);
        
        return $result;
    }
    public function setUserAvatar($agentId, $avatarId){
        /* Set the starting avatar for agentId to avatarId. See getAvatars()
            Both agentId and avatarId must be a UUID.
            **This cannot be used after the resident logs in!**
            **This ability expires after 1 hour of the account creation!**
        */
        $cap = $this->getCapability("set_user_avatar");
        $result = HTTPRequest("POST", $cap,
            array_merge(
                self::$baseHeaders,
                array(
                    "content-type: application/llsd+xml"
                )
            ),
            llsdEncode(new Map([
                "agent_id" => $agentId,
                "avatar_id" => $avatarId
            ]))
        );
        
        $result = llsdDecode($result->body);
        
        if(is_array($result))
            throw $this->getError($result[0]);
        
        return $result;
    }
    public function setUserExperience($agentId, $experienceId){
        /*Automatically make the user accept a experience.
            agentId and experienceId must both be a UUID.
            **This cannot be used after the resident logs in!**
            **This ability expires after 1 hour of the account creation!**
        */
        $cap = $this->getCapability("set_user_experience");
        $result = HTTPRequest("POST", $cap,
            array_merge(
                self::$baseHeaders,
                array(
                    "content-type: application/llsd+xml"
                )
            ),
            llsdEncode(new Map([
                "agent_id" => $agentId,
                "experience_id" => $experienceId
            ]))
        );
        
        $result = llsdDecode($result->body);
        
        if(is_array($result))
            throw $this->getError($result[0]);
        
        return $result;
    }
    public function addToGroup($username, $groupName){
        /* Add a user to a group that you manage.
            username can be a string or tuple.
            **This CAN be used after the resident logs in!**
            **This ability expires after 1 hour of the account creation!**
        */
        $cap = $this->getCapability("add_to_group");
        $username = parseUsername($username);
        $result = HTTPRequest("POST", $cap,
            array_merge(
                self::$baseHeaders,
                array(
                    "content-type: application/llsd+xml"
                )
            ),
            llsdEncode(new Map([
                "first" => $username[0],
                "last" => $username[1],
                "group_name" => $groupName
            ]))
        );
        
        $result = llsdDecode($result->body);
        
        if(is_array($result))
            throw $this->getError($result[0]);
        
        return $result;
    }
}