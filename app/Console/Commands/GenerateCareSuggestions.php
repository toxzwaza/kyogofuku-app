<?php

namespace App\Console\Commands;

use App\Services\CareSuggestionGenerator;
use Illuminate\Console\Command;

class GenerateCareSuggestions extends Command
{
    protected $signature = 'care:generate';

    protected $description = 'ケア提案（オーバービューの今日のケアリスト）を生成する';

    public function handle(CareSuggestionGenerator $generator): int
    {
        $this->info('ケア提案を生成中...');
        $count = $generator->generate();
        $this->info("生成完了: {$count} 件");

        return self::SUCCESS;
    }
}
