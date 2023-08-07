<?php

namespace yjyer;

use Exception;

/**
 * 网络请求类
 */
class Http
{
    /*
     * PHP模拟GET请求
     */
    public static function get($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch));
        }
        curl_close($ch);
        
        return array($httpCode, $response);
    }

    // 使用递归方式处理数据值(支持多维数组)
    public function flattenArrayValue($value) {
        if (is_array($value)) {
            $flattenedValue = array();
            foreach ($value as $subValue) {
                $flattenedValue[] = $this->flattenArrayValue($subValue);
            }
            return implode(',', $flattenedValue);
        } else {
            return $value;
        }
    }

    public static function buildRequestData(array $formData): array
    {
        // 生成随机的boundary值
        $boundary = uniqid();

        // 设置请求头
        $headers = ['Content-Type: multipart/form-data; boundary=' . $boundary];

        // 获取文件数据(文件流)
        $fileStream = $formData['stream'];
        $binaryData = stream_get_contents($fileStream); // 转换为二进制
        unset($formData['stream']);

        // 构建请求体
        $bodyData = "";
        // 普通数据
        foreach ($formData as $key => $value) {
            $bodyData .= "--{$boundary}\r\n";
            $bodyData .= "Content-Disposition: form-data; name=\"{$key}\"\r\n";
            $bodyData .= "\r\n";
            $bodyData .= (new self())->flattenArrayValue($value). "\r\n";
        }
    
        // 附件数据
        if ($binaryData) {
            // 获取文件MimeType
            $fileInfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $fileInfo->buffer($binaryData);
            // 获取文件名称
            $metaData = stream_get_meta_data($fileStream);
            $fileName = basename($metaData['uri']);

            $bodyData .= "--{$boundary}\r\n";
            $bodyData .= "Content-Disposition: form-data; name=\"files\"; filename=\"" . $fileName . "\"\r\n";
            $bodyData .= "Content-Type: " . $mimeType . "\r\n";
            $bodyData .= "Content-Transfer-Encoding: binary\r\n";
            $bodyData .= "\r\n";
            $bodyData .= $binaryData. "\r\n";
        }
        // 结束标识
        $bodyData .= "--{$boundary}--\r\n";
    
        return ['headers' => $headers, 'data' => $bodyData];
    }

    /*
     * PHP模拟POST请求
     */
    public static function post($url, $data)
    {
        // 生成请求体数据
        $reqData = self::buildRequestData($data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        // HTTP请求头中"Accept-Encoding: "的值。支持的编码有"identity"，"deflate"和"gzip"。如果为空字符串""，请求头会发送所有支持的编码类型。
        curl_setopt($ch, CURLOPT_ENCODING, "gzip, deflate, identity"); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $reqData['headers']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $reqData['data']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 480); // 超时时间480秒 即 8分钟

        // curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch));
        }
        curl_close($ch);

        return array($httpCode, $response);
    }
}