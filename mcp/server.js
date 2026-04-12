import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { z } from "zod";

// ── 設定 ──
const API_BASE = process.env.KYOGOFUKU_API_BASE || "https://kyogofuku-event.com";
const API_TOKEN = process.env.KYOGOFUKU_API_TOKEN || "";

// ── API 呼び出し ──
async function callTool(toolName, input, confirm = false) {
  const url = `${API_BASE}/api/tools/execute`;
  const res = await fetch(url, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      Authorization: `Bearer ${API_TOKEN}`,
    },
    body: JSON.stringify({ tool: toolName, input, confirm }),
  });

  const data = await res.json();
  if (!res.ok) {
    throw new Error(data.error || `HTTP ${res.status}`);
  }
  return data;
}

function formatResult(result) {
  if (result.needs_confirm) {
    return `⚠️ 書き込み操作です。実行するにはこのツールを confirm=true で再度呼び出してください。\n\nツール: ${result.tool}\n入力: ${JSON.stringify(result.input, null, 2)}`;
  }
  if (!result.success) {
    return `❌ エラー: ${result.error || "不明なエラー"}`;
  }
  return JSON.stringify(result.data, null, 2);
}

// ── MCP Server ──
const server = new McpServer({
  name: "kyogofuku",
  version: "1.0.0",
});

// ── Tool 定義 ──

server.tool(
  "list_events",
  "イベント一覧を取得する。公開状態や店舗名でフィルタ可能。",
  {
    status: z
      .enum(["公開中", "受付終了", "非公開", "すべて"])
      .optional()
      .describe("公開状態でフィルタ"),
    shop_name: z.string().optional().describe("店舗名でフィルタ（部分一致）"),
  },
  async (input) => {
    const result = await callTool("list_events", input);
    return { content: [{ type: "text", text: formatResult(result) }] };
  }
);

server.tool(
  "get_event",
  "イベントの詳細情報を取得する。IDまたはタイトルのキーワードで検索。",
  {
    event_id: z.number().optional().describe("イベントID"),
    title_keyword: z
      .string()
      .optional()
      .describe("イベントタイトルの部分一致検索キーワード"),
  },
  async (input) => {
    const result = await callTool("get_event", input);
    return { content: [{ type: "text", text: formatResult(result) }] };
  }
);

server.tool(
  "create_event",
  "新しいイベントを作成する。書き込み操作のため confirm=true が必要。",
  {
    title: z.string().describe("イベントタイトル"),
    form_type: z
      .enum(["reservation", "reservation_hakama", "document", "contact"])
      .describe(
        "フォーム種別。reservation=振袖予約, reservation_hakama=袴予約, document=資料請求, contact=お問い合わせ"
      ),
    start_at: z.string().optional().describe("開始日（YYYY-MM-DD）"),
    end_at: z.string().optional().describe("受付終了日（YYYY-MM-DD）"),
    shop_names: z
      .array(z.string())
      .optional()
      .describe('紐づける店舗名の配列。例: ["岡山店"]'),
    is_public: z.boolean().optional().describe("公開状態"),
    description: z.string().optional().describe("説明文"),
    confirm: z
      .boolean()
      .optional()
      .describe("trueで実行。省略時は確認メッセージを返す。"),
  },
  async (input) => {
    const { confirm, ...toolInput } = input;
    const result = await callTool("create_event", toolInput, confirm ?? false);
    return { content: [{ type: "text", text: formatResult(result) }] };
  }
);

server.tool(
  "update_event",
  "既存イベントを更新する。書き込み操作のため confirm=true が必要。",
  {
    event_id: z.number().describe("イベントID（必須）"),
    title: z.string().optional().describe("新しいタイトル"),
    end_at: z.string().optional().describe("新しい受付終了日（YYYY-MM-DD）"),
    is_public: z.boolean().optional().describe("公開/非公開"),
    description: z.string().optional().describe("新しい説明文"),
    confirm: z.boolean().optional().describe("trueで実行"),
  },
  async (input) => {
    const { confirm, ...toolInput } = input;
    const result = await callTool("update_event", toolInput, confirm ?? false);
    return { content: [{ type: "text", text: formatResult(result) }] };
  }
);

server.tool(
  "list_timeslots",
  "指定イベントの予約枠一覧を取得する。日付や会場でフィルタ可能。",
  {
    event_id: z.number().describe("イベントID（必須）"),
    date: z.string().optional().describe("日付でフィルタ（YYYY-MM-DD）"),
    venue_name: z.string().optional().describe("会場名でフィルタ（部分一致）"),
  },
  async (input) => {
    const result = await callTool("list_timeslots", input);
    return { content: [{ type: "text", text: formatResult(result) }] };
  }
);

server.tool(
  "create_timeslots",
  "予約枠を一括作成する。書き込み操作のため confirm=true が必要。",
  {
    event_id: z.number().describe("イベントID（必須）"),
    date: z.string().describe("日付（YYYY-MM-DD）"),
    times: z
      .array(z.string())
      .describe('時刻の配列（HH:MM形式）。例: ["10:00", "11:00"]'),
    capacity: z.number().describe("各枠の定員"),
    venue_name: z.string().optional().describe("会場名"),
    confirm: z.boolean().optional().describe("trueで実行"),
  },
  async (input) => {
    const { confirm, ...toolInput } = input;
    const result = await callTool(
      "create_timeslots",
      toolInput,
      confirm ?? false
    );
    return { content: [{ type: "text", text: formatResult(result) }] };
  }
);

server.tool(
  "update_timeslot",
  "予約枠を更新する。書き込み操作のため confirm=true が必要。",
  {
    timeslot_id: z.number().describe("予約枠ID（必須）"),
    capacity: z.number().optional().describe("新しい定員"),
    is_active: z.boolean().optional().describe("有効/無効"),
    confirm: z.boolean().optional().describe("trueで実行"),
  },
  async (input) => {
    const { confirm, ...toolInput } = input;
    const result = await callTool(
      "update_timeslot",
      toolInput,
      confirm ?? false
    );
    return { content: [{ type: "text", text: formatResult(result) }] };
  }
);

server.tool(
  "adjust_capacity",
  "予約枠の定員を増減する。書き込み操作のため confirm=true が必要。",
  {
    timeslot_id: z.number().describe("予約枠ID（必須）"),
    adjustment: z
      .number()
      .describe("増減数。+3で3人増加、-2で2人減少。（必須）"),
    confirm: z.boolean().optional().describe("trueで実行"),
  },
  async (input) => {
    const { confirm, ...toolInput } = input;
    const result = await callTool(
      "adjust_capacity",
      toolInput,
      confirm ?? false
    );
    return { content: [{ type: "text", text: formatResult(result) }] };
  }
);

server.tool(
  "delete_timeslot",
  "予約枠を削除する。予約が存在する枠は削除できない。書き込み操作のため confirm=true が必要。",
  {
    timeslot_id: z.number().describe("予約枠ID（必須）"),
    confirm: z.boolean().optional().describe("trueで実行"),
  },
  async (input) => {
    const { confirm, ...toolInput } = input;
    const result = await callTool(
      "delete_timeslot",
      toolInput,
      confirm ?? false
    );
    return { content: [{ type: "text", text: formatResult(result) }] };
  }
);

server.tool(
  "list_reservations",
  "予約一覧を取得する。イベントID、ステータス、日付でフィルタ可能。",
  {
    event_id: z.number().optional().describe("イベントIDでフィルタ"),
    status: z
      .enum(["未対応", "確認中", "返信待ち", "対応完了済み", "キャンセル済み"])
      .optional()
      .describe("ステータスでフィルタ"),
    date: z.string().optional().describe("予約日でフィルタ（YYYY-MM-DD）"),
    limit: z.number().optional().describe("取得件数上限。デフォルト20。"),
  },
  async (input) => {
    const result = await callTool("list_reservations", input);
    return { content: [{ type: "text", text: formatResult(result) }] };
  }
);

server.tool(
  "get_reservation",
  "予約の詳細情報を取得する。",
  {
    reservation_id: z.number().describe("予約ID（必須）"),
  },
  async (input) => {
    const result = await callTool("get_reservation", input);
    return { content: [{ type: "text", text: formatResult(result) }] };
  }
);

server.tool(
  "update_reservation_status",
  "予約のステータスを変更する。書き込み操作のため confirm=true が必要。",
  {
    reservation_id: z.number().describe("予約ID（必須）"),
    status: z
      .enum(["未対応", "確認中", "返信待ち", "対応完了済み", "キャンセル済み"])
      .describe("新しいステータス（必須）"),
    confirm: z.boolean().optional().describe("trueで実行"),
  },
  async (input) => {
    const { confirm, ...toolInput } = input;
    const result = await callTool(
      "update_reservation_status",
      toolInput,
      confirm ?? false
    );
    return { content: [{ type: "text", text: formatResult(result) }] };
  }
);

// ── 起動 ──
const transport = new StdioServerTransport();
await server.connect(transport);
