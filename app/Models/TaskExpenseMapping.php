<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskExpenseMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_title',
        'expense_category',
        'count',
        'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'count' => 'integer',
    ];

    /**
     * タスク名と費用項目の紐づけを記録または更新
     */
    public static function recordMapping(string $taskTitle, string $expenseCategory): void
    {
        $mapping = self::where('task_title', $taskTitle)
            ->where('expense_category', $expenseCategory)
            ->first();

        if ($mapping) {
            $mapping->increment('count');
            $mapping->update(['last_used_at' => now()]);
        } else {
            self::create([
                'task_title' => $taskTitle,
                'expense_category' => $expenseCategory,
                'count' => 1,
                'last_used_at' => now(),
            ]);
        }
    }

    /**
     * タスク名から費用項目を推測
     */
    public static function predictExpenseCategory(string $taskTitle): ?string
    {
        if (empty($taskTitle)) {
            return null;
        }

        // 完全一致を優先
        $exactMatch = self::where('task_title', $taskTitle)
            ->orderBy('count', 'desc')
            ->orderBy('last_used_at', 'desc')
            ->first();

        if ($exactMatch) {
            return $exactMatch->expense_category;
        }

        // 部分一致で検索（タスク名に含まれる文字列で検索）
        $partialMatches = self::where('task_title', 'LIKE', "%{$taskTitle}%")
            ->orWhere(function($query) use ($taskTitle) {
                // 逆方向の部分一致（タスク名が既存のタスク名に含まれる場合）
                $query->whereRaw('? LIKE CONCAT("%", task_title, "%")', [$taskTitle]);
            })
            ->orderBy('count', 'desc')
            ->orderBy('last_used_at', 'desc')
            ->get();

        if ($partialMatches->isNotEmpty()) {
            // 最も使用回数が多いものを返す
            return $partialMatches->first()->expense_category;
        }

        // 類似度計算（レーベンシュタイン距離など）で最も近いものを検索
        $allMappings = self::select('task_title', 'expense_category', 'count', 'last_used_at')
            ->get()
            ->map(function($mapping) use ($taskTitle) {
                $similarity = self::calculateSimilarity($taskTitle, $mapping->task_title);
                return [
                    'expense_category' => $mapping->expense_category,
                    'similarity' => $similarity,
                    'count' => $mapping->count,
                    'last_used_at' => $mapping->last_used_at,
                ];
            })
            ->filter(function($item) {
                // 類似度が0.5以上の場合のみ
                return $item['similarity'] >= 0.5;
            })
            ->sortByDesc(function($item) {
                // 類似度、使用回数、最終使用日時でソート
                return [
                    $item['similarity'],
                    $item['count'],
                    $item['last_used_at'] ? $item['last_used_at']->timestamp : 0,
                ];
            })
            ->first();

        return $allMappings['expense_category'] ?? null;
    }

    /**
     * 文字列の類似度を計算（簡易版：共通部分の割合）
     */
    private static function calculateSimilarity(string $str1, string $str2): float
    {
        $str1 = mb_strtolower($str1);
        $str2 = mb_strtolower($str2);

        // 完全一致
        if ($str1 === $str2) {
            return 1.0;
        }

        // 一方が他方に含まれる場合
        if (mb_strpos($str1, $str2) !== false || mb_strpos($str2, $str1) !== false) {
            $minLen = min(mb_strlen($str1), mb_strlen($str2));
            $maxLen = max(mb_strlen($str1), mb_strlen($str2));
            return $minLen / $maxLen;
        }

        // 共通文字数を計算
        $commonChars = 0;
        $str1Chars = mb_str_split($str1);
        $str2Chars = mb_str_split($str2);
        
        foreach ($str1Chars as $char) {
            if (in_array($char, $str2Chars)) {
                $commonChars++;
                $key = array_search($char, $str2Chars);
                unset($str2Chars[$key]);
            }
        }

        $maxLen = max(mb_strlen($str1), mb_strlen($str2));
        return $maxLen > 0 ? $commonChars / $maxLen : 0.0;
    }
}
