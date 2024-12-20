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
    protected $signature = 'app:add-address';

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
        $this->setData();
    }



    public function setData()
    {
        $this->info('开始添加地址数据');
        $jsonData = file_get_contents(__DIR__ . '/../../resources/json/address_data.json');

        $data = json_decode($jsonData, true);

        SysAddress::truncate();

        $this->insertAddressData($data);

        $this->info('地址数据添加成功');
    }

    private function insertAddressData($data, $parentCode = 0)
    {
        try {
            $batchData = [];
            $batchSize = 300; // 设置批量插入的最大数量

            foreach ($data as $item) {
                $addressData = [
                    'code' => $item['code'],
                    'name' => $item['name'],
                    'parent_code' => $parentCode,
                ];
                $batchData[] = $addressData;

                // 当批量数据达到300条时，执行插入操作
                if (count($batchData) >= $batchSize) {
                    SysAddress::insert($batchData);
                    $batchData = []; // 清空批量数据数组，准备下一批
                }

                if (isset($item['children']) && !empty($item['children'])) {
                    $this->insertAddressData($item['children'], $item['code']);
                }
            }

            // 插入剩余的数据（如果有的话）
            if (!empty($batchData)) {
                SysAddress::insert($batchData);
            }
        } catch (\Exception $e) {
            // 错误处理
            \Log::error('插入地址数据时发生错误: ' . $e->getMessage());
        }
    }
}
