<?php

use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

// 检测日期
if (!function_exists('dateFormat')) {
    function dateFormat(array $date)
    {
        try {
            $start = Carbon::parse(reset($date))->timezone('PRC');
            $end = Carbon::parse(end($date))->timezone('PRC');

            if ($start->toTimeString() == '00:00:00') {
                $start = $start->startOfDay();
            }

            if ($end->toTimeString() == '00:00:00') {
                $end = $end->endOfDay();
            }

            return [$start->toDateTimeString(), $end->toDateTimeString()];
        } catch (Exception $e) {
            return [
                'status' => false,
                'msg' => $e->getMessage(),
            ];
        }
    }
}


if (!function_exists('getClientIp')) {
    /**
     * 获取客户端IP地址
     *
     * @return string
     */
    function getClientIP(): string
    {
        $ip = request()->ip();

        if (!$ip || $ip === '::1' || $ip === '127.0.0.1') {
            $ip = request()->header('X-Forwarded-For');
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
        }

        if (!$ip) {
            $ip = request()->header('X-Real-IP');
        }

        return $ip ?: '0.0.0.0';
    }
}



/**
 * 获取日期段间隔的所有日期
 */
if (!function_exists('dateRange')) {
    function dateRange(array $date, string $type = 'day')
    {
        $startDate = reset($date);
        $endDate = end($date);
        if (!validateData($startDate) || !validateData($endDate)) {
            return Response::make()->failed('日期格式输入有误');
        }
        if (strtotime($startDate) > strtotime($endDate)) {
            return Response::make()->failed('开始时间必须小于或等于结束时间');
        }
        $dateRange = [];
        $startDate = new DateTime($startDate);
        $endDate = new DateTime($endDate);

        while ($startDate <= $endDate) {
            if ($type == 'month') {
                $dateRange[] = $startDate->format('Y-m');
                $startDate->modify('+1 month');
            } else {
                $dateRange[] = $startDate->format('Y-m-d');
                $startDate->modify('+1 day');
            }
        }
        return $dateRange;
    }
}




/* *
 * 一些日期的计算
 * */
if (!function_exists('dateAnalysis')) {
    function dateAnalysis($tab)
    {
        $today    =   date("Y-m-d");
        $begin_time = $end_time = $today;
        switch ($tab) {
            case 1:
                $begin_time = $today; //今天
                $end_time   = $today; //今天
                break;
            case 2:
                $begin_time = date('Y-m-d', strtotime('-1 day')); //昨天
                $end_time   = date('Y-m-d', strtotime('-1 day')); //昨天
                break;
            case 3: //本周
                $w              =   date('w', strtotime($today));
                //$first =1 表示每周星期一为开始日期 0表示每周日为开始日期
                $first  =   1;
                $week_start =   date('Y-m-d', strtotime("$today -" . ($w ? $w - $first : 6) . ' days'));

                $begin_time =   $week_start; //本周
                $end_time   =   date('Y-m-d');
                break;
            case 4: //上周
                $w              =   date('w', strtotime($today));
                //$first =1 表示每周星期一为开始日期 0表示每周日为开始日期
                $first  =   1;
                $week_start =   date('Y-m-d', strtotime("$today -" . ($w ? $w - $first : 6) . ' days'));

                $begin_time =   date('Y-m-d', strtotime($week_start)); //上周
                $end_time   =   $week_start;
                break;
            case 5: //本月
                $begin_time =   date("Y-m-01"); //本月
                $end_time   =   $today;
                break;
            case 6: //上个月
                $begin_time =   date("Y-m-01", strtotime('-1 month')); //上个月
                $end_time   =   $today;
                break;
            default:
                if ($tab) {
                    //筛选指定日期
                    if (!validateData($tab)) {
                        return Response::make()->failed('日期格式输入有误');
                    }
                    $begin_time = $tab;
                    $end_time   = $tab;
                }
        }

        return [
            'begin_time'  => $begin_time,
            'end_time'    => $end_time
        ];
    }
}


/**
 * 校验日期格式
 * */
if (!function_exists('validateData')) {
    function validateData($date)
    {
        if (date('Y-m-d H:i:s', strtotime($date)) == $date || date('Y-m-d', strtotime($date)) == $date) {
            return true;
        }
        return false;
    }
}


/**
 * 随机传递数据，判断是否符合格式
 * */
if (!function_exists('paramsTransfer')) {
    function paramsTransfer($date)
    {
        $begin_time = $end_time = date("Y-m-d");

        foreach ($date as $value) {
            if (!$value) {
                continue;
            }
            if (count($value) == 2) {
                $begin_time = $value[0];
                $end_time   = $value[1];
                return [$begin_time, $end_time];
            }
        }
        return [$begin_time, $end_time];
    }
}

//时间差计算
if (!function_exists('diffDate')) {
    function diffDate($startDate, $endDate, $dateUnit = 'day')
    {
        try {
            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);

            switch ($dateUnit) {
                case 'day':
                    return $end->diffInDays($start);
                    // 可以添加其他单位的计算
                default:
                    return $end->diffInDays($start);
            }
        } catch (Exception $e) {
            return 0;
        }
    }
}

/**
 * 设置分页格式化 给前端使用
 * @param $data
 * @return array
 */
if (!function_exists('pageSetting')) {
    function pageSetting($data)
    {
        $info                              = [];
        $info['items']                     = $data->items();
        $info['paginator']['count']        = $data->count();
        $info['paginator']['currentPage']  = $data->currentPage();
        $info['paginator']['hasMorePages'] = $data->hasMorePages();
        $info['paginator']['lastPage']     = $data->lastPage();
        $info['paginator']['onFirstPage']  = $data->onFirstPage();
        $info['paginator']['perPage']      = $data->perPage();
        $info['paginator']['total']        = $data->total();
        return $info;
    }
}


if (!function_exists('contentImgSrcReplace')) {
    function contentImgSrcReplace($content)
    {
        //匹配所有图片
        $content = stripslashes($content);
        //        preg_match_all('/<img[^>]*src="([^"]*)"[^>]*>/i',$content, $images);
        // preg_match_all('/src="([^"]*)"[^>]*>/i', $content, $images);

        preg_match_all('/<img.*?src=[\'|\"](.*?(?:[\.gif|\.jpg|\.jpeg]|\.png]|.bmp]))[\'|\"].*?[\/]?>/i', $content, $images);
        //如果匹配到了图片进行处理
        if (count($images) == 2) {
            $imagesOnly = array_unique($images[1]);
            foreach ($imagesOnly as $image) {
                //如果是本地图片
                if (!strstr($image, 'http')) {
                    // $local_url = \Rap\Rap::image($image, '', 'aliOss')[0];
                    $local_url =  imageUrl($image);

                    //替换原来的图片地址
                    $content = str_replace($image, $local_url, $content);
                }
            }
            return $content;
        } else {
            return $content;
        }
    }
}


/**
 * 生成校验参数 调用接口
 * @param $app_id
 * @param $secret
 * @return string
 */
if (!function_exists('tokenSignature')) {
    function tokenSignature($appid, $secret)
    {
        //获取当前时间，服务端验证时间正负误不得大于5分钟
        $time = now()->toDateTimeString();
        //生成随机校验串
        $echostr = Str::random(16);
        // 模块iD $appid 秘钥 $secret
        # 组合一起，如果当前请求中还有其他GET参数需要一并组合校验
        $p = array_values(compact('time', 'echostr', 'secret', 'appid'));
        # 自然排序法排序
        sort($p);
        //组装为字符串参数
        $p = join('&', $p);
        //通过hash_hmac函数sha256加密请求参数体，秘钥作为秘钥
        $signature = hash_hmac('sha256', $p, $secret);
        //组装与打印参数路径字符串
        $params = http_build_query(compact('time', 'echostr', 'appid', 'signature'));

        return $params;
    }
}


if (!function_exists('paginateFormat')) {
    function paginateFormat($data)
    {
        return [
            'data' => $data->items(),
            'total' => $data->total(),
            'size' => $data->perPage(),
            'page' => $data->currentPage(),
            // 'last_page' => $data->lastPage(),
            // 'from' => $data->firstItem(),
            // 'to' => $data->lastItem(),
            // 'next_page_url' => $data->nextPageUrl(),
            // 'prev_page_url' => $data->previousPageUrl(),
        ];
    }
}

if (!function_exists('imageUrl')) {
    function imageUrl($url)
    {
        if (empty($url)) {
            return '';
        }
        if (strpos($url, 'aliyuncs.com') !== false && strpos($url, 'dtop') !== false) {
            $url = parse_url($url, PHP_URL_PATH);
        }
        $parseUrl = parse_url($url);
        if (!isset($parseUrl['scheme']) || empty($parseUrl['scheme'])) {
            return trim(config('dtop.domain_image'), '/') . '/' . trim($url, '/');
        }
        return $url;
    }
}
if (!function_exists('apkUrl')) {
    function apkUrl($url)
    {
        if (empty($url)) {
            return '';
        }
        if (strpos($url, 'aliyuncs.com') !== false && strpos($url, 'dtop') !== false) {
            $url = parse_url($url, PHP_URL_PATH);
        }
        $parseUrl = parse_url($url);
        if (!isset($parseUrl['scheme']) || empty($parseUrl['scheme'])) {
            return trim(config('dtop.domain_down'), '/') . '/' . trim($url, '/');
        }
        return $url;
    }
}




//钉钉异常发送通知
if (!function_exists('request_by_curl')) {
    function request_by_curl($remote_server, $post_string)
    {
        $ch = curl_init();
        try {
            curl_setopt_array($ch, [
                CURLOPT_URL => $remote_server,
                CURLOPT_POST => 1,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json;charset=utf-8'],
                CURLOPT_POSTFIELDS => $post_string,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0
            ]);

            $data = curl_exec($ch);

            if (curl_errno($ch)) {
                throw new Exception(curl_error($ch));
            }

            return $data;
        } catch (Exception $e) {
            Log::error('CURL请求失败: ' . $e->getMessage());
            return false;
        } finally {
            curl_close($ch);
        }
    }
}


/**
 * 获取当前登录用户ID
 */
if (! function_exists('getUid')) {
    /**
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    function getUid()
    {
        try {
            auth(config('jwt.defaults.guard'))->check();
            // 检查用户是否登录
            $token = JWTAuth::parseToken(); // 解析 Token
            $staff = $token->authenticate(); // 从 Token 获取用户信息
            if ($staff) {
                return $staff->getAttribute("id");
            }
            return 0;
        } catch (Exception $e) {
            return  0;
        }
    }
}


if (!function_exists('hash256')) {
    /**
     * 生成自定义 SHA256 哈希值
     * 处理规则：移除每两位十六进制数字中开头的0
     * 
     * @param string $input 需要哈希的输入字符串
     * @return string 处理后的哈希字符串
     */
    function hash256($input)
    {
        $hash = hash("sha256", mb_convert_encoding($input, 'UTF-8', 'ISO-8859-1'));
        $output = "";
        foreach (str_split($hash, 2) as $key => $value) {
            if (strpos($value, "0") === 0) {
                $output .= str_replace("0", "", $value);
            } else {
                $output .= $value;
            }
        }
        return $output;
    }
}

use Illuminate\Support\Facades\DB;

function dumpSQL()
{
    DB::listen(function ($query) {
        $sql = str_replace('?', '%s', $query->sql);
        $sql = vsprintf($sql, $query->bindings);
        $sql = str_replace("\\", "", $sql);
        dump($sql);
    });
}

function printSQL($channel = 'sql')
{
    DB::listen(function ($query) use ($channel) {
        $sql = str_replace('?', '%s', $query->sql);
        $sql = vsprintf($sql, $query->bindings);
        $sql = str_replace("\\", "", $sql);
        Log::channel($channel)->info($sql);
    });
}
