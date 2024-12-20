<?php

namespace Chaihao\Rap;

use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use DateTime;
use DateInterval;
use Exception;

/**
 * 格式化日期范围
 * @param array $date 包含开始和结束日期的数组
 * @return array 格式化后的日期时间或错误信息
 */
if (!function_exists('dateFormat')) {
    function dateFormat(array $date): array
    {
        try {
            [$startDate, $endDate] = [reset($date), end($date)];

            $start = Carbon::parse($startDate)->timezone('PRC');
            $end = Carbon::parse($endDate)->timezone('PRC');

            // 优化日期处理逻辑
            $start = $start->format('H:i:s') === '00:00:00' ? $start->startOfDay() : $start;
            $end = $end->format('H:i:s') === '00:00:00' ? $end->endOfDay() : $end;

            return [$start->toDateTimeString(), $end->toDateTimeString()];
        } catch (Exception $e) {
            Log::error('日期格式化错误: ' . $e->getMessage());
            return ['status' => false, 'msg' => '日期格式无效'];
        }
    }
}

/**
 * 获取客户端真实IP地址
 */
if (!function_exists('getClientIP')) {
    function getClientIP(): string
    {
        $request = request();
        $ip = $request->ip();

        // 本地环境IP处理
        if (!$ip || in_array($ip, ['::1', '127.0.0.1'], true)) {
            $forwardedIp = $request->header('X-Forwarded-For');
            if ($forwardedIp) {
                $ipArray = array_map('trim', explode(',', $forwardedIp));
                $ip = $ipArray[0];
            }
        }

        return $ip ?: $request->header('X-Real-IP', '0.0.0.0');
    }
}

/**
 * 获取日期范围内的所有日期
 */
if (!function_exists('dateRange')) {
    function dateRange(array $date, string $type = 'day'): array|Response
    {
        [$startDate, $endDate] = [$date[0], end($date)];

        if (!validateData($startDate) || !validateData($endDate)) {
            return Response::make()->failed('日期格式输入有误');
        }

        if (strtotime($startDate) > strtotime($endDate)) {
            return Response::make()->failed('开始时间必须小于或等于结束时间');
        }

        $dateRange = [];
        $current = new DateTime($startDate);
        $end = new DateTime($endDate);
        $interval = new DateInterval($type === 'month' ? 'P1M' : 'P1D');
        $format = $type === 'month' ? 'Y-m' : 'Y-m-d';

        while ($current <= $end) {
            $dateRange[] = $current->format($format);
            $current->add($interval);
        }

        return $dateRange;
    }
}

/**
 * 一些日期的计算
 */
if (!function_exists('dateAnalysis')) {
    function dateAnalysis($tab)
    {
        $today = Carbon::today();
        $begin_time = $end_time = $today->format('Y-m-d');

        try {
            switch ($tab) {
                case 1: // 今天
                    break;

                case 2: // 昨天
                    $begin_time = $end_time = $today->copy()->subDay()->format('Y-m-d');
                    break;

                case 3: // 本周
                    $begin_time = $today->copy()->startOfWeek()->format('Y-m-d');
                    break;

                case 4: // 上周
                    $lastWeek = $today->copy()->subWeek();
                    $begin_time = $lastWeek->startOfWeek()->format('Y-m-d');
                    $end_time = $lastWeek->endOfWeek()->format('Y-m-d');
                    break;

                case 5: // 本月
                    $begin_time = $today->copy()->startOfMonth()->format('Y-m-d');
                    break;

                case 6: // 上月
                    $begin_time = $today->copy()->subMonth()->startOfMonth()->format('Y-m-d');
                    $end_time = $today->copy()->subMonth()->endOfMonth()->format('Y-m-d');
                    break;

                default:
                    if ($tab && !validateData($tab)) {
                        return Response::make()->failed('日期格式输入有误');
                    }
                    $begin_time = $end_time = $tab ?: $begin_time;
            }

            return [
                'begin_time' => $begin_time,
                'end_time' => $end_time
            ];
        } catch (Exception $e) {
            Log::error('日期分析错误: ' . $e->getMessage());
            return Response::make()->failed('日期处理出错');
        }
    }
}

/**
 * 校验日期格式
 */
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
 */
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
    function contentImgSrcReplace($content): string
    {
        if (empty($content)) {
            return '';
        }

        $content = stripslashes($content);

        // 优化正则表达式，修复文件扩展名匹配问题
        $pattern = '/<img[^>]*?src=[\'"]([^\'"]+'
            . '(?:\.(?:gif|jpe?g|png|bmp))?)[\'"][^>]*>/i';

        return preg_replace_callback($pattern, function ($matches) {
            $imageUrl = $matches[1];
            // 仅处理非http开头的本地图片
            if (!preg_match('/^https?:\/\//i', $imageUrl)) {
                return str_replace($imageUrl, imageUrl($imageUrl), $matches[0]);
            }
            return $matches[0];
        }, $content);
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

/**
 * 分页数据格式化（简化版）
 */
if (!function_exists('paginateFormat')) {
    function paginateFormat($data): array
    {
        return [
            'data' => $data->items(),
            'total' => $data->total(),
            'size' => $data->perPage(),
            'page' => $data->currentPage(),
        ];
    }
}

/**
 * 图片URL处理
 */
if (!function_exists('imageUrl')) {
    function imageUrl(?string $url): string
    {
        if (empty($url)) {
            return '';
        }

        // 处理阿里云OSS的URL
        if (str_contains($url, 'aliyuncs.com') && str_contains($url, 'dtop')) {
            $url = parse_url($url, PHP_URL_PATH);
        }

        $parseUrl = parse_url($url);
        if (!isset($parseUrl['scheme'])) {
            return rtrim(config('dtop.domain_image'), '/') . '/' . ltrim($url, '/');
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

/**
 * CURL请求封装
 */
if (!function_exists('request_by_curl')) {
    function request_by_curl(string $remote_server, string $post_string)
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

if (! function_exists('getUid')) {
    /**
     * 获取当前登录用户ID
     * @return int 用户ID，未登录返回0
     */
    function getUid(): int
    {
        try {
            if (!auth(config('jwt.defaults.guard'))->check()) {
                return 0;
            }

            $user = auth(config('jwt.defaults.guard'))->user();
            return $user ? $user->id : 0;
        } catch (Exception $e) {
            return 0;
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
