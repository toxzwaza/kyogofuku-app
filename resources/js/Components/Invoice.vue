<template>
  <div class="invoice">
    <!-- ヘッダー -->
    <div class="header">
      <h1>請 求 書</h1>
    </div>
    <!-- 上部 -->
    <div class="top">
      <!-- 請求先 -->
      <div class="client">
        <p class="client-name">{{ client.name }} 御中</p>
        <p>〒{{ client.postal }}</p>
        <p>{{ client.address }}</p>
      </div>
      <!-- 発行情報 -->
      <div class="meta">
        <table>
          <tr>
            <th>発行日</th>
            <td>{{ invoice.issueDate }}</td>
          </tr>
          <tr>
            <th>請求書No.</th>
            <td>{{ invoice.number }}</td>
          </tr>
          <tr>
            <th>支払期限</th>
            <td>{{ invoice.dueDate }}</td>
          </tr>
        </table>
      </div>
    </div>
    <p class="message">下記のとおり、御請求申し上げます。</p>
    <!-- 概要 -->
    <div class="summary">
      <table>
        <tr>
          <th>件名</th>
          <td>{{ invoice.title }}</td>
        </tr>
        <tr>
          <th>振込先</th>
          <td>{{ invoice.bank }}</td>
        </tr>
      </table>
    </div>
    <!-- 事業者 -->
    <div class="issuer">
      <p class="issuer-name">{{ issuer.name }}</p>
      <p>〒{{ issuer.postal }}</p>
      <p>{{ issuer.address1 }}</p>
      <p>{{ issuer.address2 }}</p>
      <p>TEL：{{ issuer.tel }}</p>
      <p>担当：{{ issuer.person }}</p>
    </div>
    <!-- 合計 -->
    <div class="total-box">
      <span>合計</span>
      <span class="total-amount">¥{{ totalAmount.toLocaleString() }}</span>
    </div>
    <!-- 明細 -->
    <table class="items">
      <thead>
        <tr>
          <th>項目</th>
          <th class="amount">金額</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="category in allExpenseCategories" :key="category">
          <td>{{ category }}</td>
          <td class="amount">
            <template v-if="getItemAmount(category) > 0">
              ¥{{ getItemAmount(category).toLocaleString() }}
            </template>
            <template v-else>
              -
            </template>
          </td>
        </tr>
      </tbody>
    </table>
    <!-- 注意書き -->
    <div class="footer">
      <p>当社は消費税の免税事業者です。本請求書には消費税額を明記しておりません。</p>
      <p>振込手数料は貴社にてご負担をお願いいたします。</p>
    </div>
    <!-- 備考 -->
    <div class="remarks">
      <h3>備考</h3>
      <div class="remarks-box">
        {{ invoice.remarks }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  client: Object,
  issuer: Object,
  invoice: Object,
  items: Array,
  totalHours: Number,
})

// すべての費用項目（9種類）
const allExpenseCategories = [
  'システム設計・要件定義費',
  'システム開発・実装・テスト費',
  'インフラ構築・外部サービス連携費',
  '運用支援・マニュアル作成費',
  'データ移行・PC設定・ITサポート費',
  '調査・打合せ・進行管理費',
  'デザイン制作費',
  '外注先折衝・ディレクション費',
  'そのほか雑費',
];

// 項目の金額を取得
function getItemAmount(category) {
  const item = props.items.find(i => i.name === category);
  return item ? item.amount : 0;
}

const totalAmount = computed(() => {
  // localStorageから時間単価を読み込む（デフォルト1075円）
  const hourlyRate = localStorage.getItem('hourlyRate') 
    ? Number(localStorage.getItem('hourlyRate')) 
    : 1075;
  
  // 時間合計×時間単価で計算
  if (props.totalHours) {
    return Math.round(props.totalHours * hourlyRate);
  }
  // フォールバック：itemsの合計
  return props.items.reduce((sum, i) => sum + Number(i.amount || 0), 0);
})
</script>

<style scoped>
@page {
  size: A4;
  margin: 15mm;
}

.invoice {
  font-family: "Hiragino Kaku Gothic ProN", "Meiryo", sans-serif;
  border-top: 4px solid #2e7d32;
  color: #000;
  padding: 10mm 12mm;
  max-width: 210mm;
  min-height: 267mm; /* A4高さ(297mm) - 上下マージン(30mm) = 267mm */
  margin: 0 auto;
  background: white;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
}

.header {
  flex-shrink: 0;
}

.header h1 {
  text-align: center;
  letter-spacing: 0.3em;
  margin: 6px 0;
  font-size: 20px;
}

.top {
  display: flex;
  justify-content: space-between;
  margin-bottom: 6px;
  flex-shrink: 0;
}

.client {
  width: 55%;
  font-size: 12px;
}

.client-name {
  font-weight: bold;
  margin-bottom: 2px;
  font-size: 14px;
}

.client p {
  margin: 1px 0;
  font-size: 12px;
}

.meta table {
  border-collapse: collapse;
  font-size: 11px;
}

.meta th,
.meta td {
  border: 1px solid #000;
  padding: 3px 6px;
  font-size: 11px;
}

.meta th {
  background: #f5f5f5;
  width: 80px;
}

.message {
  margin: 6px 0;
  font-size: 12px;
  flex-shrink: 0;
}

.summary {
  width: 50%;
  margin-bottom: 6px;
  font-size: 11px;
  flex-shrink: 0;
}

.summary table {
  width: 100%;
  border-collapse: collapse;
}

.summary th,
.summary td {
  border: 1px solid #000;
  padding: 3px;
  font-size: 11px;
}

.summary th {
  background: #f5f5f5;
  width: 80px;
}

.issuer {
  text-align: right;
  margin-top: -60px;
  font-size: 11px;
  flex-shrink: 0;
}

.issuer-name {
  font-weight: bold;
  font-size: 14px;
  margin-bottom: 1px;
}

.issuer p {
  margin: 0.5px 0;
  font-size: 11px;
}

.total-box {
  display: flex;
  justify-content: space-between;
  width: 280px;
  border: 2px solid #000;
  padding: 6px;
  margin: 8px 0;
  font-size: 16px;
  font-weight: bold;
  flex-shrink: 0;
}

.items {
  width: 100%;
  border-collapse: collapse;
  margin-top: 6px;
  font-size: 12px;
  flex: 1;
  min-height: 0;
}

.items th,
.items td {
  border: 1px solid #000;
  padding: 4px;
  font-size: 12px;
}

.items th {
  background: #333;
  color: #fff;
  padding: 5px 4px;
}

.items tbody tr {
  height: 24px;
}

.amount {
  text-align: right;
  width: 180px;
}

.footer {
  margin-top: 6px;
  font-size: 11px;
  flex-shrink: 0;
}

.footer p {
  margin: 2px 0;
}

.remarks {
  margin-top: 8px;
  flex-shrink: 0;
}

.remarks h3 {
  background: #333;
  color: #fff;
  padding: 3px;
  font-size: 12px;
  margin: 0;
}

.remarks-box {
  border: 1px dashed #000;
  min-height: 45px;
  padding: 5px;
  white-space: pre-wrap;
  font-size: 11px;
}
</style>

