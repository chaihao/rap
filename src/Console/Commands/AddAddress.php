<?php

namespace Chaihao\Rap\Console\Commands;

use App\Models\Ulity\SysAddress;
use Illuminate\Console\Command;

class AddAddress extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rap:add-address';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '添加地址数据';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $this->setData();
        } catch (\Exception $e) {
            $this->error('添加地址数据失败：' . $e->getMessage());
            return 1;
        }
        return 0;
    }

    /**
     * 设置地址数据
     */
    public function setData()
    {
        $this->info('开始添加地址数据');
        $jsonData = file_get_contents(__DIR__ . '/../../resources/json/address_data.json');

        if (!$jsonData) {
            throw new \Exception('无法读取地址数据文件');
        }

        $data = json_decode($jsonData, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('JSON 解析错误：' . json_last_error_msg());
        }

        \DB::beginTransaction();
        try {
            SysAddress::truncate();
            $this->insertAddressData($data);
            \DB::commit();
            $this->info('地址数据添加成功');
        } catch (\Exception $e) {
            \DB::rollBack();
            throw $e;
        }
    }
    /**
     * 插入地址数据
     * @param array $data 地址数据
     * @param int $parentCode 父级代码
     */
    private function insertAddressData($data, $parentCode = 0)
    {
        $batchData = [];
        $batchSize = 300;
        $totalCount = count($data);
        $progress = $this->output->createProgressBar($totalCount);

        try {
            foreach ($data as $item) {
                $addressData = [
                    'code' => $item['code'],
                    'name' => $item['name'],
                    'parent_code' => $parentCode,
                ];
                $batchData[] = $addressData;

                if (count($batchData) >= $batchSize) {
                    SysAddress::insert($batchData);
                    $batchData = [];
                }

                if (isset($item['children']) && !empty($item['children'])) {
                    $this->insertAddressData($item['children'], $item['code']);
                }

                $progress->advance();
            }

            if (!empty($batchData)) {
                SysAddress::insert($batchData);
            }

            $progress->finish();
            $this->line(''); // 添加换行

        } catch (\Exception $e) {
            \Log::error('插入地址数据失败', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            throw $e;
        }
    }
}
