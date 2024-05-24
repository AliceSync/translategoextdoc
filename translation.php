<?php

class Translation
{
    private SQLite3 $db;
    private $api;

    function __construct($api = "baidu")
    {
        try {
            $this->db = new SQLite3(ROOT . "/translation.db");
            $this->db->exec("CREATE TABLE IF NOT EXISTS translation (id INTEGER PRIMARY KEY AUTOINCREMENT, en TEXT, zh TEXT)");
            $this->api = $api;
        }
        catch (Exception $e) {
            exit_log($e->getMessage());
        }
    }

    function __destruct()
    {
        $this->db->close();
    }

    public function get($en)
    {
        if (trim($en) === "" || mb_strlen($en) < 2 || is_numeric($en)) {
            return $en;
        }

        log_message('run', "准备翻译英文: [" . $en . "]");
        $stmt = $this->db->prepare("SELECT zh FROM translation WHERE en = :en");
        $stmt->bindValue(":en", $en, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row    = $result->fetchArray(SQLITE3_ASSOC);

        if ($row !== false) {
            log_message('run', "结果: 数据库获取[" . $row["zh"] . "]");
            return $row["zh"];
        }

        $zh = $this->get_api($en);
        if ($zh !== false) {
            log_message('run', "结果: API获取[" . $zh . "]");
            $this->set_db($en, $zh);
            return $zh;
        }

        log_message('run', "结果: 翻译失败");
        return false;
    }

    public function get_api($en)
    {
        return match ($this->api) {
            "baidu"      => baidu_translate::get($en),
            "baidu_free" => baidu_translate_free::get($en),
            "tencent"    => tencent_translate::get($en),
            "deepl_free" => deepl_translate_free::get($en),
            default      => false,
        };
    }

    private function set_db($en, $zh)
    {
        $stmt = $this->db->prepare("INSERT INTO translation (en, zh) VALUES (:en, :zh)");
        $stmt->bindValue(":en", $en, SQLITE3_TEXT);
        $stmt->bindValue(":zh", $zh, SQLITE3_TEXT);
        $stmt->execute();
    }
}

class baidu_translate
{
    private static function access()
    {
        if (is_file(ROOT . "baidu.access.token")) {
            $access_token = file_get_contents(ROOT . "/baidu.access.token");
            if ($access_token !== false) {
                return json_decode($access_token, true)["access_token"];
            }
        }

        $conf = config::get("baidu");
        if ($conf === false) {
            return false;
        }

        $rtn = curl_require("https://aip.baidubce.com/oauth/2.0/token?client_id=" . $conf["client_id"] . "&client_secret=" . $conf["client_secret"] . "&grant_type=client_credentials", "", [
            "Content-Type: application/json",
            "Accept: application/json"
        ]);

        if ($rtn === false) {
            return false;
        }

        $rtn = json_decode($rtn, true);

        if (!isset($rtn["access_token"])) {
            log_message('error', "access请求失败: " . var_export($rtn, true));
            return false;
        }

        if (file_put_contents(ROOT . "baidu.access.token", $rtn) === false) {
            log_message('error', "access配置写入缓存失败: " . var_export($rtn, true));
        }

        return $rtn["access_token"];
    }

    public static function get($en)
    {
        $access = self::access();
        if ($access === false) {
            log_message('error', "access配置读取失败: " . var_export($access, true));
            return false;
        }

        $res = curl_require("https://aip.baidubce.com/rpc/2.0/mt/texttrans/v1?access_token=" . $access, json_encode([
            "from"    => "en",
            "to"      => "zh",
            "q"       => $en,
            "termIds" => "",
        ]));

        if ($res === false) {
            return false;
        }

        $res = json_decode($res, true);

        if (!isset($res["result"]["trans_result"][0]["dst"])) {
            log_message('error', "接口请求错误: " . var_export($res, true));
            return false;
        }

        return $res["result"]["trans_result"][0]["dst"] ?? false;
    }
}

class baidu_translate_free
{
    public static function get($en)
    {
        $conf = config::get("baidu_free");
        if ($conf === false) {
            log_message('error', "配置读取失败: " . var_export($conf, true));
            return false;
        }

        $appid = $conf["appid"];
        $key   = $conf["key"];
        $salt  = rand(10000, 99999);
        $sign  = md5($appid . $en . $salt . $key);
        $res   = curl_require("https://fanyi-api.baidu.com/api/trans/vip/translate?" . http_build_query([
            "q"     => $en,
            "from"  => "en",
            "to"    => "zh",
            "appid" => $appid,
            "salt"  => $salt,
            "sign"  => $sign
        ]));

        if ($res === false) {
            return false;
        }

        $res = json_decode($res, true);

        if (!isset($res["trans_result"][0]["dst"])) {
            log_message('error', "接口请求错误: " . var_export($res, true));
            return false;
        }

        return $res["trans_result"][0]["dst"] ?? false;
    }
}

class tencent_translate
{
    private static function sign($key, $msg)
    {
        return hash_hmac("sha256", $msg, $key, true);
    }

    private static function getAuthorization($secret_id, $secret_key, $timestamp, $payload)
    {
        $service                = "tmt";
        $host                   = "tmt.tencentcloudapi.com";
        $action                 = "TextTranslate";
        $algorithm              = "TC3-HMAC-SHA256";
        $date                   = gmdate("Y-m-d", $timestamp);
        $http_request_method    = "POST";
        $canonical_uri          = "/";
        $canonical_querystring  = "";
        $ct                     = "application/json; charset=utf-8";
        $canonical_headers      = "content-type:" . $ct . "\nhost:" . $host . "\nx-tc-action:" . strtolower($action) . "\n";
        $signed_headers         = "content-type;host;x-tc-action";
        $hashed_request_payload = hash("sha256", $payload);
        $canonical_request      = "$http_request_method\n$canonical_uri\n$canonical_querystring\n$canonical_headers\n$signed_headers\n$hashed_request_payload";
        $credential_scope       = "$date/$service/tc3_request";

        $hashed_canonical_request = hash("sha256", $canonical_request);
        $string_to_sign           = "$algorithm\n$timestamp\n$credential_scope\n$hashed_canonical_request";

        $secret_date    = self::sign("TC3" . $secret_key, $date);
        $secret_service = self::sign($secret_date, $service);
        $secret_signing = self::sign($secret_service, "tc3_request");
        $signature      = hash_hmac("sha256", $string_to_sign, $secret_signing);

        return "$algorithm Credential=$secret_id/$credential_scope, SignedHeaders=$signed_headers, Signature=$signature";
    }

    public static function get($en)
    {
        $conf = Config::get("tencent");
        if ($conf === false) {
            log_message('error', "配置读取失败: " . var_export($conf, true));
            return false;
        }

        $time          = time();
        $payload       = json_encode(
            [
                "SourceText" => $en,
                "Source"     => "en",
                "Target"     => "zh",
                "ProjectId"  => 0
            ]
        );
        $authorization = self::getAuthorization($conf['secret_id'], $conf['secret_key'], $time, $payload);
        $res           = curl_require("https://tmt.tencentcloudapi.com", $payload, [
            "Authorization: " . $authorization,
            "Content-Type: application/json; charset=utf-8",
            "Host: tmt.tencentcloudapi.com",
            "X-TC-Action: TextTranslate",
            "X-TC-Timestamp: " . $time,
            "X-TC-Version: 2018-03-21",
            "X-TC-Region: ap-guangzhou",
        ]);

        if ($res === false) {
            return false;
        }

        $res = json_decode($res, true);

        if (!isset($res["Response"]["TargetText"])) {
            log_message('error', "接口请求错误: " . var_export($res, true));
            return false;
        }
        return $res["Response"]["TargetText"] ?? false;
    }
}

class deepl_translate_free
{
    public static function get($en)
    {
    }
}
