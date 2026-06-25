<template>
    <Head title="クーポン管理" />
    <AdminLayout :breadcrumb="[{ label: '顧客' }, { label: 'クーポン' }]">
        <UiPageHeader title="クーポン管理" description="任意配布クーポンの発行・編集・併用可否を管理します。">
            <template #actions>
                <UiButton variant="primary" @click="openCreate">
                    <template #leading><Plus :size="14" /></template>
                    新規クーポン
                </UiButton>
            </template>
        </UiPageHeader>

        <UiCard variant="default" padding="md">
            <div v-if="coupons.data.length" class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-brand-surface-2">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium">クーポン名</th>
                            <th class="px-4 py-2 text-left font-medium">割引</th>
                            <th class="px-4 py-2 text-left font-medium">併用</th>
                            <th class="px-4 py-2 text-left font-medium">有効期間</th>
                            <th class="px-4 py-2 text-left font-medium">状態</th>
                            <th class="px-4 py-2 text-left font-medium">配布数</th>
                            <th class="px-4 py-2 text-left font-medium w-20">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-border">
                        <tr v-for="c in coupons.data" :key="c.id" class="hover:bg-brand-surface-2">
                            <td class="px-4 py-2 flex items-center gap-2">
                                <img v-if="c.thumbnail_url" :src="c.thumbnail_url" class="w-10 h-7 object-cover rounded border border-brand-border" />
                                {{ c.name }}
                            </td>
                            <td class="px-4 py-2">{{ discountLabel(c) }}</td>
                            <td class="px-4 py-2">
                                <span :class="c.combinable ? 'bg-green-100 text-green-900' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 rounded text-xs">{{ c.combinable ? '併用可' : '併用不可' }}</span>
                            </td>
                            <td class="px-4 py-2 text-xs">{{ validityLabel(c) }}</td>
                            <td class="px-4 py-2">
                                <span :class="statusClass(c.status)" class="px-2 py-0.5 rounded text-xs">{{ statusLabel(c.status) }}</span>
                            </td>
                            <td class="px-4 py-2">{{ c.distributed }}</td>
                            <td class="px-4 py-2 flex gap-2">
                                <button type="button" class="text-brand-primary hover:underline text-sm" @click="openEdit(c)">編集</button>
                                <button type="button" class="text-brand-danger hover:underline text-sm" @click="remove(c)">削除</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="py-10 text-center text-brand-text-muted text-sm">クーポンがありません</div>
        </UiCard>

        <!-- 作成/編集モーダル -->
        <UiDialog v-model:open="modalOpen" size="lg">
            <template #header>{{ editing ? 'クーポンを編集' : '新規クーポン' }}</template>
            <div class="space-y-3">
                <UiFormField label="クーポン名"><UiInput v-model="form.name" size="sm" /></UiFormField>
                <div class="grid grid-cols-2 gap-3">
                    <UiFormField label="割引タイプ">
                        <UiSelect v-model="form.discount_type" :options="[{value:'fixed',label:'金額(円)'},{value:'rate',label:'率(%)'}]" size="sm" />
                    </UiFormField>
                    <UiFormField label="割引値"><UiInput v-model.number="form.discount_value" type="number" min="0" size="sm" /></UiFormField>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <UiFormField label="有効日数（配布からN日）" hint="固定期限と排他">
                        <UiInput v-model.number="form.valid_days" type="number" min="1" size="sm" />
                    </UiFormField>
                    <UiFormField label="固定有効期限">
                        <UiInput v-model="form.valid_until_fixed" type="date" size="sm" />
                    </UiFormField>
                </div>
                <UiFormField label="利用条件"><UiTextarea v-model="form.terms_text" rows="2" /></UiFormField>
                <UiFormField label="サムネイル画像（LINE送付時の画像）">
                    <div class="flex items-center gap-2">
                        <img v-if="form.thumbnail_path" :src="thumbPreview" class="w-20 h-12 object-cover rounded border border-brand-border" />
                        <UiButton variant="subtle" size="sm" @click="pickerOpen = true">画像を選択</UiButton>
                        <UiButton v-if="form.thumbnail_path" variant="ghost" size="sm" @click="clearThumb">解除</UiButton>
                    </div>
                </UiFormField>
                <div class="flex items-center gap-4">
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="form.combinable" /> 併用可</label>
                    <UiFormField label="状態" class="flex-1">
                        <UiSelect v-model="form.status" :options="[{value:'active',label:'有効'},{value:'inactive',label:'無効'},{value:'archived',label:'アーカイブ'}]" size="sm" />
                    </UiFormField>
                </div>
                <div v-if="Object.keys(form.errors).length" class="p-2 bg-red-50 text-red-700 rounded text-sm">
                    <ul><li v-for="(m,k) in form.errors" :key="k">{{ m }}</li></ul>
                </div>
            </div>
            <template #footer>
                <UiButton variant="ghost" @click="modalOpen = false">キャンセル</UiButton>
                <UiButton variant="primary" :loading="form.processing" @click="submit">{{ editing ? '更新' : '作成' }}</UiButton>
            </template>
        </UiDialog>

        <MediaPicker :show="pickerOpen" :multiple="false" @close="pickerOpen = false" @select="onPickThumb" />
    </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { UiPageHeader, UiCard, UiButton, UiDialog, UiFormField, UiInput, UiSelect, UiTextarea } from '@/Components/UI';
import { Plus } from 'lucide-vue-next';
import MediaPicker from '@/Components/MediaPicker.vue';

const props = defineProps({ coupons: Object });

const modalOpen = ref(false);
const editing = ref(null);
const pickerOpen = ref(false);
const thumbPreviewUrl = ref('');

const form = useForm({
    name: '', description: '', thumbnail_path: '', thumbnail_disk: '', terms_text: '',
    discount_type: 'fixed', discount_value: 0, valid_days: null, valid_until_fixed: '',
    combinable: false, status: 'active',
});

const thumbPreview = computed(() => thumbPreviewUrl.value || '');

const discountLabel = (c) => c.discount_type === 'rate' ? `${c.discount_value}% OFF` : `${Number(c.discount_value).toLocaleString()}円 OFF`;
const validityLabel = (c) => c.valid_until_fixed ? `〜${c.valid_until_fixed}` : (c.valid_days ? `配布から${c.valid_days}日` : '無期限');
const statusLabel = (s) => ({ active: '有効', inactive: '無効', archived: 'アーカイブ' }[s] || s);
const statusClass = (s) => ({ active: 'bg-green-100 text-green-900', inactive: 'bg-gray-200 text-gray-700', archived: 'bg-gray-100 text-gray-500' }[s]);

const openCreate = () => {
    editing.value = null;
    form.reset();
    thumbPreviewUrl.value = '';
    modalOpen.value = true;
};
const openEdit = (c) => {
    editing.value = c;
    form.name = c.name;
    form.discount_type = c.discount_type;
    form.discount_value = c.discount_value;
    form.valid_days = c.valid_days;
    form.valid_until_fixed = c.valid_until_fixed || '';
    form.combinable = c.combinable;
    form.status = c.status;
    form.thumbnail_path = ''; // 既存サムネは編集時に再選択（簡易）
    thumbPreviewUrl.value = c.thumbnail_url || '';
    modalOpen.value = true;
};

const onPickThumb = (media) => {
    const m = Array.isArray(media) ? media[0] : media;
    if (m) {
        form.thumbnail_path = m.path;
        form.thumbnail_disk = m.storage_disk || 's3';
        thumbPreviewUrl.value = m.url;
    }
    pickerOpen.value = false;
};
const clearThumb = () => { form.thumbnail_path = ''; thumbPreviewUrl.value = ''; };

const submit = () => {
    const opts = { preserveScroll: true, onSuccess: () => { modalOpen.value = false; } };
    if (editing.value) {
        form.put(route('admin.coupons.update', editing.value.id), opts);
    } else {
        form.post(route('admin.coupons.store'), opts);
    }
};

const remove = (c) => {
    if (!confirm(`クーポン「${c.name}」を削除しますか？（配布実績があればアーカイブされます）`)) return;
    router.delete(route('admin.coupons.destroy', c.id), { preserveScroll: true });
};
</script>
