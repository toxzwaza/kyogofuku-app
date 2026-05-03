<template>
    <div class="space-y-3">
        <!-- フィールド一覧 -->
        <div v-if="fields.length === 0" class="rounded-md border border-dashed border-brand-border p-6 text-center text-sm text-brand-text-muted">
            フィールドがありません。下のボタンから追加してください。
        </div>

        <div
            v-for="(field, idx) in fields"
            :key="field._uid"
            class="rounded-md border border-brand-border bg-white shadow-sm"
        >
            <!-- カードヘッダ -->
            <div class="flex items-center gap-2 border-b border-brand-border px-3 py-2 bg-brand-surface-2">
                <span class="text-xs font-mono text-brand-text-muted">#{{ idx + 1 }}</span>
                <span class="text-xs px-2 py-0.5 rounded bg-indigo-100 text-indigo-700">{{ typeLabel(field.type) }}</span>
                <span class="font-medium text-sm">{{ field.label || '(無題)' }}</span>
                <span class="text-xs text-brand-text-muted font-mono ml-1">{{ field.key }}</span>
                <span v-if="field.required" class="ml-auto text-xs px-2 py-0.5 rounded bg-red-100 text-red-700">必須</span>

                <div class="flex items-center gap-1" :class="{ 'ml-auto': !field.required }">
                    <button type="button" @click="moveUp(idx)" :disabled="idx === 0"
                        class="p-1 text-brand-text-muted hover:text-brand-text disabled:opacity-30" title="上へ">▲</button>
                    <button type="button" @click="moveDown(idx)" :disabled="idx === fields.length - 1"
                        class="p-1 text-brand-text-muted hover:text-brand-text disabled:opacity-30" title="下へ">▼</button>
                    <button type="button" @click="toggleExpand(idx)"
                        class="ml-1 px-2 py-0.5 text-xs border border-brand-border rounded hover:bg-brand-surface">
                        {{ expanded[idx] ? '閉じる' : '編集' }}
                    </button>
                    <button type="button" @click="duplicate(idx)"
                        class="px-2 py-0.5 text-xs border border-brand-border rounded hover:bg-brand-surface" title="複製">複製</button>
                    <button type="button" @click="confirmRemove(idx)"
                        class="px-2 py-0.5 text-xs border border-red-300 text-red-600 rounded hover:bg-red-50">削除</button>
                </div>
            </div>

            <!-- 編集フォーム -->
            <div v-if="expanded[idx]" class="p-3 space-y-3">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-brand-text mb-1">key（識別子・英数字）<span class="text-red-500">*</span></label>
                        <input
                            v-model="field.key"
                            @input="onKeyInput(field)"
                            type="text"
                            class="block w-full rounded border-brand-border text-sm font-mono"
                            placeholder="例: name, phone"
                        >
                        <p v-if="keyError(field, idx)" class="text-xs text-red-600 mt-1">{{ keyError(field, idx) }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-brand-text mb-1">label（表示名）<span class="text-red-500">*</span></label>
                        <input v-model="field.label" type="text"
                            class="block w-full rounded border-brand-border text-sm" placeholder="例: お名前">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-brand-text mb-1">type</label>
                        <select v-model="field.type" @change="onTypeChange(field)"
                            class="block w-full rounded border-brand-border text-sm">
                            <option v-for="t in TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="field.required" type="checkbox"
                                class="rounded border-brand-border text-brand-primary">
                            <span>必須</span>
                        </label>
                    </div>
                </div>

                <div v-if="hasPlaceholder(field.type)">
                    <label class="block text-xs font-medium text-brand-text mb-1">placeholder（プレースホルダー）</label>
                    <input v-model="field.placeholder" type="text"
                        class="block w-full rounded border-brand-border text-sm" placeholder="例: 090-1234-5678">
                </div>

                <div>
                    <label class="block text-xs font-medium text-brand-text mb-1">help（補足説明）</label>
                    <input v-model="field.help" type="text"
                        class="block w-full rounded border-brand-border text-sm" placeholder="例: ハイフン無しでも構いません">
                </div>

                <div v-if="hasDefault(field.type)">
                    <label class="block text-xs font-medium text-brand-text mb-1">default（初期値）</label>
                    <input v-model="field.default" type="text"
                        class="block w-full rounded border-brand-border text-sm">
                </div>

                <div v-if="field.type === 'number'" class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-brand-text mb-1">min</label>
                        <input v-model.number="field.min" type="number"
                            class="block w-full rounded border-brand-border text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-brand-text mb-1">max</label>
                        <input v-model.number="field.max" type="number"
                            class="block w-full rounded border-brand-border text-sm">
                    </div>
                </div>

                <div v-if="field.type === 'textarea'">
                    <label class="block text-xs font-medium text-brand-text mb-1">rows（行数）</label>
                    <input v-model.number="field.rows" type="number" min="2" max="20"
                        class="block w-32 rounded border-brand-border text-sm">
                </div>

                <!-- options 編集（select / radio / checkbox） -->
                <div v-if="hasOptions(field.type)">
                    <label class="block text-xs font-medium text-brand-text mb-1">
                        options（選択肢）
                        <span v-if="field.type === 'checkbox'" class="text-brand-text-muted">— 未指定なら単独 ON/OFF</span>
                    </label>
                    <div class="space-y-1">
                        <div v-for="(opt, oi) in field.options" :key="oi" class="flex gap-2 items-center">
                            <span class="text-xs text-brand-text-muted font-mono w-6">{{ oi + 1 }}.</span>
                            <input
                                :value="opt"
                                @input="updateOption(field, oi, $event.target.value)"
                                type="text"
                                class="flex-1 rounded border-brand-border text-sm"
                                :placeholder="`選択肢 ${oi + 1}`"
                            >
                            <button type="button" @click="moveOptionUp(field, oi)" :disabled="oi === 0"
                                class="px-1 text-xs text-brand-text-muted hover:text-brand-text disabled:opacity-30">▲</button>
                            <button type="button" @click="moveOptionDown(field, oi)" :disabled="oi === field.options.length - 1"
                                class="px-1 text-xs text-brand-text-muted hover:text-brand-text disabled:opacity-30">▼</button>
                            <button type="button" @click="removeOption(field, oi)"
                                class="px-2 py-0.5 text-xs border border-red-300 text-red-600 rounded hover:bg-red-50">削除</button>
                        </div>
                    </div>
                    <button type="button" @click="addOption(field)"
                        class="mt-2 px-3 py-1 text-xs border border-brand-border rounded hover:bg-brand-surface">
                        + 選択肢を追加
                    </button>
                </div>

                <!-- 条件付き表示（show_if） -->
                <div class="border-t border-dashed border-brand-border pt-3">
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input
                            type="checkbox"
                            :checked="!!field.show_if"
                            @change="toggleShowIf(field, $event.target.checked, idx)"
                            class="rounded border-brand-border text-brand-primary"
                        >
                        <span class="font-medium">条件付き表示にする</span>
                        <span class="text-xs text-brand-text-muted">— 他のフィールドの値に応じて表示／非表示</span>
                    </label>

                    <div v-if="field.show_if" class="mt-3 ml-6 p-3 bg-brand-surface-2 rounded space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-brand-text mb-1">依存するフィールド</label>
                            <select v-model="field.show_if.key" class="block w-full rounded border-brand-border text-sm">
                                <option value="">— 選択してください —</option>
                                <option
                                    v-for="other in otherFields(idx)"
                                    :key="other.key"
                                    :value="other.key"
                                >{{ other.label || '(無題)' }}（{{ other.key }} / {{ typeLabel(other.type) }}）</option>
                            </select>
                            <p v-if="field.show_if.key && !otherFields(idx).some(o => o.key === field.show_if.key)"
                                class="text-xs text-amber-700 mt-1">
                                ⚠ 依存先 '{{ field.show_if.key }}' が見つかりません（フィールドが削除された可能性）
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-brand-text mb-1">条件</label>
                            <select v-model="field.show_if.op" class="block w-full rounded border-brand-border text-sm">
                                <option value="not_empty">値が入力されているとき</option>
                                <option value="equals">特定の値と一致するとき</option>
                            </select>
                        </div>

                        <div v-if="field.show_if.op === 'equals'">
                            <label class="block text-xs font-medium text-brand-text mb-1">一致させる値</label>
                            <select
                                v-if="dependentOptions(field.show_if.key).length > 0"
                                v-model="field.show_if.value"
                                class="block w-full rounded border-brand-border text-sm"
                            >
                                <option value="">— 選択してください —</option>
                                <option
                                    v-for="opt in dependentOptions(field.show_if.key)"
                                    :key="opt"
                                    :value="opt"
                                >{{ opt }}</option>
                            </select>
                            <input
                                v-else
                                v-model="field.show_if.value"
                                type="text"
                                class="block w-full rounded border-brand-border text-sm"
                                placeholder="この値と一致したときに表示"
                            >
                        </div>

                        <p class="text-xs text-brand-text-muted">
                            この条件に合わない間は、表示されず未入力扱いになります。
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 追加 -->
        <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-brand-border">
            <select v-model="newType" class="rounded border-brand-border text-sm">
                <option v-for="t in TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
            <button type="button" @click="addField"
                class="px-3 py-1.5 text-sm bg-brand-primary text-white rounded hover:bg-brand-primary-hover">
                + フィールドを追加
            </button>
            <button type="button" @click="showJson = !showJson"
                class="ml-auto text-xs text-brand-primary hover:underline">
                {{ showJson ? 'JSON を隠す' : 'JSON を表示' }}
            </button>
        </div>

        <!-- JSON プレビュー（コピー・貼り付け用） -->
        <div v-if="showJson" class="space-y-2">
            <textarea
                v-model="jsonText"
                @input="onJsonInput"
                rows="10"
                class="block w-full font-mono text-xs rounded border-brand-border"
                placeholder="JSON 配列で直接編集することもできます"
            />
            <p v-if="jsonError" class="text-xs text-red-600">{{ jsonError }}</p>
            <p v-else class="text-xs text-brand-text-muted">
                JSON を編集すると即座に GUI に反映されます。エラーがあると反映されません。
            </p>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:modelValue']);

const TYPES = [
    { value: 'text',           label: 'text — 1行テキスト' },
    { value: 'textarea',       label: 'textarea — 複数行' },
    { value: 'tel',            label: 'tel — 電話番号' },
    { value: 'email',          label: 'email — メール' },
    { value: 'number',         label: 'number — 数値' },
    { value: 'url',            label: 'url — URL' },
    { value: 'date',           label: 'date — 日付' },
    { value: 'datetime-local', label: 'datetime-local — 日時' },
    { value: 'time',           label: 'time — 時刻' },
    { value: 'select',         label: 'select — プルダウン' },
    { value: 'radio',          label: 'radio — ラジオ' },
    { value: 'checkbox',       label: 'checkbox — チェックボックス' },
    { value: 'hidden',         label: 'hidden — 非表示' },
];
const TYPE_LABEL_MAP = Object.fromEntries(TYPES.map(t => [t.value, t.label.split(' — ')[0]]));

const TYPES_WITH_OPTIONS  = ['select', 'radio', 'checkbox'];
const TYPES_WITH_PLACEHOLDER = ['text', 'tel', 'email', 'number', 'url', 'textarea'];
const TYPES_WITH_DEFAULT  = ['text', 'tel', 'email', 'number', 'url', 'textarea', 'date', 'datetime-local', 'time', 'hidden'];

let uidSeq = 0;
const nextUid = () => `f${++uidSeq}_${Date.now()}`;
const ensureUid = (f) => (f._uid ? f : { ...f, _uid: nextUid() });

const fields = ref((props.modelValue || []).map(ensureUid));
const expanded = ref({});
const newType = ref('text');
const showJson = ref(false);
const jsonText = ref('');
const jsonError = ref('');
const lastEmitted = ref('');

// 初期表示時、フィールドが少なければ全部展開
fields.value.forEach((_, i) => { expanded.value[i] = fields.value.length <= 3; });

const typeLabel = (t) => TYPE_LABEL_MAP[t] || t;
const hasOptions = (t) => TYPES_WITH_OPTIONS.includes(t);
const hasPlaceholder = (t) => TYPES_WITH_PLACEHOLDER.includes(t);
const hasDefault = (t) => TYPES_WITH_DEFAULT.includes(t);

function emitChange() {
    // _uid を除去し、空値も除去してクリーンな配列を出す
    const cleaned = fields.value.map(f => stripField(f));
    const json = JSON.stringify(cleaned);
    if (json === lastEmitted.value) return;
    lastEmitted.value = json;
    emit('update:modelValue', cleaned);
    // showJson が開いているときは textarea も更新
    if (showJson.value) jsonText.value = JSON.stringify(cleaned, null, 2);
}

function stripField(f) {
    const out = {
        key: f.key || '',
        label: f.label || '',
        type: f.type || 'text',
        required: !!f.required,
    };
    for (const k of ['placeholder', 'help', 'default', 'min', 'max', 'rows']) {
        if (f[k] !== undefined && f[k] !== null && f[k] !== '') out[k] = f[k];
    }
    if (hasOptions(f.type) && Array.isArray(f.options) && f.options.length > 0) {
        out.options = f.options.map(o => String(o)).filter(o => o !== '');
        if (out.options.length === 0) delete out.options;
    }
    if (f.show_if && typeof f.show_if === 'object' && f.show_if.key) {
        const si = { key: String(f.show_if.key), op: f.show_if.op === 'equals' ? 'equals' : 'not_empty' };
        if (si.op === 'equals' && f.show_if.value !== undefined && f.show_if.value !== '') {
            si.value = String(f.show_if.value);
        }
        out.show_if = si;
    }
    return out;
}

function otherFields(idx) {
    return fields.value
        .filter((_, i) => i !== idx)
        .filter(f => f.key)
        .map(f => ({ key: f.key, label: f.label, type: f.type }));
}

function dependentOptions(depKey) {
    if (!depKey) return [];
    const dep = fields.value.find(f => f.key === depKey);
    if (!dep || !Array.isArray(dep.options)) return [];
    return dep.options.filter(o => o !== '');
}

function toggleShowIf(field, on, idx) {
    if (on) {
        // 直前のフィールドをデフォルト依存先として提案（自分以外）
        const candidates = otherFields(idx);
        field.show_if = {
            key: candidates.length > 0 ? candidates[Math.max(0, idx - 1)]?.key || candidates[0].key : '',
            op: 'not_empty',
        };
    } else {
        delete field.show_if;
    }
    emitChange();
}

function addField() {
    const f = ensureUid({
        key: suggestKey(newType.value),
        label: '',
        type: newType.value,
        required: false,
    });
    if (hasOptions(f.type)) f.options = ['選択肢1', '選択肢2'];
    fields.value.push(f);
    expanded.value[fields.value.length - 1] = true;
    emitChange();
}

function suggestKey(type) {
    const base = `field_${type.replace(/-/g, '_')}`;
    let i = 1;
    const used = new Set(fields.value.map(f => f.key));
    let candidate = base;
    while (used.has(candidate)) candidate = `${base}_${++i}`;
    return candidate;
}

function confirmRemove(idx) {
    const label = fields.value[idx].label || fields.value[idx].key || '(無題)';
    if (!confirm(`「${label}」を削除しますか？`)) return;
    fields.value.splice(idx, 1);
    rebuildExpanded();
    emitChange();
}

function duplicate(idx) {
    const src = fields.value[idx];
    const copy = ensureUid({ ...JSON.parse(JSON.stringify(stripField(src))), key: suggestKey(src.type) });
    fields.value.splice(idx + 1, 0, copy);
    rebuildExpanded();
    expanded.value[idx + 1] = true;
    emitChange();
}

function moveUp(idx) {
    if (idx === 0) return;
    const arr = fields.value;
    [arr[idx - 1], arr[idx]] = [arr[idx], arr[idx - 1]];
    rebuildExpanded();
    emitChange();
}
function moveDown(idx) {
    if (idx === fields.value.length - 1) return;
    const arr = fields.value;
    [arr[idx + 1], arr[idx]] = [arr[idx], arr[idx + 1]];
    rebuildExpanded();
    emitChange();
}
function rebuildExpanded() {
    // 削除/移動で index がズレるので再構築
    const next = {};
    fields.value.forEach((f, i) => { next[i] = expanded.value[i] ?? false; });
    expanded.value = next;
}
function toggleExpand(idx) { expanded.value[idx] = !expanded.value[idx]; }

function onTypeChange(field) {
    if (hasOptions(field.type)) {
        if (!Array.isArray(field.options) || field.options.length === 0) {
            field.options = ['選択肢1', '選択肢2'];
        }
    } else {
        delete field.options;
    }
    emitChange();
}
function onKeyInput(field) {
    field.key = (field.key || '').replace(/[^a-zA-Z0-9_]/g, '');
    emitChange();
}

function keyError(field, idx) {
    const k = field.key || '';
    if (k === '') return 'key を入力してください';
    if (!/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/.test(k)) return '英字で始まり、英数字とアンダースコアのみ（最大64文字）';
    const dup = fields.value.findIndex((f, i) => i !== idx && f.key === k);
    if (dup !== -1) return `他のフィールド (#${dup + 1}) と key が重複しています`;
    return '';
}

function addOption(field) {
    if (!Array.isArray(field.options)) field.options = [];
    field.options.push('');
    emitChange();
}
function removeOption(field, oi) { field.options.splice(oi, 1); emitChange(); }
function updateOption(field, oi, value) { field.options[oi] = value; emitChange(); }
function moveOptionUp(field, oi) {
    if (oi === 0) return;
    [field.options[oi - 1], field.options[oi]] = [field.options[oi], field.options[oi - 1]];
    emitChange();
}
function moveOptionDown(field, oi) {
    if (oi === field.options.length - 1) return;
    [field.options[oi + 1], field.options[oi]] = [field.options[oi], field.options[oi + 1]];
    emitChange();
}

// JSON 直編集
watch(showJson, (v) => {
    if (v) jsonText.value = JSON.stringify(fields.value.map(stripField), null, 2);
});
function onJsonInput() {
    jsonError.value = '';
    try {
        const parsed = JSON.parse(jsonText.value || '[]');
        if (!Array.isArray(parsed)) {
            jsonError.value = '配列ではありません';
            return;
        }
        fields.value = parsed.map(ensureUid);
        rebuildExpanded();
        emitChange();
    } catch (e) {
        jsonError.value = '不正な JSON です: ' + e.message;
    }
}

// 親が外部からスキーマを差し替えたとき（例：サンプル挿入）に同期
watch(() => props.modelValue, (val) => {
    const incoming = JSON.stringify(val || []);
    const current = JSON.stringify(fields.value.map(stripField));
    if (incoming === current) return;
    fields.value = (val || []).map(ensureUid);
    rebuildExpanded();
    lastEmitted.value = incoming;
    if (showJson.value) jsonText.value = JSON.stringify(val || [], null, 2);
});

// 内部編集 → 親通知（深いウォッチ）
watch(fields, emitChange, { deep: true });
</script>
