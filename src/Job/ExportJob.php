<?php

namespace Chaihao\Rap\Job;

use Throwable;
use ZipArchive;
use Maatwebsite\Excel\Excel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\Export\WallpapersExportService;
use Chaihao\Rap\Services\Export\BaseExportService;

class ExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $serviceClass;
    protected $fileName;
    protected $params;
    protected $page;
    protected $limit;
    protected $totalPage;
    protected $redisKey;
    protected $exportService;
    public function __construct($serviceClass, $fileName, $params, $page, $limit, $totalPage, $redisKey)
    {
        $this->serviceClass = $serviceClass;
        $this->fileName = $fileName;
        $this->params = $params;
        $this->page = $page;
        $this->limit = $limit;
        $this->totalPage = $totalPage;
        $this->redisKey = $redisKey;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            ini_set('memory_limit', '512M');
            $filename = $this->fileName . '_' . $this->page . '.csv';
            $service = app($this->serviceClass); // 获取服务类
            $service->setParams($this->params); // 设置参数
            $service->setPage($this->page); // 设置页码
            $service->setLimit($this->limit); // 设置限制
            $service->store($filename, 'public', Excel::CSV); // 存储CSV文件
            Redis::incr($this->fileName); // 增加计数器

            if (intval(Redis::get($this->fileName)) == intval($this->totalPage)) {
                $baseName = basename($this->fileName); // 获取文件名
                // 文件绝对路径
                $zip_file = Storage::disk('public')->path($baseName . '.zip');
                $zip = new ZipArchive();
                $zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE); // 创建zip文件
                for ($i = 0; $i <= $this->totalPage; $i++) {
                    // 将 .csv 文件添加至 .zip 文件
                    $csvFile = $this->fileName . '_' .  $i . '.csv';
                    if (Storage::disk('public')->exists($csvFile)) {
                        $zip->addFile(Storage::disk('public')->path($csvFile), $csvFile);
                    }
                }
                $zip->close(); // 关闭zip文件
                for ($i = 0; $i <= $this->totalPage; $i++) {
                    $csvFile = $this->fileName . '_' .  $i . '.csv';
                    // 添加至zip文件后删除csv文件
                    Storage::disk('public')->delete($csvFile);
                }
                // $downloadUrl = rtrim(env('APP_URL'), '/') . Storage::url($baseName . '.zip'); // 返回文件URL
                $downloadUrl = asset('storage/' . $baseName . '.zip');
                // 记录下载链接到日志
                Log::info('下载链接: ' . $downloadUrl);
                // 删除导出限制
                Redis::delete($this->redisKey);
                Redis::delete($this->fileName); // 删除计数器
                Log::info($downloadUrl);
                echo $downloadUrl;
            }
        } catch (Throwable $t) {
            Log::error($t->getMessage()); // 记录错误信息
        }
    }
}
