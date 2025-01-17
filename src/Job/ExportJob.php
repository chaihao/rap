<?php

namespace Chaihao\Rap\Job;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Excel;
use Throwable;
use ZipArchive;

class ExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $fileName;
    protected $params;
    protected $totalPage;
    protected string $filePathSuffix = 'export/';
    protected $model;
    protected $page;
    public function __construct($model, $fileName,  $params, $page, int $totalPage)
    {
        $this->fileName = $fileName;
        $this->params = $params;
        $this->totalPage = $totalPage;
        $this->model = $model;
        $this->page = $page;
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
            $filename = $this->filePathSuffix . $this->fileName . '_' . ($this->page + 1) . '.csv';
            $this->model->store($filename, 'public', Excel::CSV);
            Redis::incr($this->fileName);

            if (Redis::get($this->fileName) == ($this->totalPage + 1)) {
                // 文件绝对路径
                $zip_file = Storage::disk('public')->path($this->filePathSuffix . $this->fileName) . '.zip';
                $zip = new ZipArchive();
                $zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
                for ($i = 1; $i <= ($this->totalPage + 1); $i++) {
                    // 将 .csv 文件添加至 .zip 文件
                    $value = $this->filePathSuffix . $this->fileName . '_' . $i . '.csv';
                    $zip->addFile(Storage::disk('public')->path($value), $value);
                }
                $zip->close();
                $fileExportName = $this->fileName . '.zip';

                $downloadUrl = Storage::disk('public')->url($this->filePathSuffix . $fileExportName);
                // 记录下载链接到日志
                Log::info('下载链接: ' . $downloadUrl);
                // 删除导出限制
                $redisKey = 'AsynchronousExportRedisKey';
                Redis::delete($redisKey);
                Redis::delete($this->fileName); // 删除计数器
                Log::info($downloadUrl);
            }
        } catch (Throwable $t) {
            Log::info($t->getMessage());
        }
    }
}
