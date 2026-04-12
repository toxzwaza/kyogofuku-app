<?php

namespace App\Services\NaturalLanguage;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class NlChatService
{
    private Client $http;

    private ToolExecutor $executor;

    private string $apiKey;

    private string $model;

    private const MAX_TOOL_ROUNDS = 5;

    private const SYSTEM_PROMPT = <<<'PROMPT'
あなたは京呉服平田の業務システム管理アシスタントです。
スタッフからの自然言語による指示を受けて、システムの操作を行います。

## ルール
- 日本語で応答してください。
- 操作結果は簡潔にまとめて報告してください。
- イベントや予約枠の検索は、まず list や get で情報を取得してから操作を行ってください。
- 曖昧な指示の場合は、確認してから実行してください。
- 複数のツールを組み合わせて1つの指示を達成してください。

## 店舗一覧
- 岡山店、城東店、浜店、福井店

## フォーム種別
- reservation: 振袖予約
- reservation_hakama: 袴予約
- document: 資料請求
- contact: お問い合わせ
PROMPT;

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.api_key', '');
        $this->model = config('services.anthropic.model', 'claude-sonnet-4-20250514');
        $this->http = new Client([
            'base_uri' => 'https://api.anthropic.com/',
            'timeout' => 60,
            'verify' => false,
        ]);
        $this->executor = new ToolExecutor();
    }

    /**
     * 自然言語メッセージを受け取り、tool_use ループで処理して結果を返す
     *
     * @return array{message: string, actions: array}
     */
    public function chat(string $userMessage, bool $confirmed = false): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('ANTHROPIC_API_KEY が設定されていません。.env に追加してください。');
        }

        $messages = [
            ['role' => 'user', 'content' => $userMessage],
        ];

        $actions = [];

        // tool_use ループ（最大 MAX_TOOL_ROUNDS 回）
        for ($round = 0; $round < self::MAX_TOOL_ROUNDS; $round++) {
            $response = $this->callClaudeApi($messages);

            $stopReason = $response['stop_reason'] ?? 'end_turn';
            $contentBlocks = $response['content'] ?? [];

            // tool_use ブロックがなければ最終回答
            $toolUseBlocks = array_filter($contentBlocks, fn ($b) => ($b['type'] ?? '') === 'tool_use');

            if (empty($toolUseBlocks)) {
                // テキスト応答を返す
                $textParts = array_map(
                    fn ($b) => $b['text'] ?? '',
                    array_filter($contentBlocks, fn ($b) => ($b['type'] ?? '') === 'text')
                );

                return [
                    'message' => implode("\n", $textParts),
                    'actions' => $actions,
                ];
            }

            // assistant メッセージとして content blocks を追加
            $messages[] = ['role' => 'assistant', 'content' => $contentBlocks];

            // 各 tool_use を実行
            $toolResults = [];
            foreach ($toolUseBlocks as $block) {
                $toolName = $block['name'];
                $toolInput = $block['input'] ?? [];
                $toolUseId = $block['id'];

                Log::info('[NL API] tool_use', ['tool' => $toolName, 'input' => $toolInput, 'round' => $round]);

                $result = $this->executor->execute($toolName, $toolInput, $confirmed);
                $actions[] = [
                    'tool' => $toolName,
                    'input' => $toolInput,
                    'result' => $result,
                ];

                $toolResults[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $toolUseId,
                    'content' => json_encode($result, JSON_UNESCAPED_UNICODE),
                ];
            }

            $messages[] = ['role' => 'user', 'content' => $toolResults];
        }

        return [
            'message' => 'ツール実行の上限回数に達しました。操作を簡略化してください。',
            'actions' => $actions,
        ];
    }

    /**
     * Claude API を呼び出す
     */
    private function callClaudeApi(array $messages): array
    {
        $body = [
            'model' => $this->model,
            'max_tokens' => 4096,
            'system' => self::SYSTEM_PROMPT,
            'tools' => ToolDefinitions::all(),
            'messages' => $messages,
        ];

        try {
            $response = $this->http->post('v1/messages', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                ],
                'json' => $body,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $errorBody = $e->getResponse()?->getBody()?->getContents() ?? 'unknown';
            Log::error('[NL API] Claude API error', ['status' => $e->getResponse()?->getStatusCode(), 'body' => $errorBody]);
            throw new \RuntimeException('Claude API エラー: ' . $errorBody);
        }
    }
}
