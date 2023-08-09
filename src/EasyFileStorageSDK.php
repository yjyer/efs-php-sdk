<?php

namespace yjyer;

use Exception;

/**
 * 附件管理类
 */
class EasyFileStorageSDK
{
    private const BASE_URL = '/api/file';

    private static function response(array $params): array
    {
        list($httpCode, $data) = $params;
        if ($httpCode != 200) {
            throw new Exception('上传异常');
        }
        $retData = json_decode($data, true);
        if ($retData['code']==0) {
            throw new Exception($retData['msg']);
        }

        return $retData['data'];
    }

    private static function requestApi(string $type, string $url, array $data): array
    {
        // 获取配置参数
        $config = config('efsconfig');
        if (empty($config)) {
            throw new Exception('请到config目录完成efsconfig配置');
        }
        $configField = ['api_url', 'app_id', 'app_secret'];
        foreach($configField as $key => $val) {
            if (empty($val)) {
                throw new Exception('请配置'. $key);
            }
        }

        // 请求参数
        $params = array_merge($data, [
            'app_id' => $config['app_id'] ?? '',
            'app_secret' => $config['app_secret'] ?? '',
        ]);

        $url = $config['api_url']. $url;
        if ($type=='get') {
            $params = http_build_query($params);
            $url .= '?'. $params;
            $result = Http::get($url);
        } else {
            $result = Http::post($url, $params);
        }

        return self::response($result);
    }


    /**
     * 上传附件
     * @param resource $data['stream'] 附件资源(文件流)
     * @param string   $data['directory'] 存储目录
     * @param array    $data['extend_data'] 扩展数据
     * @return
     */
    public static function upload(array $data)
    {
        if (!isset($data['stream'])) {
            throw new Exception('请选择要上传的附件');
        }
        if (!is_resource($data['stream']) || !get_resource_type($data['stream']) === 'stream') {
            throw new Exception('附件必须是文件流类型');
        }

        $url = self::BASE_URL. "/Attachment/upload";
        return self::requestApi('post', $url, $data);
    }

    /**
     * 查找附件
     * @param string $fileName 附件名称
     * @return 
     */
    public static function find(string $fileName)
    {
        $url = self::BASE_URL. "/Attachment/find";
        return self::requestApi('get', $url, [
            'file_name' => $fileName
        ]);
    }

    /**
     * 删除附件
     * @param string $fileName 附件名称
     * @return 
     */
    public static function delete(string $fileName)
    {
        $url = self::BASE_URL. "/Attachment/delete";
        return self::requestApi('post', $url, [
            'file_name' => $fileName
        ]);
    }

    // 生成MD5签名
    private function makeSign(string $fileName, array $params, string $key)
    {
        $kvs = array();
        ksort($params);
        foreach ($params as $index => $item) {
            if ($index == 'sign' || $item === null || $item === '') {
                continue;
            }
            array_push($kvs, $index . "=" . $item);
        }
        array_push($kvs, "key=" . $key);
        $signStr = $fileName.'?'.implode('&', $kvs);
        $sign = strtoupper(md5($signStr));

        return $sign;
    }

    /**
     * 获取附件预览地址
     * @param string $url    附件路径
     * @param bool   $ori    是否原图
     * @param array  $params 扩展数据 
     * @return 
     */
    public static function url(string $url, bool $ori = false, array $params = [])
    {
        // 获取配置参数
        $config = config('efsconfig');
        if (empty($config)) {
            throw new Exception('请到config目录完成efsconfig配置');
        }
        $configField = ['bucket_name', 'api_url', 'app_secret'];
        foreach($configField as $key => $val) {
            if (empty($val)) {
                throw new Exception('请配置'. $key);
            }
        }

        $fileName = basename($url);
        if ($ori) {
            // 查看原图，将附件路径替换成原图路径
            $oriFileName = str_replace('.', '_ori.', $fileName);
            $url = str_replace($fileName, $oriFileName, $url);
            $fileName = $oriFileName;
        }
        // 生成签名
        $sign = (new self())->makeSign($fileName, $params, $config['app_secret']);
        $url = $config['api_url'].'/f/'.$config['bucket_name']. $url .'?sign='. $sign;
        if (empty($params)) {
            return $url;
        }
        $url .= '&'. http_build_query($params);

        return $url;
    }
}
