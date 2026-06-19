<template>
    <Head title="端末管理" />

    <AdminLayout :breadcrumb="[{ label: 'システム' }, { label: '端末管理' }]">
        <UiPageHeader
            title="端末管理"
            description="ログインを許可する端末（店舗ごとに登録）と、店舗の端末登録パスワードを管理します。"
        />

        <!-- 端末ゲート状態 -->
        <div
            class="mb-5 rounded-soft border px-4 py-3 text-sm"
            :class="deviceGateEnabled
                ? 'bg-uguisu-50/60 border-uguisu-200 text-uguisu-800'
                : 'bg-natane-50/60 border-natane-200 text-natane-800'"
        >
            <template v-if="deviceGateEnabled">
                端末ゲートは<strong>有効</strong>です。登録済み端末（または店舗パスワードでの登録）がないとログインできません。
            </template>
            <template v-else>
                端末ゲートは現在<strong>無効</strong>です（<code>DEVICE_GATE_ENABLED=false</code>）。各店舗のパスワード設定が完了したら、サーバの環境設定で有効化してください。
            </template>
        </div>

        <!-- 店舗パスワード設定 -->
        <section class="mb-8">
            <h3 class="text-sm font-semibold text-brand-text mb-3">店舗ごとの端末登録パスワード</h3>
            <UiDataTable :columns="shopColumns" :rows="shops || []" empty-message="店舗がありません。">
                <template #cell-name="{ value }">
                    <span class="font-medium">{{ value }}</span>
                </template>
                <template #cell-has_password="{ row }">
                    <UiBadge :variant="row.has_password ? 'success' : 'neutral'" size="sm">
                        {{ row.has_password ? '設定済み' : '未設定' }}
                    </UiBadge>
                </template>
                <template #cell-active_device_count="{ value }">
                    <span class="tabular-nums">{{ value }} 台</span>
                </template>
                <template #cell-password_updated_at="{ value }">
                    <span class="text-brand-text-muted text-xs">{{ formatDateTime(value) }}</span>
                </template>
                <template #cell-actions="{ row }">
                    <div class="flex items-center justify-end">
                        <UiButton size="sm" variant="ghost" @click="openPasswordDialog(row)">
                            <KeyRound :size="13" />
                            <span class="ml-1">{{ row.has_password ? '変更' : '設定' }}</span>
                        </UiButton>
                    </div>
                </template>
            </UiDataTable>
        </section>

        <!-- 登録端末一覧 -->
        <section>
            <h3 class="text-sm font-semibold text-brand-text mb-3">登録端末一覧</h3>
            <UiDataTable :columns="deviceColumns" :rows="devices || []" empty-message="登録された端末はありません。">
                <template #cell-device_code="{ value }">
                    <span class="font-mono font-semibold">{{ value }}</span>
                </template>
                <template #cell-shop="{ row }">
                    <span>{{ row.shop?.name ?? '-' }}</span>
                </template>
                <template #cell-label="{ value }">
                    <span>{{ value || '-' }}</span>
                </template>
                <template #cell-ip="{ row }">
                    <span class="text-xs text-brand-text-muted font-mono">{{ row.last_ip || row.ip_address || '-' }}</span>
                </template>
                <template #cell-user_agent="{ value }">
                    <span class="text-xs text-brand-text-muted">{{ shortUa(value) }}</span>
                </template>
                <template #cell-last_used_at="{ value }">
                    <span class="text-xs text-brand-text-muted">{{ formatDateTime(value) }}</span>
                </template>
                <template #cell-status="{ row }">
                    <UiBadge :variant="row.active ? 'success' : 'neutral'" size="sm">
                        {{ row.active ? '有効' : '解除済' }}
                    </UiBadge>
                </template>
                <template #cell-actions="{ row }">
                    <div class="flex items-center justify-end">
                        <UiButton v-if="row.active" size="sm" variant="ghost" class="text-brand-danger" @click="askRevoke(row)">
                            <Trash2 :size="13" />
                            <span class="ml-1">解除</span>
                        </UiButton>
                        <span v-else class="text-xs text-brand-text-subtle">{{ formatDateTime(row.revoked_at) }} 解除</span>
                    </div>
                </template>
            </UiDataTable>
        </section>

        <!-- パスワード設定ダイアログ -->
        <UiDialog v-model:open="passwordOpen" :title="`端末登録パスワード：${passwordTarget?.name ?? ''}`" size="md">
            <p class="text-xs text-brand-text-muted mb-4">
                この店舗の端末を初回登録する際に入力するパスワードです。設定/変更すると、以降の新規登録に適用されます（既存の登録端末には影響しません）。
            </p>
            <UiFormField label="新しいパスワード">
                <UiInput v-model="newPassword" type="password" placeholder="4文字以上" />
            </UiFormField>
            <p v-if="passwordError" class="mt-2 text-sm text-akane-600">{{ passwordError }}</p>
            <template #footer>
                <UiButton variant="ghost" @click="passwordOpen = false">キャンセル</UiButton>
                <UiButton variant="primary" :loading="savingPassword" @click="savePassword">保存する</UiButton>
            </template>
        </UiDialog>

        <!-- 解除確認ダイアログ -->
        <UiDialog v-model:open="revokeOpen" title="端末を解除">
            <p class="text-sm text-brand-text-muted">
                端末 <span class="font-mono font-semibold text-brand-text">{{ revokeTarget?.device_code }}</span>
                （{{ revokeTarget?.shop?.name }}）を解除します。<br>
                解除後、この端末は次回ログイン時に再度パスワード入力が必要になります。
            </p>
            <template #footer>
                <UiButton variant="ghost" @click="revokeOpen = false">キャンセル</UiButton>
                <UiButton variant="danger" :loading="revoking" @click="confirmRevoke">解除する</UiButton>
            </template>
        </UiDialog>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { UiPageHeader, UiButton, UiDataTable, UiDialog, UiBadge, UiFormField, UiInput } from '@/Components/UI';
import { KeyRound, Trash2 } from 'lucide-vue-next';

defineProps({
    devices: { type: Array, default: () => [] },
    shops: { type: Array, default: () => [] },
    deviceGateEnabled: { type: Boolean, default: false },
});

const shopColumns = [
    { key: 'name', label: '店舗' },
    { key: 'has_password', label: 'パスワード', width: '120px' },
    { key: 'active_device_count', label: '有効端末', width: '100px' },
    { key: 'password_updated_at', label: '最終更新', width: '160px' },
    { key: 'actions', label: '', align: 'right', width: '110px', noLink: true },
];

const deviceColumns = [
    { key: 'device_code', label: '端末ID', width: '140px' },
    { key: 'shop', label: '店舗' },
    { key: 'label', label: 'ラベル' },
    { key: 'ip', label: 'IP', width: '130px' },
    { key: 'user_agent', label: 'ブラウザ' },
    { key: 'last_used_at', label: '最終利用', width: '150px' },
    { key: 'status', label: '状態', width: '90px' },
    { key: 'actions', label: '', align: 'right', width: '130px', noLink: true },
];

function formatDateTime(iso) {
    if (!iso) return '-';
    const d = new Date(iso);
    if (isNaN(d.getTime())) return '-';
    const p = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}/${p(d.getMonth() + 1)}/${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}`;
}

function shortUa(ua) {
    if (!ua) return '-';
    const m = ua.match(/(Edg|Chrome|Firefox|Safari)\/[\d.]+/);
    let browser = m ? m[0].replace(/\/[\d.]+/, '') : 'その他';
    let os = '';
    if (/iPhone|iPad/.test(ua)) os = 'iOS';
    else if (/Android/.test(ua)) os = 'Android';
    else if (/Windows/.test(ua)) os = 'Windows';
    else if (/Macintosh|Mac OS/.test(ua)) os = 'Mac';
    return [browser, os].filter(Boolean).join(' / ');
}

// 店舗パスワード設定
const passwordOpen = ref(false);
const passwordTarget = ref(null);
const newPassword = ref('');
const passwordError = ref('');
const savingPassword = ref(false);

function openPasswordDialog(shop) {
    passwordTarget.value = shop;
    newPassword.value = '';
    passwordError.value = '';
    passwordOpen.value = true;
}

function savePassword() {
    passwordError.value = '';
    if (!newPassword.value || newPassword.value.length < 4) {
        passwordError.value = 'パスワードは4文字以上で入力してください。';
        return;
    }
    savingPassword.value = true;
    router.put(route('admin.device-registrations.shop-password', passwordTarget.value.id),
        { password: newPassword.value },
        {
            preserveScroll: true,
            onSuccess: () => { passwordOpen.value = false; },
            onError: (errors) => { passwordError.value = errors.password || '保存に失敗しました。'; },
            onFinish: () => { savingPassword.value = false; },
        }
    );
}

// 端末解除
const revokeOpen = ref(false);
const revokeTarget = ref(null);
const revoking = ref(false);

function askRevoke(device) {
    revokeTarget.value = device;
    revokeOpen.value = true;
}

function confirmRevoke() {
    if (!revokeTarget.value) return;
    revoking.value = true;
    router.delete(route('admin.device-registrations.revoke', revokeTarget.value.id), {
        preserveScroll: true,
        onFinish: () => {
            revoking.value = false;
            revokeOpen.value = false;
            revokeTarget.value = null;
        },
    });
}
</script>
