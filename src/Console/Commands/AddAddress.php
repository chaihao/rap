<?php

namespace Chaihao\Rap\Console\Commands;

use App\Models\Ulity\SysAddress;
use Illuminate\Console\Command;
use DB;

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
    protected $description = '添加地址库数据';

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
        $sqlFilePath = __DIR__ . '/../../resources/sql/sys_address.sql';

        if (!file_exists($sqlFilePath)) {
            throw new \Exception('无法读取地址数据文件');
        }

        $sql = file_get_contents($sqlFilePath);

        $this->info('开始清空地址数据');
        SysAddress::truncate();
        $this->info('清空地址数据成功');

        $this->info('开始添加地址数据');
        DB::beginTransaction();
        try {
            DB::statement($sql);
            DB::commit();
            $this->info('地址数据添加成功');
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
